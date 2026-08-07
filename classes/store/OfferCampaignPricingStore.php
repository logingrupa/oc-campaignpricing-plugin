<?php namespace Logingrupa\CampaignpricingShopaholic\Classes\Store;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Kharanenka\Helper\CCache;
use Logingrupa\CampaignpricingShopaholic\Classes\Tier\TierResolverRegistry;
use Lovata\CampaignsShopaholic\Models\Campaign;
use Lovata\OrdersShopaholic\Models\PromoMechanism;
use Lovata\Toolbox\Classes\Store\AbstractStoreWithParam;

/**
 * Class OfferCampaignPricingStore
 * @package Logingrupa\CampaignpricingShopaholic\Classes\Store
 *
 * Resolves campaign pricing tiers for a given offer by fan-out querying
 * 4 pivot tables (offer, product, brand, category), filters to quantity-based
 * mechanisms, extracts tier data, and caches with CCache.
 */
class OfferCampaignPricingStore extends AbstractStoreWithParam
{
    protected static $instance;

    /** @var string Shape of one cached tier row; see getCacheKey() */
    private const CACHE_KEY_VERSION = 'v2';

    /**
     * Type-safe integer from DB::value() which returns mixed.
     */
    private static function dbInt(mixed $mValue): int
    {
        return is_numeric($mValue) ? (int) $mValue : 0;
    }

    /**
     * Type-safe integer list from pluck()->all() which returns array<mixed>.
     * @param int|list<int> $mValue
     * @return list<int>
     */
    private static function dbIntList(string $sTable, string $sColumn, string $sWhere, int|array $mValue): array
    {
        $obQuery = DB::table($sTable);
        $obQuery = is_array($mValue)
            ? $obQuery->whereIn($sWhere, $mValue)
            : $obQuery->where($sWhere, $mValue);

        return array_values($obQuery->pluck($sColumn)
            ->map(fn (mixed $iVal): int => is_numeric($iVal) ? (int) $iVal : 0)
            ->all());
    }

    /**
     * 'Y-m-d H:i:s' from a date column, null when the column is empty. Strings
     * in that format compare lexicographically, which is what
     * Campaign::scopeCurrentActive() relies on as well.
     */
    private static function dbDateString(mixed $mValue): ?string
    {
        if ($mValue instanceof DateTimeInterface) {
            return $mValue->format('Y-m-d H:i:s');
        }

        return is_string($mValue) && $mValue !== '' ? $mValue : null;
    }

    /**
     * Offer id, prefixed with the version of the row shape held under it.
     *
     * 1.4.2 added the campaign date window to every row, and an entry written
     * before that carries no date to test, so a reader would quietly drop every
     * tier it holds until someone cleared the cache. A new prefix leaves those
     * entries unread instead. Bump it whenever the row shape changes.
     *
     * clear() and the per-request memo both key off this method, so invalidation
     * follows the prefix on its own.
     */
    protected function getCacheKey(): string
    {
        return self::CACHE_KEY_VERSION.'_'.self::dbInt($this->sValue);
    }

    /**
     * Tier list from cache or database, narrowed to the campaigns running now.
     *
     * Two deliberate departures from the parent.
     *
     * It accepts a cached empty array as a hit. The parent reads one as a miss,
     * because getIDListFromCache() casts the raw value with (array) and loses
     * the null-vs-[] distinction, so saveIDList() writes a [] that can never be
     * read back. Most offers carry no tiers at all, so without this they re-run
     * the whole pivot fan-out on every request, forever. Same override that
     * patches/lovata-campaigns-empty-list-cache.patch applies to the three
     * Lovata campaign stores; it is written out here because this store is ours.
     *
     * And the date window is applied HERE rather than in the query, so that what
     * CCache::forever holds does not depend on when it was built. A campaign
     * whose date_end merely passes saves no model and fires no event, so a list
     * built while it was running would go on describing it until someone
     * re-saved that campaign by hand.
     * @return array<int, array<string, mixed>>
     */
    protected function getIDList(): array
    {
        /** @var mixed $mCachedList */
        $mCachedList = CCache::get($this->getCacheTagList(), $this->getCacheKey());
        if (is_array($mCachedList)) {
            /** @var array<int, array<string, mixed>> $mCachedList */
            return self::filterByDateWindow($mCachedList);
        }

        $arTierList = $this->getIDListFromDB();
        $this->saveIDList($arTierList);

        return self::filterByDateWindow($arTierList);
    }

