<?php namespace Logingrupa\CampaignpricingShopaholic\Classes\Tier;

use Event;

/**
 * Class TierResolverRegistry
 * @package Logingrupa\CampaignpricingShopaholic\Classes\Tier
 *
 * Registry for mapping promo mechanism types to their tier extraction logic.
 * Follows the Shopaholic event-driven extensibility pattern.
 *
 * Built-in resolvers handle all standard Lovata position-level mechanisms.
 * Third-party plugins register custom resolvers via the event:
 *
 *   Event::listen('campaignpricing.tier.register_resolvers', function () {
 *       return [
 *           MyCustomMechanism::class => [
 *               'quantity_property' => 'my_qty_field',
 *               'price_type'        => 'target_price', // or 'discount'
 *           ],
 *       ];
 *   });
 *
 * Price types:
 *   - 'discount': discount_value is subtracted from base price (fixed) or percentage (percent).
 *                 This is the standard Shopaholic behavior.
 *   - 'target_price': discount_value IS the final unit price (e.g., SpecificPriceByQuantity).
 *
 * Display types (controls how the tier is presented on the product page):
 *   - 'offer_quantity': "Buy N+ of this offer" — quantity threshold per single offer
 *   - 'total_quantity': "Buy N+ total" — quantity threshold across all offers combined
 *   - 'position_count': "N+ different products in cart" — count of unique positions
 *   - 'target_price': "Buy N+ for {price}" — shows the final unit price directly
 *   - 'unconditional': No quantity condition — flat discount
 *   - 'custom': Third-party display — CampaignPricingItem passes through raw values
 */
class TierResolverRegistry
{
    public const EVENT_REGISTER_RESOLVERS = 'campaignpricing.tier.register_resolvers';

    /**
     * Known quantity property names in order of specificity.
     * The first non-zero match wins when auto-detecting.
     * @var list<string>
     */
    protected static $arQuantityPropertyPriorityList = [
        'offer_limit',
        'quantity_limit',
        'position_limit',
    ];

    /** @var array<string, array{quantity_property: string, price_type: string, display_type: string}>|null Cached resolver map */
    protected static $arResolverMap;

    /**
     * Get the full resolver map: mechanism class => resolver config.
     * Built once per request, then cached.
     * @return array<string, array{quantity_property: string, price_type: string, display_type: string}>
     */
    public static function getResolverMap(): array
    {
        if (static::$arResolverMap !== null) {
            return static::$arResolverMap;
        }

        static::$arResolverMap = static::buildResolverMap();

        return static::$arResolverMap;
    }

    /**
     * Get resolver config for a specific mechanism type.
     *
     * @param string $sMechanismType Full class name
     * @return array{quantity_property: string, price_type: string, display_type: string}|null Resolver config or null if not supported
     */
    public static function getResolver(string $sMechanismType): ?array
    {
        $arMap = static::getResolverMap();

        return $arMap[$sMechanismType] ?? null;
    }

    /**
     * Check if a mechanism type is supported for tier display.
     *
     * @param string $sMechanismType Full class name
     */
    public static function isSupported(string $sMechanismType): bool
    {
        return static::getResolver($sMechanismType) !== null;
    }

    /**
     * Extract quantity threshold from mechanism properties using the resolver config.
     *
     * @param array<string, mixed> $arProperty Mechanism property JSON (decoded)
     * @param array{quantity_property: string, price_type: string, display_type: string} $arResolver Resolver config from getResolver()
     */
    public static function extractQuantity(array $arProperty, array $arResolver): int
    {
        $sQuantityProperty = $arResolver['quantity_property'];

        if ($sQuantityProperty !== '') {
            $mValue = array_get($arProperty, $sQuantityProperty, 0);

            return is_numeric($mValue) ? (int) $mValue : 0;
        }

        // Auto-detect: try known property names in priority order
        return static::autoDetectQuantity($arProperty);
    }

    /**
     * Check if a mechanism has shipping or payment constraints.
     * We skip these because campaign pricing display is pre-cart context.
     *
     * @param array<string, mixed> $arProperty Mechanism property JSON (decoded)
     * @return bool True if mechanism has shipping/payment constraints
     */
    public static function hasShippingOrPaymentConstraint(array $arProperty): bool
    {
        $arShippingTypeIdList = array_get($arProperty, 'shipping_type_id', []);
        $arPaymentMethodIdList = array_get($arProperty, 'payment_method_id', []);

        if (!empty($arShippingTypeIdList) && is_array($arShippingTypeIdList) && array_filter($arShippingTypeIdList)) {
            return true;
        }
        return !empty($arPaymentMethodIdList) && is_array($arPaymentMethodIdList) && array_filter($arPaymentMethodIdList);
    }

