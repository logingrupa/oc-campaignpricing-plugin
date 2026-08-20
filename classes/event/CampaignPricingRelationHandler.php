<?php namespace Logingrupa\CampaignpricingShopaholic\Classes\Event;

use Logingrupa\CampaignpricingShopaholic\Classes\Store\OfferCampaignPricingStore;
use Lovata\CampaignsShopaholic\Models\Campaign;
use Lovata\Toolbox\Classes\Event\AbstractModelRelationHandler;

/**
 * Class CampaignPricingRelationHandler
 * @package Logingrupa\CampaignpricingShopaholic\Classes\Event
 *
 * Clears OfferCampaignPricingStore cache for all affected offers
 * when Campaign relations (product, offer, brand, category) are
 * attached or detached (DATA-06).
 *
 * Skips tag and shipping_type relations per D-02.
 */
class CampaignPricingRelationHandler extends AbstractModelRelationHandler
{
    /** @var int */
    protected $iPriority = 900;

    /**
     * After attach event handler
     * @param Campaign $obModel
     * @param list<int> $arAttachedIDList
     * @param array<string, mixed> $arInsertData
     */
    protected function afterAttach($obModel, $arAttachedIDList, $arInsertData): void
    {
        $this->clearAffectedOfferPricingCache($obModel);
    }

    /**
     * After detach event handler
     * @param Campaign $obModel
     * @param list<int> $arAttachedIDList
     */
    protected function afterDetach($obModel, $arAttachedIDList): void
    {
        $this->clearAffectedOfferPricingCache($obModel);
    }

    /**
     * Trace campaign -> pivot tables -> affected offer IDs -> clear pricing cache (D-09)
     */
    protected function clearAffectedOfferPricingCache(Campaign $obModel): void
    {
        $arOfferIdList = OfferCampaignPricingStore::resolveAffectedOfferIdList($obModel);

        OfferCampaignPricingStore::forgetMemo();

        /** @var OfferCampaignPricingStore $obStore */
        $obStore = OfferCampaignPricingStore::instance();
        foreach ($arOfferIdList as $iOfferId) {
            $obStore->clear($iOfferId);
        }
    }

    /**
     * Get model class name
     */
    protected function getModelClass(): string
    {
        return Campaign::class;
    }

    /**
     * Get relation name
     * @return list<string>
     */
    protected function getRelationName(): array
    {
        return ['product', 'offer', 'brand', 'category'];
    }
}
