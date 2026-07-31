<?php namespace Logingrupa\CampaignpricingShopaholic\Classes\Item;

use Lang;
use Logingrupa\StoreExtender\Classes\Event\Currency\WholeNumberPriceFormatter;
use Lovata\OrdersShopaholic\Models\PromoMechanism;
use Lovata\Shopaholic\Classes\Helper\CurrencyHelper;
use Lovata\Toolbox\Classes\Helper\PriceHelper;
use Lovata\Toolbox\Classes\Item\ElementItem;

/**
 * Class CampaignPricingItem
 * @package Logingrupa\CampaignpricingShopaholic\Classes\Item
 *
 * Non-model ElementItem wrapping a single campaign pricing tier.
 * Provides currency-aware price accessors and localized display text.
 *
 * @property int    $quantity
 * @property float  $price_value
 * @property string $price
 * @property string $discount_display   Formatted discount ("5%" or "kr 20,-")
 * @property string $display_type       Resolver display type
 * @property string $display_text       Localized human-readable tier description
 * @property float  $discount_value
 * @property string $discount_type
 * @property string $mechanism_name
 */
class CampaignPricingItem extends ElementItem
{
    public const MODEL_CLASS = \Model::class;

    /**
     * Request-scoped mechanism cache. Multiple tiers of one campaign share a
     * mechanism; without this every tier ran its own PromoMechanism::find()
     * (plus a translate attribute load) at render time.
     * @var array<int, PromoMechanism|null>
     */
    private static array $arMechanismCache = [];

    /**
     * Factory method to create an item from a tier data array (not a model)
     * @param array<string, mixed> $arTierData
     */
    public static function makeFromData(array $arTierData): static
    {
        $obItem = new static(null, null);
        $obItem->arModelData = $arTierData;

        return $obItem;
    }

    /**
     * Prevent DB lookup since this item is not model-backed
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getElementData(): array
    {
        return [];
    }

    /**
     * Type-safe CurrencyHelper accessor (instance() returns mixed in Toolbox).
     */
    private function currencyHelper(): CurrencyHelper
    {
        /** @var CurrencyHelper $obHelper */
        $obHelper = CurrencyHelper::instance();

        return $obHelper;
    }

    /**
     * Type-safe string accessor for tier data.
     * Reads directly from arModelData (which we control via makeFromData).
     */
    private function stringData(string $sKey, string $sDefault = ''): string
    {
        $mValue = $this->arModelData[$sKey] ?? $sDefault;

        return is_scalar($mValue) ? (string) $mValue : $sDefault;
    }

    /**
     * Type-safe float accessor for tier data.
     */
    private function floatData(string $sKey, float $fDefault = 0.0): float
    {
        $mValue = $this->arModelData[$sKey] ?? $fDefault;

        return is_numeric($mValue) ? (float) $mValue : $fDefault;
    }

    /**
     * Type-safe int accessor for tier data.
     */
    private function intData(string $sKey, int $iDefault = 0): int
    {
        $mValue = $this->arModelData[$sKey] ?? $iDefault;

        return is_numeric($mValue) ? (int) $mValue : $iDefault;
    }

    /**
     * Get quantity attribute
     */
    protected function getQuantityAttribute(): int
    {
        return $this->intData('quantity');
    }

    /**
     * Get price value in active site currency (D-05, D-06, ITEM-03)
     *
     * For SpecificPriceByQuantity: discount_value IS the target price in EUR.
     * Convert directly to active currency.
     *
     * For OfferQuantityGreater: compute tier price from offer base price minus discount.
     * offer_base_price is injected by OfferItem extension in Phase 3.
     */
    protected function getPriceValueAttribute(): float
    {
        $fDiscountValue = $this->floatData('discount_value');
        $sDiscountType = $this->stringData('discount_type');
        $sPriceType = $this->stringData('price_type');

        // target_price: discount_value IS the final unit price (e.g., SpecificPriceByQuantity)
        if ($sPriceType === 'target_price') {
            return PriceHelper::round($this->currencyHelper()->convert($fDiscountValue));
        }

        // discount: compute tier price from offer base price minus discount
        $fBasePrice = $this->floatData('offer_base_price');

        if ($sDiscountType === 'fixed') {
            $fPrice = max(0, $fBasePrice - $fDiscountValue);
        } else {
            // percent
            $fPrice = $fBasePrice * (1 - $fDiscountValue / 100);
        }

        return PriceHelper::round($this->currencyHelper()->convert(max(0, $fPrice)));
    }

    /**
     * Get formatted price string (D-05)
     */
    protected function getPriceAttribute(): string
    {
        return $this->formatPrice($this->price_value);
    }

    /**
     * Get discount value attribute
     */
    protected function getDiscountValueAttribute(): float
    {
        return $this->floatData('discount_value');
    }

    /**
     * Get discount type attribute
     */
    protected function getDiscountTypeAttribute(): string
    {
        return $this->stringData('discount_type');
    }

    /**
     * Get mechanism name attribute
     */
    protected function getMechanismNameAttribute(): string
    {
        return $this->stringData('mechanism_name');
    }

