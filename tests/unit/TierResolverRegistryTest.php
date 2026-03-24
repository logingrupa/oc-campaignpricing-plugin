<?php

use Logingrupa\CampaignpricingShopaholic\Classes\Tier\TierResolverRegistry;

beforeEach(function () {
    TierResolverRegistry::resetCache();
});

afterEach(function () {
    TierResolverRegistry::resetCache();
});

test('built in resolvers are registered', function () {
    $arMap = TierResolverRegistry::getResolverMap();

    expect($arMap)->not->toBeEmpty('Resolver map should not be empty');
    expect(count($arMap))->toBeGreaterThanOrEqual(9, 'Should have at least 9 built-in resolvers');
});

test('built in resolvers have required keys', function () {
    $arMap = TierResolverRegistry::getResolverMap();

    foreach ($arMap as $sClass => $arConfig) {
        expect($arConfig)->toHaveKey('quantity_property');
        expect($arConfig)->toHaveKey('price_type');
        expect($arConfig)->toHaveKey('display_type');
        expect($arConfig['price_type'])->toBeIn(['discount', 'target_price']);
    }
});

test('specific price by quantity is target price', function () {
    $sClass = \Logingrupa\ExtendPromoMechanism\Classes\PromoMechanism\SpecificPriceByQuantity\SpecificPriceByQuantityDiscountPosition::class;
    $arResolver = TierResolverRegistry::getResolver($sClass);

    expect($arResolver)->not->toBeNull('SpecificPriceByQuantity should be registered');
    expect($arResolver['price_type'])->toBe('target_price');
    expect($arResolver['quantity_property'])->toBe('quantity_limit');
    expect($arResolver['display_type'])->toBe('target_price');
});

test('offer quantity greater is discount', function () {
    $sClass = \Lovata\OrdersShopaholic\Classes\PromoMechanism\OfferQuantityGreater\OfferQuantityGreaterDiscountPosition::class;
    $arResolver = TierResolverRegistry::getResolver($sClass);

    expect($arResolver)->not->toBeNull('OfferQuantityGreater should be registered');
    expect($arResolver['price_type'])->toBe('discount');
    expect($arResolver['quantity_property'])->toBe('offer_limit');
    expect($arResolver['display_type'])->toBe('offer_quantity');
});

test('unknown mechanism returns null', function () {
    $arResolver = TierResolverRegistry::getResolver('Some\Nonexistent\MechanismClass');

    expect($arResolver)->toBeNull();
    expect(TierResolverRegistry::isSupported('Some\Nonexistent\MechanismClass'))->toBeFalse();
});

test('event registers custom resolver', function () {
    $sCustomClass = 'Acme\CustomPlugin\Classes\PromoMechanism\BuyOneGetOneDiscount';

    Event::listen(TierResolverRegistry::EVENT_REGISTER_RESOLVERS, function () use ($sCustomClass) {
        return [
            $sCustomClass => [
                'quantity_property' => 'bogo_threshold',
                'price_type'        => 'discount',
                'display_type'      => 'custom',
            ],
        ];
    });

    TierResolverRegistry::resetCache();
    $arResolver = TierResolverRegistry::getResolver($sCustomClass);

    expect($arResolver)->not->toBeNull('Custom mechanism should be registered via event');
    expect($arResolver['quantity_property'])->toBe('bogo_threshold');
    expect($arResolver['price_type'])->toBe('discount');
    expect($arResolver['display_type'])->toBe('custom');
    expect(TierResolverRegistry::isSupported($sCustomClass))->toBeTrue();
});

test('multiple event listeners stack resolvers', function () {
    $sClassA = 'PluginA\PromoMechanism\DiscountA';
    $sClassB = 'PluginB\PromoMechanism\DiscountB';

    Event::listen(TierResolverRegistry::EVENT_REGISTER_RESOLVERS, function () use ($sClassA) {
        return [
            $sClassA => [
                'quantity_property' => 'min_qty_a',
                'price_type'        => 'discount',
                'display_type'      => 'offer_quantity',
            ],
        ];
    });

    Event::listen(TierResolverRegistry::EVENT_REGISTER_RESOLVERS, function () use ($sClassB) {
        return [
            $sClassB => [
                'quantity_property' => 'min_qty_b',
                'price_type'        => 'target_price',
                'display_type'      => 'target_price',
            ],
        ];
    });

    TierResolverRegistry::resetCache();
    $arMap = TierResolverRegistry::getResolverMap();

    expect($arMap)->toHaveKey($sClassA);
    expect($arMap)->toHaveKey($sClassB);
    expect($arMap[$sClassA]['quantity_property'])->toBe('min_qty_a');
    expect($arMap[$sClassB]['price_type'])->toBe('target_price');
});

test('event can override built in resolver', function () {
    $sBuiltInClass = \Lovata\OrdersShopaholic\Classes\PromoMechanism\OfferQuantityGreater\OfferQuantityGreaterDiscountPosition::class;

    // Verify built-in default
    $arOriginal = TierResolverRegistry::getResolver($sBuiltInClass);
    expect($arOriginal['quantity_property'])->toBe('offer_limit');

    // Override via event
    Event::listen(TierResolverRegistry::EVENT_REGISTER_RESOLVERS, function () use ($sBuiltInClass) {
        return [
            $sBuiltInClass => [
                'quantity_property' => 'custom_limit',
                'price_type'        => 'target_price',
                'display_type'      => 'custom',
            ],
        ];
    });

    TierResolverRegistry::resetCache();
    $arOverridden = TierResolverRegistry::getResolver($sBuiltInClass);

    expect($arOverridden['quantity_property'])->toBe('custom_limit', 'Event should override built-in resolver');
    expect($arOverridden['price_type'])->toBe('target_price');
});

test('extract quantity uses resolver property', function () {
    $arProperty = ['offer_limit' => 5, 'quantity_limit' => 10];
    $arResolver = ['quantity_property' => 'offer_limit'];

    expect(TierResolverRegistry::extractQuantity($arProperty, $arResolver))->toBe(5);
});

test('extract quantity auto detects', function () {
    $arProperty = ['quantity_limit' => 10];
    $arResolver = ['quantity_property' => ''];

    expect(TierResolverRegistry::extractQuantity($arProperty, $arResolver))->toBe(10);
});

test('shipping constraint detected', function () {
    expect(TierResolverRegistry::hasShippingOrPaymentConstraint([
        'shipping_type_id' => [1, 2],
    ]))->toBeTrue();

    expect(TierResolverRegistry::hasShippingOrPaymentConstraint([
        'shipping_type_id' => [],
    ]))->toBeFalse();

    expect(TierResolverRegistry::hasShippingOrPaymentConstraint([]))->toBeFalse();
});

test('payment constraint detected', function () {
    expect(TierResolverRegistry::hasShippingOrPaymentConstraint([
        'payment_method_id' => [3],
    ]))->toBeTrue();

    expect(TierResolverRegistry::hasShippingOrPaymentConstraint([
        'payment_method_id' => [],
    ]))->toBeFalse();
});

test('campaign pricing enabled flag', function () {
    // Explicitly disabled
    $arDisabled = ['campaign_pricing_enabled' => '0'];
    expect(array_get($arDisabled, 'campaign_pricing_enabled'))->toBe('0');

    // Enabled (default when key missing)
    $arDefault = [];
    expect(array_get($arDefault, 'campaign_pricing_enabled'))->toBeNull();

    // Explicitly enabled
    $arEnabled = ['campaign_pricing_enabled' => '1'];
    expect(array_get($arEnabled, 'campaign_pricing_enabled'))->toBe('1');
});
