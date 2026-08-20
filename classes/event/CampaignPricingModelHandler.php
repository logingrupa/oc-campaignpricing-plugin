<?php namespace Logingrupa\CampaignpricingShopaholic\Classes\Event;

use Logingrupa\CampaignpricingShopaholic\Classes\Store\OfferCampaignPricingStore;
use Lovata\CampaignsShopaholic\Classes\Item\CampaignItem;
use Lovata\CampaignsShopaholic\Models\Campaign;
use Lovata\Toolbox\Classes\Event\ModelHandler;

/**
 * Class CampaignPricingModelHandler
 * @package Logingrupa\CampaignpricingShopaholic\Classes\Event
 *
 * Clears OfferCampaignPricingStore cache for all affected offers
 * when a Campaign is saved or deleted (DATA-05).
 */
class CampaignPricingModelHandler extends ModelHandler
{
    /** @var int */
    protected $iPriority = 900;

    /** @var Campaign */
    protected $obElement;

    /**
     * After save event handler
     * Do NOT call parent::afterSave() -- we only clear our own pricing cache,
     * not CampaignItem cache (that is CampaignsShopaholic's responsibility).
     */
    #[\Override]
    protected function afterSave(): void
    {
        $this->clearAffectedOfferPricingCache();
    }

    /**
     * After delete event handler
     * Do NOT call parent::afterDelete() -- same reason as afterSave.
     */
    #[\Override]
    protected function afterDelete(): void
    {
        $this->clearAffectedOfferPricingCache();
    }

    /**
     * Trace campaign -> pivot tables -> affected offer IDs -> clear pricing cache (D-09)
     */
    protected function clearAffectedOfferPricingCache(): void
    {
        $arOfferIdList = OfferCampaignPricingStore::resolveAffectedOfferIdList($this->obElement);

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
     * Get item class name
     */
    protected function getItemClass(): string
    {
        return CampaignItem::class;
    }
}