    /**
     * Build the resolver map by combining built-in resolvers with event-registered ones.
     * @return array<string, array{quantity_property: string, price_type: string, display_type: string}>
     */
    protected static function buildResolverMap(): array
    {
        $arMap = static::getBuiltInResolverMap();

        // Allow third-party plugins to register custom resolvers
        $arEventResults = Event::fire(self::EVENT_REGISTER_RESOLVERS);
        if (!empty($arEventResults)) {
            foreach ($arEventResults as $arResult) {
                if (is_array($arResult)) {
                    /** @var array<string, array{quantity_property: string, price_type: string, display_type: string}> $arResult */
                    $arMap = array_merge($arMap, $arResult);
                }
            }
        }

        return $arMap;
    }

    /**
     * Built-in resolver map for all standard Lovata position-level mechanisms.
     * @return array<string, array{quantity_property: string, price_type: string, display_type: string}>
     */
    protected static function getBuiltInResolverMap(): array
    {
        return [
            // OfferQuantityGreater: discount if total quantity of one offer >= N
            \Lovata\OrdersShopaholic\Classes\PromoMechanism\OfferQuantityGreater\OfferQuantityGreaterDiscountPosition::class => [
                'quantity_property' => 'offer_limit',
                'price_type'        => 'discount',
                'display_type'      => 'offer_quantity',
            ],
            \Lovata\OrdersShopaholic\Classes\PromoMechanism\OfferQuantityGreater\OfferQuantityGreaterDiscountMinPrice::class => [
                'quantity_property' => 'offer_limit',
                'price_type'        => 'discount',
                'display_type'      => 'offer_quantity',
            ],

            // OfferTotalQuantityGreater: discount if total quantity of all offers combined >= N
            \Lovata\OrdersShopaholic\Classes\PromoMechanism\OfferTotalQuantityGreater\OfferTotalQuantityGreaterDiscountPosition::class => [
                'quantity_property' => 'offer_limit',
                'price_type'        => 'discount',
                'display_type'      => 'total_quantity',
            ],
            \Lovata\OrdersShopaholic\Classes\PromoMechanism\OfferTotalQuantityGreater\OfferTotalQuantityGreaterDiscountMinPrice::class => [
                'quantity_property' => 'offer_limit',
                'price_type'        => 'discount',
                'display_type'      => 'total_quantity',
            ],

            // PositionCountGreater: discount if position count in order >= N
            \Lovata\OrdersShopaholic\Classes\PromoMechanism\PositionCountGreater\PositionCountGreaterDiscountPosition::class => [
                'quantity_property' => 'position_limit',
                'price_type'        => 'discount',
                'display_type'      => 'position_count',
            ],
            \Lovata\OrdersShopaholic\Classes\PromoMechanism\PositionCountGreater\PositionCountGreaterDiscountMinPrice::class => [
                'quantity_property' => 'position_limit',
                'price_type'        => 'discount',
                'display_type'      => 'position_count',
            ],

            // WithoutCondition: discount without checking any conditions
            \Lovata\OrdersShopaholic\Classes\PromoMechanism\WithoutCondition\WithoutConditionDiscountPosition::class => [
                'quantity_property' => 'quantity_limit',
                'price_type'        => 'discount',
                'display_type'      => 'unconditional',
            ],
            \Lovata\OrdersShopaholic\Classes\PromoMechanism\WithoutCondition\WithoutConditionDiscountMinPrice::class => [
                'quantity_property' => 'quantity_limit',
                'price_type'        => 'discount',
                'display_type'      => 'unconditional',
            ],

            // SpecificPriceByQuantity (custom): exact target unit price when quantity >= N
            \Logingrupa\ExtendPromoMechanism\Classes\PromoMechanism\SpecificPriceByQuantity\SpecificPriceByQuantityDiscountPosition::class => [
                'quantity_property' => 'quantity_limit',
                'price_type'        => 'target_price',
                'display_type'      => 'target_price',
            ],
        ];
    }

    /**
     * Auto-detect quantity from property array by trying known property names.
     * @param array<string, mixed> $arProperty
     */
    protected static function autoDetectQuantity(array $arProperty): int
    {
        foreach (static::$arQuantityPropertyPriorityList as $sProperty) {
            $mValue = array_get($arProperty, $sProperty, 0);
            $iValue = is_numeric($mValue) ? (int) $mValue : 0;
            if ($iValue > 0) {
                return $iValue;
            }
        }

        return 0;
    }

    /**
     * Reset cached resolver map (useful for testing).
     */
    public static function resetCache(): void
    {
        static::$arResolverMap = null;
    }
}
