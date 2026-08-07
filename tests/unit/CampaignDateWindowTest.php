<?php

use Carbon\Carbon;
use Logingrupa\CampaignpricingShopaholic\Classes\Store\OfferCampaignPricingStore;

/**
 * The tier list is held with CCache::forever and nothing fires when a campaign's
 * date_end simply passes, so the date window cannot be decided when the list is
 * built. These pin the read-time filter that replaces currentActive() inside the
 * store, including the case that covers 17 of the 39 campaigns: no end date.
 */

/**
 * Call the store's protected static filter.
 * @param array<int, array<string, mixed>> $arTierList
 * @return array<int, array<string, mixed>>
 */
function filterTierListByDateWindow(array $arTierList): array
{
    $obMethod = new ReflectionMethod(OfferCampaignPricingStore::class, 'filterByDateWindow');

    /** @var array<int, array<string, mixed>> $arResult */
    $arResult = $obMethod->invoke(null, $arTierList);

    return $arResult;
}

/**
 * @param string|null $sDateBegin
 * @param string|null $sDateEnd
 * @return array<string, mixed>
 */
function makeDatedTier($sDateBegin, $sDateEnd, int $iCampaignId = 1): array
{
    return [
        'campaign_id' => $iCampaignId,
        'quantity'    => 3,
        'date_begin'  => $sDateBegin,
        'date_end'    => $sDateEnd,
    ];
}

test('a running campaign is kept', function () {
    $arTierList = [makeDatedTier(
        Carbon::now()->subDay()->toDateTimeString(),
        Carbon::now()->addDay()->toDateTimeString()
    )];

    expect(filterTierListByDateWindow($arTierList))->toHaveCount(1);
});

test('a campaign with no end date runs forever', function () {
    $arTierList = [makeDatedTier(Carbon::now()->subYears(5)->toDateTimeString(), null)];

    expect(filterTierListByDateWindow($arTierList))->toHaveCount(1);
});

test('a campaign whose end date has passed is dropped', function () {
    $arTierList = [makeDatedTier(
        Carbon::now()->subDays(10)->toDateTimeString(),
        Carbon::now()->subDay()->toDateTimeString()
    )];

    expect(filterTierListByDateWindow($arTierList))->toBe([]);
});

test('a campaign that has not started yet is dropped', function () {
    $arTierList = [makeDatedTier(
        Carbon::now()->addDay()->toDateTimeString(),
        Carbon::now()->addDays(10)->toDateTimeString()
    )];

    expect(filterTierListByDateWindow($arTierList))->toBe([]);
});

test('a tier with no begin date never starts, as the SQL scope has it', function () {
    $arTierList = [makeDatedTier(null, null)];

    expect(filterTierListByDateWindow($arTierList))->toBe([]);
});

test('an ended campaign is dropped from among running ones, and the keys close up', function () {
    $arTierList = [
        makeDatedTier(Carbon::now()->subDays(10)->toDateTimeString(), Carbon::now()->subDay()->toDateTimeString(), 11),
        makeDatedTier(Carbon::now()->subDay()->toDateTimeString(), null, 22),
        makeDatedTier(Carbon::now()->subDay()->toDateTimeString(), Carbon::now()->addDay()->toDateTimeString(), 33),
    ];

    $arResult = filterTierListByDateWindow($arTierList);

    expect($arResult)->toHaveCount(2);
    expect(array_column($arResult, 'campaign_id'))->toBe([22, 33]);
    expect(array_keys($arResult))->toBe([0, 1]);
});

test('an empty list stays empty rather than throwing', function () {
    expect(filterTierListByDateWindow([]))->toBe([]);
});
