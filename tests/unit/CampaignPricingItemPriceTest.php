<?php

use Logingrupa\CampaignpricingShopaholic\Classes\Item\CampaignPricingItem;
use Lovata\Shopaholic\Classes\Helper\CurrencyHelper;

beforeEach(function () {
    // Mock CurrencyHelper singleton — convert() returns price as-is (1:1 EUR)
    $obMock = Mockery::mock(CurrencyHelper::class);
    $obMock->shouldReceive('convert')->andReturnUsing(fn ($fPrice) => $fPrice);
    $obMock->shouldReceive('getActiveCurrencyCode')->andReturn('EUR');

    CurrencyHelper::forgetInstance();
    $obReflection = new ReflectionClass(CurrencyHelper::class);
    $obProperty = $obReflection->getProperty('instance');
    $obProperty->setValue(null, $obMock);
});

afterEach(function () {
    CurrencyHelper::forgetInstance();
});

// --- price_value computation ---

test('target price uses discount_value directly as price', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 10,
        'discount_value'   => 49.90,
        'discount_type'    => 'fixed',
        'price_type'       => 'target_price',
        'display_type'     => 'target_price',
        'offer_base_price' => 120.0,
        'offer_currency'   => 'EUR',
    ]);

    // target_price: discount_value IS the final price, base_price ignored
    expect($obTier->price_value)->toBe(49.90);
});

test('fixed discount subtracts from base price', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 5,
        'discount_value'   => 20.0,
        'discount_type'    => 'fixed',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'offer_base_price' => 100.0,
        'offer_currency'   => 'EUR',
    ]);

    // 100 - 20 = 80
    expect($obTier->price_value)->toBe(80.0);
});

test('percent discount computes percentage off base price', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 3,
        'discount_value'   => 25.0,
        'discount_type'    => 'percent',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'offer_base_price' => 200.0,
        'offer_currency'   => 'EUR',
    ]);

    // 200 * (1 - 25/100) = 150
    expect($obTier->price_value)->toBe(150.0);
});

test('fixed discount larger than base price floors at zero', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 1,
        'discount_value'   => 999.0,
        'discount_type'    => 'fixed',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'offer_base_price' => 50.0,
        'offer_currency'   => 'EUR',
    ]);

    // max(0, 50 - 999) = 0
    expect($obTier->price_value)->toBe(0.0);
});

test('100 percent discount results in zero price', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 2,
        'discount_value'   => 100.0,
        'discount_type'    => 'percent',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'offer_base_price' => 75.50,
        'offer_currency'   => 'EUR',
    ]);

    expect($obTier->price_value)->toBe(0.0);
});

test('currency conversion applied to target price', function () {
    // Override mock with NOK rate
    $obMock = Mockery::mock(CurrencyHelper::class);
    $obMock->shouldReceive('convert')->andReturnUsing(fn ($fPrice) => $fPrice * 11.5);

    $obReflection = new ReflectionClass(CurrencyHelper::class);
    $obProperty = $obReflection->getProperty('instance');
    $obProperty->setValue(null, $obMock);

    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 3,
        'discount_value'   => 10.0,
        'discount_type'    => 'fixed',
        'price_type'       => 'target_price',
        'display_type'     => 'target_price',
        'offer_base_price' => 50.0,
        'offer_currency'   => 'NOK',
    ]);

    // 10.0 EUR * 11.5 = 115.0 NOK
    expect($obTier->price_value)->toBe(115.0);
});

test('currency conversion applied to discount path', function () {
    $obMock = Mockery::mock(CurrencyHelper::class);
    $obMock->shouldReceive('convert')->andReturnUsing(fn ($fPrice) => $fPrice * 2.0);

    $obReflection = new ReflectionClass(CurrencyHelper::class);
    $obProperty = $obReflection->getProperty('instance');
    $obProperty->setValue(null, $obMock);

    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 5,
        'discount_value'   => 10.0,
        'discount_type'    => 'fixed',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'offer_base_price' => 100.0,
        'offer_currency'   => 'EUR',
    ]);

    // (100 - 10) * 2.0 = 180.0
    expect($obTier->price_value)->toBe(180.0);
});

// --- discount_display ---

test('fixed discount display formats as currency', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 5,
        'discount_value'   => 20.0,
        'discount_type'    => 'fixed',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'offer_base_price' => 100.0,
        'offer_currency'   => 'EUR',
    ]);

    $sDisplay = $obTier->discount_display;

    // Fixed discount is currency-formatted, should contain the value
    expect($sDisplay)->toContain('20');
    expect($sDisplay)->not->toContain('%');
});

test('percent discount display shows percentage', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 3,
        'discount_value'   => 15.0,
        'discount_type'    => 'percent',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'offer_base_price' => 100.0,
        'offer_currency'   => 'EUR',
    ]);

    expect($obTier->discount_display)->toBe('15%');
});

// --- basic accessors ---

test('quantity accessor returns integer', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity' => '7',
        'discount_value' => 0,
        'discount_type' => 'fixed',
        'price_type' => 'discount',
        'offer_base_price' => 0,
    ]);

    expect($obTier->quantity)->toBe(7);
    expect($obTier->quantity)->toBeInt();
});

test('mechanism name accessor', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'mechanism_name' => 'OfferQuantityGreaterDiscountPosition',
        'quantity' => 1,
        'discount_value' => 0,
        'discount_type' => 'fixed',
        'price_type' => 'discount',
        'offer_base_price' => 0,
    ]);

    expect($obTier->mechanism_name)->toBe('OfferQuantityGreaterDiscountPosition');
});

test('discount value accessor returns float', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'discount_value' => '12.5',
        'quantity' => 1,
        'discount_type' => 'percent',
        'price_type' => 'discount',
        'offer_base_price' => 0,
    ]);

    expect($obTier->discount_value)->toBe(12.5);
    expect($obTier->discount_value)->toBeFloat();
});

test('discount type accessor returns string', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'discount_type' => 'percent',
        'quantity' => 1,
        'discount_value' => 0,
        'price_type' => 'discount',
        'offer_base_price' => 0,
    ]);

    expect($obTier->discount_type)->toBe('percent');
});
