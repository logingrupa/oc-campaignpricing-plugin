<?php namespace Logingrupa\CampaignpricingShopaholic;

use Event;
use Logingrupa\CampaignpricingShopaholic\Classes\Event\CampaignPricingModelHandler;
use Logingrupa\CampaignpricingShopaholic\Classes\Event\CampaignPricingRelationHandler;
use Logingrupa\CampaignpricingShopaholic\Classes\Event\OfferItemExtendHandler;
use Logingrupa\CampaignpricingShopaholic\Classes\Event\PromoMechanismFieldsHandler;
use Logingrupa\CampaignpricingShopaholic\Classes\Event\PromoMechanismModelExtendHandler;
use Logingrupa\CampaignpricingShopaholic\Classes\Event\PromoMechanismPricingHandler;
use Logingrupa\CampaignpricingShopaholic\Components\CampaignPricing;
use System\Classes\PluginBase;

/**
 * Class Plugin
 * @package Logingrupa\CampaignpricingShopaholic
 * @author Logingrupa
 */
class Plugin extends PluginBase
{
    /**
     * Required plugins
     * @var list<string>
     */
    public $require = [
        'Lovata.Shopaholic',
        'Lovata.CampaignsShopaholic',
    ];

    /**
     * Returns information about this plugin
     * @return array<string, string>
     */
    #[\Override]
    public function pluginDetails(): array
    {
        return [
            'name'        => 'logingrupa.campaignpricingshopaholic::lang.plugin.name',
            'description' => 'logingrupa.campaignpricingshopaholic::lang.plugin.description',
            'author'      => 'Logingrupa',
            'icon'        => 'icon-tags',
        ];
    }

    /**
     * Boot method, called right before the request route
     */
    public function boot(): void
    {
        Event::subscribe(CampaignPricingModelHandler::class);
        Event::subscribe(PromoMechanismPricingHandler::class);
        (new CampaignPricingRelationHandler())->subscribe();

        // Phase 3: OfferItem extension with campaign_pricing_list accessor
        Event::subscribe(OfferItemExtendHandler::class);

        // Backend: add display_template field to promo mechanism form
        Event::subscribe(PromoMechanismFieldsHandler::class);

        // Model: make display_template translatable via site switcher
        (new PromoMechanismModelExtendHandler())->subscribe();
    }

    /**
     * Register components
     * @return array<class-string, string>
     */
    #[\Override]
    public function registerComponents(): array
    {
        return [
            CampaignPricing::class => 'CampaignPricing',
        ];
    }
}