    /**
     * Keep the tiers whose campaign is running at this instant.
     *
     * Reproduces Campaign::scopeCurrentActive() exactly: a null date_end runs
     * forever, which is how 17 of the 39 campaigns are set up, and a null
     * date_begin never starts, because `date_begin <= now` is false for NULL in
     * SQL and the model requires the field anyway.
     * @param array<int, array<string, mixed>> $arTierList
     * @return array<int, array<string, mixed>>
     */
    protected static function filterByDateWindow(array $arTierList): array
    {
        $sDateNow = Carbon::now()->toDateTimeString();

        return array_values(array_filter(
            $arTierList,
            static function (array $arTier) use ($sDateNow): bool {
                $mDateBegin = $arTier['date_begin'] ?? null;
                if (!is_string($mDateBegin) || $mDateBegin > $sDateNow) {
                    return false;
                }

                $mDateEnd = $arTier['date_end'] ?? null;

                return !is_string($mDateEnd) || $mDateEnd > $sDateNow;
            }
        ));
    }

    /**
     * Get pricing tier data from database for an offer
     * @return array<int, array<string, mixed>>
     */
    protected function getIDListFromDB(): array
    {
        $iOfferId = self::dbInt($this->sValue);

        // Step 1: Get offer's product_id
        $iProductId = self::dbInt(DB::table('lovata_shopaholic_offers')
            ->where('id', $iOfferId)
            ->value('product_id'));

        if ($iProductId === 0) {
            return [];
        }

        // Step 2: Get product's brand_id
        $iBrandId = self::dbInt(DB::table('lovata_shopaholic_products')
            ->where('id', $iProductId)
            ->value('brand_id'));

        // Step 3: Get product's category IDs (primary + additional)
        $arCategoryIdList = $this->getProductCategoryIdList($iProductId);

        // Step 4: Query 4 pivot tables for campaign IDs
        $arCampaignIdList = $this->resolveCampaignIdList(
            $iOfferId,
            $iProductId,
            $iBrandId,
            $arCategoryIdList
        );

        if ($arCampaignIdList === []) {
            return [];
        }

        // Step 5: Filter to active, quantity-based campaigns and extract tier data
        return $this->extractPricingTierList($arCampaignIdList);
    }

    /**
     * Get primary and additional category IDs for a product
     * @return list<int>
     */
    protected function getProductCategoryIdList(int $iProductId): array
    {
        $iPrimaryCategoryId = self::dbInt(DB::table('lovata_shopaholic_products')
            ->where('id', $iProductId)
            ->value('category_id'));

        $arAdditionalCategoryIdList = self::dbIntList(
            'lovata_shopaholic_additional_categories',
            'category_id',
            'product_id',
            $iProductId
        );

        /** @var list<int> $arCategoryIdList */
        $arCategoryIdList = array_values(array_filter(
            array_unique(array_merge([$iPrimaryCategoryId], $arAdditionalCategoryIdList))
        ));

        return $arCategoryIdList;
    }

    /**
     * Query 4 pivot tables to resolve campaign IDs linked to an offer
     * @param list<int> $arCategoryIdList
     * @return list<int>
     */
    protected function resolveCampaignIdList(
        int $iOfferId,
        int $iProductId,
        int $iBrandId,
        array $arCategoryIdList
    ): array {
        // Direct offer link
        $arDirectOfferCampaignIdList = self::dbIntList('lovata_campaigns_shopaholic_campaign_offer', 'campaign_id', 'offer_id', $iOfferId);

        // Product link
        $arProductCampaignIdList = self::dbIntList('lovata_campaigns_shopaholic_campaign_product', 'campaign_id', 'product_id', $iProductId);

        // Brand link
        $arBrandCampaignIdList = [];
        if ($iBrandId !== 0) {
            $arBrandCampaignIdList = self::dbIntList('lovata_campaigns_shopaholic_campaign_brand', 'campaign_id', 'brand_id', $iBrandId);
        }

        // Category link (primary + additional)
        $arCategoryCampaignIdList = [];
        if ($arCategoryIdList !== []) {
            $arCategoryCampaignIdList = self::dbIntList('lovata_campaigns_shopaholic_campaign_category', 'campaign_id', 'category_id', $arCategoryIdList);
        }

        /** @var list<int> $arCampaignIdList */
        $arCampaignIdList = array_values(array_unique(array_merge(
            $arDirectOfferCampaignIdList,
            $arProductCampaignIdList,
            $arBrandCampaignIdList,
            $arCategoryCampaignIdList
        )));

        return $arCampaignIdList;
    }

