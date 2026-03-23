<?php namespace Logingrupa\CampaignpricingShopaholic;

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
     * @var array
     */
    public $require = [
        'Lovata.Shopaholic',
        'Lovata.CampaignsShopaholic',
    ];

    /**
     * Returns information about this plugin
     * @return array
     */
    public function pluginDetails()
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
     * @return void
     */
    public function boot()
    {
        // Phase 2 will add OfferItem extension and event subscribers here
    }

    /**
     * Register components
     * @return array
     */
    public function registerComponents()
    {
        return [];
        // Phase 3 will register CampaignPricing component here
    }
}
