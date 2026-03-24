<?php namespace Logingrupa\CampaignpricingShopaholic\Classes\Event;

use Logingrupa\CampaignpricingShopaholic\Classes\Collection\CampaignPricingCollection;
use Logingrupa\CampaignpricingShopaholic\Classes\Item\CampaignPricingItem;
use Logingrupa\CampaignpricingShopaholic\Classes\Store\OfferCampaignPricingStore;
use Lovata\Shopaholic\Classes\Item\OfferItem;

/**
 * Class OfferItemExtendHandler
 * @package Logingrupa\CampaignpricingShopaholic\Classes\Event
 *
 * Extends OfferItem with a `campaign_pricing_list` dynamic accessor that returns
 * a CampaignPricingCollection of pricing tiers for the offer (INTG-01, INTG-02, D-02).
 *
 * Tier merging logic (D-02):
 * - Multiple campaigns for the same offer may produce tiers at the same quantity threshold.
 * - Duplicate quantities are deduplicated, keeping the tier with the lowest price_value.
 * - Tiers are sorted ascending by quantity.
 */
class OfferItemExtendHandler
{
    /**
     * Subscribe to extend OfferItem with the campaign_pricing_list accessor
     * @param \Illuminate\Events\Dispatcher $obEvent
     */
    public function subscribe($obEvent): void
    {
        OfferItem::extend(function (OfferItem $obOfferItem): void {
            $this->extendOfferItem($obOfferItem);
        });
    }

    /**
     * Add the getCampaignPricingListAttribute dynamic method to the given OfferItem
     * @param OfferItem $obOfferItem
     */
    protected function extendOfferItem($obOfferItem): void
    {
        if (empty($obOfferItem) || !$obOfferItem instanceof OfferItem) {
            return;
        }

        $obOfferItem->addDynamicMethod('getCampaignPricingListAttribute', fn (): CampaignPricingCollection => $this->resolveCampaignPricingList($obOfferItem));
    }

    /**
     * Resolve and return the campaign pricing tier collection for an offer item.
     * Results are cached on the item instance to avoid redundant store lookups.
     */
    protected function resolveCampaignPricingList(OfferItem $obOfferItem): CampaignPricingCollection
    {
        // Return cached collection if already resolved for this item instance
        $obCachedList = $obOfferItem->getAttribute('campaign_pricing_list');
        if ($obCachedList instanceof CampaignPricingCollection) {
            return $obCachedList;
        }

        /** @var int|null $mOfferId */
        $mOfferId = $obOfferItem->id;
        $iOfferId = (int) ($mOfferId ?? 0);
        if ($iOfferId === 0) {
            return CampaignPricingCollection::make([]);
        }

        // Retrieve raw tier data array from the store (cached via AbstractStoreWithParam)
        /** @var OfferCampaignPricingStore $obStore */
        $obStore = OfferCampaignPricingStore::instance();
        /** @var array<int, array<string, mixed>> $arTierDataList */
        $arTierDataList = $obStore->get($iOfferId);
        if ($arTierDataList === []) {
            return CampaignPricingCollection::make([]);
        }

        // Inject offer context so tiers can compute price_value and display_text
        /** @var float|int|string|null $mBasePrice */
        $mBasePrice = $obOfferItem->getAttribute('price_value');
        $fBasePrice = is_numeric($mBasePrice) ? (float) $mBasePrice : 0.0;
        /** @var string|null $mCurrency */
        $mCurrency = $obOfferItem->currency;
        $sCurrency = $mCurrency ?? '';
        foreach ($arTierDataList as &$arTierData) {
            $arTierData['offer_base_price'] = $fBasePrice;
            $arTierData['offer_currency'] = $sCurrency;
        }
        unset($arTierData);

        // Merge tiers from multiple campaigns: deduplicate by quantity, keep lowest price (D-02)
        $arMergedTierList = $this->mergeTierList($arTierDataList, $fBasePrice);

        $obCollection = CampaignPricingCollection::make($arMergedTierList);

        // Cache the resolved collection on the item instance (avoids re-resolving within same request)
        $obOfferItem->setAttribute('campaign_pricing_list', $obCollection);

        return $obCollection;
    }

    /**
     * Merge tier data list by quantity, keeping the lowest-priced tier per quantity threshold (D-02).
     * Returns tiers sorted ascending by quantity.
     *
     * @param array<int, array<string, mixed>> $arTierDataList Raw tier data arrays (each must include offer_base_price)
     * @param float $fBasePrice Offer base price in active currency (already injected into each tier)
     * @return array<int, array<string, mixed>> Deduplicated, quantity-sorted tier data arrays
     */
    protected function mergeTierList(array $arTierDataList, float $fBasePrice): array
    {
        $arByQuantity = [];

        foreach ($arTierDataList as $arTierData) {
            $mQty = $arTierData['quantity'] ?? 0;
            $iQuantity = is_numeric($mQty) ? (int) $mQty : 0;

            if (!isset($arByQuantity[$iQuantity])) {
                // First tier for this quantity threshold — store as-is
                $arByQuantity[$iQuantity] = $arTierData;
                continue;
            }

            // Compare price_value of both tiers; keep the one with the lower price
            $obExistingItem = CampaignPricingItem::makeFromData($arByQuantity[$iQuantity]);
            $obCandidateItem = CampaignPricingItem::makeFromData($arTierData);

            if ($obCandidateItem->price_value < $obExistingItem->price_value) {
                $arByQuantity[$iQuantity] = $arTierData;
            }
        }

        // Sort by quantity ascending (D-07)
        ksort($arByQuantity);

        return array_values($arByQuantity);
    }
}
