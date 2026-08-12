<?php

use Logingrupa\CampaignpricingShopaholic\Classes\Item\CampaignPricingItem;
use Lovata\Shopaholic\Classes\Helper\CurrencyHelper;

beforeEach(function () {
    // Mock CurrencyHelper singleton — convert() returns price as-is (1:1 EUR)
    $obMock = Mockery::mock(CurrencyHelper::class);
    $obMock->shouldReceive('convert')->andReturnUsing(fn ($fPrice) => $fPrice);
    $obMock->shouldReceive('getActiveCurrencyCode')->andReturn('EUR');

    app()->instance(CurrencyHelper::class, $obMock);
    CurrencyHelper::forgetInstance();
    // Replace singleton instance via reflection
    $obReflection = new ReflectionClass(CurrencyHelper::class);
    $obProperty = $obReflection->getProperty('instance');
    $obProperty->setValue(null, $obMock);
});

afterEach(function () {
    CurrencyHelper::forgetInstance();
});

test('custom template overrides default (EUR)', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 10,
        'discount_value'   => 100.0,
        'discount_type'    => 'fixed',
        'mechanism_type'   => 'TestMechanism',
        'mechanism_name'   => 'TestMechanism',
        'price_type'       => 'target_price',
        'display_type'     => 'target_price',
        'display_template' => 'Buy :quantity+ for only :price!',
        'offer_base_price' => 132.0,
        'offer_currency'   => 'EUR',
        'campaign_name'    => 'Test Campaign',
    ]);

    $sText = $obTier->display_text;

    expect($sText)->toContain('Buy 10+');
    expect($sText)->toContain('!');
});

test('all variables replaced in custom template (EUR)', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 5,
        'discount_value'   => 15.0,
        'discount_type'    => 'percent',
        'mechanism_type'   => 'TestMechanism',
        'mechanism_name'   => 'TestMechanism',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'display_template' => ':quantity :discount :discount_type :campaign_name',
        'offer_base_price' => 200.0,
        'offer_currency'   => 'EUR',
        'campaign_name'    => 'Summer Sale',
    ]);

    $sText = $obTier->display_text;

    expect($sText)->toContain('5');
    expect($sText)->toContain('15%');
    expect($sText)->toContain('percent');
    expect($sText)->toContain('Summer Sale');
});

test('custom template html is rendered verbatim', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 10,
        'discount_value'   => 10.0,
        'discount_type'    => 'fixed',
        'mechanism_type'   => 'TestMechanism',
        'mechanism_name'   => 'TestMechanism',
        'price_type'       => 'target_price',
        'display_type'     => 'target_price',
        'display_template' => '<strong>:price/pc.</strong> – when buying <span>:quantity+</span> pcs<br>',
        'offer_base_price' => 50.0,
        'offer_currency'   => 'EUR',
        'campaign_name'    => 'Test',
    ]);

    $sText = $obTier->display_text;

    expect($sText)->toContain('<strong>');
    expect($sText)->toContain('<span>10+</span>');
    expect($sText)->toContain('<br>');
});

test('empty template falls back to lang key', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 10,
        'discount_value'   => 100.0,
        'discount_type'    => 'fixed',
        'mechanism_type'   => 'TestMechanism',
        'mechanism_name'   => 'TestMechanism',
        'price_type'       => 'target_price',
        'display_type'     => 'target_price',
        'display_template' => '',
        'offer_base_price' => 132.0,
        'offer_currency'   => 'EUR',
        'campaign_name'    => 'Test',
    ]);

    $sText = $obTier->display_text;

    // With no custom template, falls back to Lang::get() with display_type key
    // In test env, lang files are loaded so we get translated text or raw key
    expect($sText)->not->toBeEmpty();
    expect(
        str_contains($sText, '10') || str_contains($sText, 'lang.display.')
    )->toBeTrue();
});

test('target price with NOK currency', function () {
    // Override mock with NOK conversion rate (1 EUR = 11.5 NOK)
    $obMock = Mockery::mock(CurrencyHelper::class);
    $obMock->shouldReceive('convert')->andReturnUsing(fn ($fPrice) => $fPrice * 11.5);

    $obReflection = new ReflectionClass(CurrencyHelper::class);
    $obProperty = $obReflection->getProperty('instance');
    $obProperty->setValue(null, $obMock);

    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 3,
        'discount_value'   => 10.0,
        'discount_type'    => 'fixed',
        'mechanism_type'   => 'TestMechanism',
        'mechanism_name'   => 'TestMechanism',
        'price_type'       => 'target_price',
        'display_type'     => 'target_price',
        'display_template' => 'Buy :quantity+ for :price',
        'offer_base_price' => 50.0,
        'offer_currency'   => 'NOK',
        'campaign_name'    => 'NOK Campaign',
    ]);

    // 10.0 EUR * 11.5 = 115.0 NOK
    expect($obTier->price_value)->toBe(115.0);
});

test('percent discount display', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'quantity'         => 3,
        'discount_value'   => 15.0,
        'discount_type'    => 'percent',
        'mechanism_type'   => 'Test',
        'mechanism_name'   => 'Test',
        'price_type'       => 'discount',
        'display_type'     => 'offer_quantity',
        'display_template' => '',
        'offer_base_price' => 100.0,
        'offer_currency'   => 'EUR',
        'campaign_name'    => 'Test',
    ]);

    expect($obTier->discount_display)->toBe('15%');
});

test('display type accessor', function () {
    $obTier = CampaignPricingItem::makeFromData([
        'display_type' => 'position_count',
        'quantity' => 1,
        'discount_value' => 0,
        'discount_type' => 'fixed',
        'price_type' => 'discount',
        'offer_base_price' => 0,
    ]);

    expect($obTier->display_type)->toBe('position_count');
});
