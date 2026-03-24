<?php

use Logingrupa\CampaignpricingShopaholic\Classes\Event\OfferItemExtendHandler;
use Lovata\Shopaholic\Classes\Helper\CurrencyHelper;

beforeEach(function () {
    $obMock = Mockery::mock(CurrencyHelper::class);
    $obMock->shouldReceive('convert')->andReturnUsing(fn ($fPrice) => $fPrice);
    $obMock->shouldReceive('getActiveCurrencyCode')->andReturn('EUR');

    CurrencyHelper::forgetInstance();
    $obReflection = new ReflectionClass(CurrencyHelper::class);
    $obProperty = $obReflection->getProperty('instance');
    $obProperty->setValue(null, $obMock);

    // Make mergeTierList accessible via reflection
    $this->obHandler = new OfferItemExtendHandler();
    $this->obMergeMethod = new ReflectionMethod(OfferItemExtendHandler::class, 'mergeTierList');
});

afterEach(function () {
    CurrencyHelper::forgetInstance();
});

/**
 * Helper to call protected mergeTierList
 */
function callMergeTierList(object $obHandler, ReflectionMethod $obMethod, array $arTierList, float $fBasePrice): array
{
    return $obMethod->invoke($obHandler, $arTierList, $fBasePrice);
}

test('single tier passes through unchanged', function () {
    $arTierList = [
        [
            'quantity'         => 5,
            'discount_value'   => 10.0,
            'discount_type'    => 'fixed',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
        ],
    ];

    $arResult = callMergeTierList($this->obHandler, $this->obMergeMethod, $arTierList, 100.0);

    expect($arResult)->toHaveCount(1);
    expect($arResult[0]['quantity'])->toBe(5);
    expect($arResult[0]['discount_value'])->toBe(10.0);
});

test('different quantities are preserved', function () {
    $arTierList = [
        [
            'quantity'         => 3,
            'discount_value'   => 5.0,
            'discount_type'    => 'fixed',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
        ],
        [
            'quantity'         => 10,
            'discount_value'   => 20.0,
            'discount_type'    => 'fixed',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
        ],
    ];

    $arResult = callMergeTierList($this->obHandler, $this->obMergeMethod, $arTierList, 100.0);

    expect($arResult)->toHaveCount(2);
});

test('duplicate quantities keep lowest price', function () {
    $arTierList = [
        [
            'quantity'         => 5,
            'discount_value'   => 10.0, // price_value = 100 - 10 = 90
            'discount_type'    => 'fixed',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
            'campaign_name'    => 'Worse deal',
        ],
        [
            'quantity'         => 5,
            'discount_value'   => 30.0, // price_value = 100 - 30 = 70 (better)
            'discount_type'    => 'fixed',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
            'campaign_name'    => 'Better deal',
        ],
    ];

    $arResult = callMergeTierList($this->obHandler, $this->obMergeMethod, $arTierList, 100.0);

    expect($arResult)->toHaveCount(1);
    expect($arResult[0]['discount_value'])->toBe(30.0);
    expect($arResult[0]['campaign_name'])->toBe('Better deal');
});

test('three tiers at same quantity keep best', function () {
    $arTierList = [
        [
            'quantity'         => 5,
            'discount_value'   => 10.0, // price = 90
            'discount_type'    => 'fixed',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
            'campaign_name'    => 'A',
        ],
        [
            'quantity'         => 5,
            'discount_value'   => 50.0, // price = 50 (best)
            'discount_type'    => 'fixed',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
            'campaign_name'    => 'B',
        ],
        [
            'quantity'         => 5,
            'discount_value'   => 25.0, // price = 75
            'discount_type'    => 'fixed',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
            'campaign_name'    => 'C',
        ],
    ];

    $arResult = callMergeTierList($this->obHandler, $this->obMergeMethod, $arTierList, 100.0);

    expect($arResult)->toHaveCount(1);
    expect($arResult[0]['campaign_name'])->toBe('B');
});

test('result is sorted by quantity ascending', function () {
    $arTierList = [
        [
            'quantity'         => 20,
            'discount_value'   => 30.0,
            'discount_type'    => 'fixed',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
        ],
        [
            'quantity'         => 3,
            'discount_value'   => 5.0,
            'discount_type'    => 'fixed',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
        ],
        [
            'quantity'         => 10,
            'discount_value'   => 15.0,
            'discount_type'    => 'fixed',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
        ],
    ];

    $arResult = callMergeTierList($this->obHandler, $this->obMergeMethod, $arTierList, 100.0);

    expect($arResult[0]['quantity'])->toBe(3);
    expect($arResult[1]['quantity'])->toBe(10);
    expect($arResult[2]['quantity'])->toBe(20);
});

test('empty tier list returns empty', function () {
    $arResult = callMergeTierList($this->obHandler, $this->obMergeMethod, [], 100.0);

    expect($arResult)->toBeEmpty();
});

test('dedup works across price types', function () {
    // target_price tier: discount_value IS the price = 60
    // discount tier: 100 - 30 = 70
    // target_price wins (lower price)
    $arTierList = [
        [
            'quantity'         => 5,
            'discount_value'   => 30.0, // price = 100 - 30 = 70
            'discount_type'    => 'fixed',
            'price_type'       => 'discount',
            'display_type'     => 'offer_quantity',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
            'campaign_name'    => 'Discount campaign',
        ],
        [
            'quantity'         => 5,
            'discount_value'   => 60.0, // price = 60 (target_price)
            'discount_type'    => 'fixed',
            'price_type'       => 'target_price',
            'display_type'     => 'target_price',
            'offer_base_price' => 100.0,
            'offer_currency'   => 'EUR',
            'campaign_name'    => 'Target price campaign',
        ],
    ];

    $arResult = callMergeTierList($this->obHandler, $this->obMergeMethod, $arTierList, 100.0);

    expect($arResult)->toHaveCount(1);
    expect($arResult[0]['campaign_name'])->toBe('Target price campaign');
    expect($arResult[0]['price_type'])->toBe('target_price');
});