    /**
     * Get display type attribute
     */
    protected function getDisplayTypeAttribute(): string
    {
        return $this->stringData('display_type');
    }

    /**
     * Get formatted discount display string.
     * Returns "5%" for percent discounts, or formatted currency amount for fixed.
     */
    protected function getDiscountDisplayAttribute(): string
    {
        $sDiscountType = $this->discount_type;
        $fDiscountValue = $this->discount_value;

        if ($sDiscountType === 'percent') {
            return $fDiscountValue . '%';
        }

        // Fixed discount — format as currency
        $fConverted = PriceHelper::round($this->currencyHelper()->convert($fDiscountValue));

        return $this->formatPrice($fConverted);
    }

    /**
     * Get localized display text describing this tier.
     * Uses translatable lang keys so each site can customize the wording.
     *
     * Display types and their output:
     *   target_price:   "Buy 10+ for kr 100,-"
     *   offer_quantity:  "Buy 3+ pcs: 5% discount" or "Buy 3+ pcs: kr 20,- discount"
     *   total_quantity:  "Total 4+ pcs: 5% discount"
     *   position_count:  "3+ products in cart: 5% discount"
     *   unconditional:   "5% discount" or "kr 20,- discount"
     *   custom:          Falls back to offer_quantity format
     */
    protected function getDisplayTextAttribute(): string
    {
        $arVariables = $this->getDisplayVariables();

        // Admin-defined template takes priority — resolved at render time for correct locale
        $sCustomTemplate = $this->resolveDisplayTemplate();
        if ($sCustomTemplate !== '') {
            return $this->resolveTemplate($sCustomTemplate, $arVariables);
        }

        // Fall back to plugin lang key based on display_type
        $sDisplayType = $this->display_type;
        $sLangKey = 'logingrupa.campaignpricingshopaholic::lang.display.' . $sDisplayType;

        // If lang key doesn't exist for this display_type, fall back to offer_quantity
        if (!Lang::has($sLangKey)) {
            $sLangKey = 'logingrupa.campaignpricingshopaholic::lang.display.offer_quantity';
        }

        return Lang::get($sLangKey, $arVariables);
    }

    /**
     * Get all available display variables for template resolution.
     * These are the placeholders admins can use in display_template.
     *
     * Available: :quantity, :price, :currency, :discount, :discount_value, :discount_type, :campaign_name
     * @return array<string, string|int|float>
     */
    protected function getDisplayVariables(): array
    {
        return [
            'quantity'       => $this->quantity,
            'price'          => $this->stringData('offer_currency') . $this->price,
            'currency'       => $this->stringData('offer_currency'),
            'discount'       => $this->discount_display,
            'discount_value' => $this->discount_value,
            'discount_type'  => $this->discount_type,
            'campaign_name'  => $this->stringData('campaign_name'),
            'count'          => $this->quantity,
        ];
    }

    /**
     * Resolve display_template from the PromoMechanism model at render time.
     * This ensures RainLab.Translate returns the correct locale's translation
     * instead of the locale that was active when the store cache was built.
     */
    private function resolveDisplayTemplate(): string
    {
        $iMechanismId = $this->intData('mechanism_id');
        if ($iMechanismId === 0) {
            // Fallback for data created before mechanism_id was stored
            return trim($this->stringData('display_template'));
        }

        if (!class_exists(PromoMechanism::class)) {
            return '';
        }

        if (!array_key_exists($iMechanismId, self::$arMechanismCache)) {
            self::$arMechanismCache[$iMechanismId] = PromoMechanism::find($iMechanismId);
        }

        $obMechanism = self::$arMechanismCache[$iMechanismId];
        if (!$obMechanism instanceof PromoMechanism) {
            return '';
        }

        $mTemplate = $obMechanism->display_template;

        return is_string($mTemplate) ? trim($mTemplate) : '';
    }

    /**
     * Resolve a display template string, replacing :placeholders with values.
     * The template is first run through the |_ translation system (RainLab.Translate),
     * then Laravel's :placeholder replacement is applied.
     *
     * @param array<string, string|int|float> $arVariables
     */
    protected function resolveTemplate(string $sTemplate, array $arVariables): string
    {
        // Sort keys longest-first to avoid partial replacement
        // (e.g., :discount must not replace inside :discount_type)
        $arKeys = array_keys($arVariables);
        usort($arKeys, fn (string $sKeyA, string $sKeyB): int => strlen($sKeyB) - strlen($sKeyA));

        foreach ($arKeys as $sKey) {
            $sTemplate = str_replace(':' . $sKey, (string) $arVariables[$sKey], $sTemplate);
        }

        return $sTemplate;
    }

    /**
     * Format a price value using the WholeNumberPriceFormatter if available.
     */
    protected function formatPrice(float $fPrice): string
    {
        if (class_exists(WholeNumberPriceFormatter::class)) {
            return WholeNumberPriceFormatter::formatWholeNumberPrice($fPrice);
        }

        return PriceHelper::format($fPrice);
    }
}
