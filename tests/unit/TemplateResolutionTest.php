<?php

use Logingrupa\CampaignpricingShopaholic\Classes\Item\CampaignPricingItem;
use Lovata\Shopaholic\Classes\Helper\CurrencyHelper;

beforeEach(function () {
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

test('discount_type not mangled by discount replacement', function () {
    // Regression: :discount must not replace inside :discount_type or :discount_value
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 5,
        'discount_value'   => 15.0,
        'discount_type'    => 'percent',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'display_template' => ':discount_type discount of :discount for :quantity+ pcs',
        'offer_base_price' => 200.0,
        'offer_currency'   => 'EUR',
        'campaign_name'    => 'Test',
    ]);

    $sText = $obTier->display_text;

    expect($sText)->toContain('percent discount of 15%');
    expect($sText)->not->toContain('15%_type');
});

test('discount_value not mangled by discount replacement', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 3,
        'discount_value'   => 20.0,
        'discount_type'    => 'fixed',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'display_template' => 'Save :discount_value (:discount)',
        'offer_base_price' => 100.0,
        'offer_currency'   => 'EUR',
        'campaign_name'    => 'Test',
    ]);

    $sText = $obTier->display_text;

    expect($sText)->toContain('Save 20');
    // :discount_value should be replaced cleanly, not as ":discount" + "_value"
    expect($sText)->not->toContain('_value');
});

test('all placeholders replaced in complex template', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 10,
        'discount_value'   => 25.0,
        'discount_type'    => 'percent',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'display_template' => ':campaign_name: :quantity+ pcs at :price (:discount off, type: :discount_type, raw: :discount_value)',
        'offer_base_price' => 200.0,
        'offer_currency'   => 'EUR',
        'campaign_name'    => 'Summer Sale',
    ]);

    $sText = $obTier->display_text;

    expect($sText)->not->toContain(':quantity');
    expect($sText)->not->toContain(':price');
    expect($sText)->not->toContain(':campaign_name');
    expect($sText)->not->toContain(':discount_type');
    expect($sText)->not->toContain(':discount_value');
    // :discount should be replaced too, but check no leftover colons for our vars
    expect($sText)->toContain('Summer Sale');
    expect($sText)->toContain('10');
    expect($sText)->toContain('percent');
    expect($sText)->toContain('25');
});

test('template with no placeholders returns as-is', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 5,
        'discount_value'   => 10.0,
        'discount_type'    => 'fixed',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'display_template' => 'Special price available!',
        'offer_base_price' => 100.0,
        'offer_currency'   => 'EUR',
        'campaign_name'    => 'Test',
    ]);

    expect($obTier->display_text)->toBe('Special price available!');
});

test('count alias works same as quantity', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 7,
        'discount_value'   => 10.0,
        'discount_type'    => 'percent',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'display_template' => ':count items',
        'offer_base_price' => 100.0,
        'offer_currency'   => 'EUR',
        'campaign_name'    => 'Test',
    ]);

    expect($obTier->display_text)->toBe('7 items');
});

test('html in template is preserved', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 5,
        'discount_value'   => 15.0,
        'discount_type'    => 'percent',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'display_template' => '<strong>:quantity+</strong> pcs: <em>:discount</em> off',
        'offer_base_price' => 100.0,
        'offer_currency'   => 'EUR',
        'campaign_name'    => 'Test',
    ]);

    $sText = $obTier->display_text;

    expect($sText)->toContain('<strong>5+</strong>');
    expect($sText)->toContain('<em>15%</em>');
});
