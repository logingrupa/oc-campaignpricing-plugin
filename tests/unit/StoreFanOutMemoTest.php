<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Logingrupa\CampaignpricingShopaholic\Classes\Store\OfferCampaignPricingStore;
use October\Rain\Database\Schema\Blueprint;

/**
 * A product page resolves pricing for every shade of ONE product, so the
 * product-level half of the pivot fan-out (brand, categories, and the
 * product/brand/category campaign pivots) is the same answer for all of them.
 * These pin the per-request memo that says it once: without it a twelve-shade
 * batch pays the whole fan-out twelve times.
 */
beforeEach(function () {
    OfferCampaignPricingStore::forgetMemo();

    $arTableList = [
        'lovata_shopaholic_offers'                     => ['product_id'],
        'lovata_shopaholic_products'                   => ['brand_id', 'category_id'],
        'lovata_shopaholic_additional_categories'      => ['product_id', 'category_id'],
        'lovata_campaigns_shopaholic_campaign_offer'   => ['campaign_id', 'offer_id'],
        'lovata_campaigns_shopaholic_campaign_product' => ['campaign_id', 'product_id'],
        'lovata_campaigns_shopaholic_campaign_brand'   => ['campaign_id', 'brand_id'],
        'lovata_campaigns_shopaholic_campaign_category' => ['campaign_id', 'category_id'],
    ];
    foreach ($arTableList as $sTable => $arColumnList) {
        Schema::dropIfExists($sTable);
        Schema::create($sTable, function (Blueprint $obTable) use ($arColumnList) {
            $obTable->increments('id');
            foreach ($arColumnList as $sColumn) {
                $obTable->integer($sColumn)->nullable();
            }
        });
    }
    Schema::dropIfExists('lovata_campaigns_shopaholic_campaigns');
    Schema::create('lovata_campaigns_shopaholic_campaigns', function (Blueprint $obTable) {
        $obTable->increments('id');
        $obTable->boolean('active')->default(false);
        $obTable->string('name')->nullable();
        $obTable->integer('promo_block_id')->nullable();
        $obTable->dateTime('date_begin')->nullable();
        $obTable->dateTime('date_end')->nullable();
    });

    DB::table('lovata_shopaholic_products')->insert(['id' => 1, 'brand_id' => 5, 'category_id' => 7]);
    DB::table('lovata_shopaholic_offers')->insert([
        ['id' => 11, 'product_id' => 1],
        ['id' => 12, 'product_id' => 1],
    ]);
    DB::table('lovata_shopaholic_additional_categories')->insert(['product_id' => 1, 'category_id' => 9]);
    DB::table('lovata_campaigns_shopaholic_campaign_product')->insert(['campaign_id' => 3, 'product_id' => 1]);
});

/**
 * Query count of one uncached resolve.
 */
function countStoreQueries(int $iOfferId): int
{
    /** @var OfferCampaignPricingStore $obStore */
    $obStore = OfferCampaignPricingStore::instance();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $obStore->getNoCache((string) $iOfferId);
    $iCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $iCount;
}

it('resolves the product level fan out once per product', function () {
    $iFirst = countStoreQueries(11);
    $iSecond = countStoreQueries(12);

    expect($iFirst)->toBeGreaterThan(4)
        ->and($iSecond)->toBe(2, 'the second shade may only read its own product_id and campaign_offer pivot');
});

it('resolves the fan out again after the memo is dropped', function () {
    $iFirst = countStoreQueries(11);
    OfferCampaignPricingStore::forgetMemo();
    $iAfterForget = countStoreQueries(12);

    expect($iAfterForget)->toBe($iFirst, 'a dropped memo must not serve a stale product answer');
});
