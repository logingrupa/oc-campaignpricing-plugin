<?php namespace Logingrupa\CampaignpricingShopaholic\Classes\Event;

use Illuminate\Support\Facades\DB;
use Logingrupa\CampaignpricingShopaholic\Classes\Store\OfferCampaignPricingStore;
use Lovata\CampaignsShopaholic\Models\Campaign;
use Lovata\OrdersShopaholic\Models\PromoMechanism;
use Lovata\Toolbox\Classes\Event\ModelHandler;
use Lovata\Toolbox\Classes\Item\ElementItem;

/**
 * Class PromoMechanismPricingHandler
 * @package Logingrupa\CampaignpricingShopaholic\Classes\Event
 *
 * Clears OfferCampaignPricingStore cache for all offers affected by campaigns
 * that use the updated PromoMechanism (DATA-07, D-10).
 */
class PromoMechanismPricingHandler extends ModelHandler
{
    /** @var int */
    protected $iPriority = 900;

    /** @var PromoMechanism */
    protected $obElement;

    /**
     * After save event handler
     * Traces mechanism -> campaigns -> affected offers and clears pricing cache.
     * Do NOT call parent::afterSave() -- we do not manage PromoMechanism item cache.
     */
    #[\Override]
    protected function afterSave(): void
    {
        $arCampaignIdList = (array) DB::table('lovata_campaigns_shopaholic_campaigns')
            ->where('promo_mechanism_id', $this->obElement->id)
            ->pluck('id')
            ->all();

        /** @var OfferCampaignPricingStore $obStore */
        $obStore = OfferCampaignPricingStore::instance();

        foreach ($arCampaignIdList as $iCampaignId) {
            $obCampaign = Campaign::find($iCampaignId);
            if (!$obCampaign instanceof Campaign) {
                continue;
            }

            $arOfferIdList = OfferCampaignPricingStore::resolveAffectedOfferIdList($obCampaign);

            foreach ($arOfferIdList as $iOfferId) {
                $obStore->clear($iOfferId);
            }
        }
    }

    /**
     * After delete event handler
     * Empty override -- PromoMechanism deletion is an edge case;
     * campaigns must be deleted first.
     */
    #[\Override]
    protected function afterDelete(): void
    {
        // No-op: campaigns reference mechanism via FK, so campaigns
        // should be removed or reassigned before mechanism deletion.
    }

    /**
     * Get model class name
     */
    protected function getModelClass(): string
    {
        return PromoMechanism::class;
    }

    /**
     * Get item class name
     * Required by abstract parent but not used since we override afterSave/afterDelete.
     */
    protected function getItemClass(): string
    {
        return ElementItem::class;
    }
}