    /**
     * Extract pricing tier data from active campaigns with quantity-based mechanisms.
     *
     * No currentActive() here on purpose: each row carries its campaign's date
     * window instead, and getIDList() applies it on the way out, so what goes
     * into a forever cache says nothing about when it was built. The active flag
     * stays, because that one is a saved field and clears the cache when it
     * changes.
     * @param list<int> $arCampaignIdList
     * @return array<int, array<string, mixed>>
     */
    protected function extractPricingTierList(array $arCampaignIdList): array
    {
        $obCampaignList = Campaign::active()
            ->whereIn('id', $arCampaignIdList)
            ->with('mechanism')
            ->get();

        $arResult = [];

        /** @var Campaign $obCampaign */
        foreach ($obCampaignList as $obCampaign) {
            /** @var PromoMechanism|null $obMechanism */
            $obMechanism = $obCampaign->mechanism;
            if ($obMechanism === null) {
                continue;
            }

            $sMechanismType = (string) $obMechanism->type;

            // Check if this mechanism type has a registered tier resolver
            $arResolver = TierResolverRegistry::getResolver($sMechanismType);
            if ($arResolver === null) {
                continue;
            }

            /** @var array<string, mixed> $arProperty */
            $arProperty = (array) $obMechanism->property;

            // Skip mechanisms where campaign pricing display is disabled
            if (array_get($arProperty, 'campaign_pricing_enabled') === '0') {
                continue;
            }

            // Skip mechanisms gated by shipping type or payment method
            if (TierResolverRegistry::hasShippingOrPaymentConstraint($arProperty)) {
                continue;
            }

            $iQuantity = TierResolverRegistry::extractQuantity($arProperty, $arResolver);
            $fDiscountValue = (float) $obMechanism->discount_value;
            $sDiscountType = (string) $obMechanism->discount_type;

            $arResult[] = [
                'campaign_id'       => $obCampaign->id,
                'campaign_name'     => $obCampaign->name,
                'date_begin'        => self::dbDateString($obCampaign->date_begin),
                'date_end'          => self::dbDateString($obCampaign->date_end),
                'mechanism_id'      => $obMechanism->id,
                'quantity'          => $iQuantity,
                'discount_value'    => $fDiscountValue,
                'discount_type'     => $sDiscountType,
                'mechanism_type'    => $sMechanismType,
                'mechanism_name'    => class_basename($sMechanismType),
                'price_type'        => $arResolver['price_type'],
                'display_type'      => $arResolver['display_type'],
            ];
        }

        // Sort by quantity ascending
        usort($arResult, fn (array $arTierA, array $arTierB): int => $arTierA['quantity'] <=> $arTierB['quantity']);

        return $arResult;
    }

    /**
     * Resolve all offer IDs affected by a campaign (via 4 pivot tables)
     * Used by event handlers for surgical cache invalidation (D-09)
     * @return list<int>
     */
    public static function resolveAffectedOfferIdList(Campaign $obCampaign): array
    {
        $iCampaignId = self::dbInt($obCampaign->id);

        // Direct offer links
        $arOfferIdList = self::dbIntList('lovata_campaigns_shopaholic_campaign_offer', 'offer_id', 'campaign_id', $iCampaignId);

        // Product links -> offers
        $arProductIdList = self::dbIntList('lovata_campaigns_shopaholic_campaign_product', 'product_id', 'campaign_id', $iCampaignId);

        if ($arProductIdList !== []) {
            $arOfferIdList = array_merge($arOfferIdList, self::dbIntList('lovata_shopaholic_offers', 'id', 'product_id', $arProductIdList));
        }

        // Brand links -> products -> offers
        $arBrandIdList = self::dbIntList('lovata_campaigns_shopaholic_campaign_brand', 'brand_id', 'campaign_id', $iCampaignId);

        if ($arBrandIdList !== []) {
            $arBrandProductIdList = self::dbIntList('lovata_shopaholic_products', 'id', 'brand_id', $arBrandIdList);
            $arOfferIdList = array_merge($arOfferIdList, self::dbIntList('lovata_shopaholic_offers', 'id', 'product_id', $arBrandProductIdList));
        }

        // Category links -> products (primary + additional) -> offers
        $arCategoryIdList = self::dbIntList('lovata_campaigns_shopaholic_campaign_category', 'category_id', 'campaign_id', $iCampaignId);

        if ($arCategoryIdList !== []) {
            $arPrimaryProductIdList = self::dbIntList('lovata_shopaholic_products', 'id', 'category_id', $arCategoryIdList);
            $arAdditionalProductIdList = self::dbIntList('lovata_shopaholic_additional_categories', 'product_id', 'category_id', $arCategoryIdList);
            /** @var list<int> $arAllProductIdList */
            $arAllProductIdList = array_values(array_unique(array_merge($arPrimaryProductIdList, $arAdditionalProductIdList)));
            $arOfferIdList = array_merge($arOfferIdList, self::dbIntList('lovata_shopaholic_offers', 'id', 'product_id', $arAllProductIdList));
        }

        /** @var list<int> $arUniqueOfferIdList */
        $arUniqueOfferIdList = array_values(array_unique($arOfferIdList));

        return $arUniqueOfferIdList;
    }
}
