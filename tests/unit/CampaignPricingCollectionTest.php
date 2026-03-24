<?php

use Logingrupa\CampaignpricingShopaholic\Classes\Collection\CampaignPricingCollection;
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

test('makeFromTierDataList creates collection', function () {
    $arTierList = [
        [
            'quantity'         => 3,
            'discount_value'   => 10.0,
            'discount_type'    => 'percent',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
        ],
        [
            'quantity'         => 5,
            'discount_value'   => 20.0,
            'discount_type'    => 'percent',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
        ],
    ];

    $obCollection = CampaignPricingCollection::makeFromTierDataList($arTierList);

    expect($obCollection)->toBeInstanceOf(CampaignPricingCollection::class);
    expect($obCollection->isEmpty())->toBeFalse();
    expect($obCollection->count())->toBe(2);
});

test('empty tier list creates empty collection', function () {
    $obCollection = CampaignPricingCollection::makeFromTierDataList([]);

    expect($obCollection->isEmpty())->toBeTrue();
    expect($obCollection->count())->toBe(0);
});

test('iteration yields CampaignPricingItem instances', function () {
    $arTierList = [
        [
            'quantity'         => 3,
            'discount_value'   => 10.0,
            'discount_type'    => 'percent',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
        ],
    ];

    $obCollection = CampaignPricingCollection::makeFromTierDataList($arTierList);
    $arItems = iterator_to_array($obCollection->getIterator());

    expect($arItems)->toHaveCount(1);
    expect($arItems[0])->toBeInstanceOf(CampaignPricingItem::class);
    expect($arItems[0]->quantity)->toBe(3);
    expect($arItems[0]->price_value)->toBe(90.0);
});

test('empty collection iterates without error', function () {
    $obCollection = CampaignPricingCollection::makeFromTierDataList([]);
    $arItems = iterator_to_array($obCollection->getIterator());

    expect($arItems)->toBeEmpty();
});

test('multiple tiers preserve order from input', function () {
    $arTierList = [
        [
            'quantity'         => 10,
            'discount_value'   => 30.0,
            'discount_type'    => 'percent',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
        ],
        [
            'quantity'         => 3,
            'discount_value'   => 10.0,
            'discount_type'    => 'percent',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
        ],
    ];

    $obCollection = CampaignPricingCollection::makeFromTierDataList($arTierList);
    $arItems = iterator_to_array($obCollection->getIterator());

    // Collection preserves input order (store sorts before passing)
    expect($arItems[0]->quantity)->toBe(10);
    expect($arItems[1]->quantity)->toBe(3);
});
