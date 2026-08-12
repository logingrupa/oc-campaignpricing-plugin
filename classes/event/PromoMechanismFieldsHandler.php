<?php namespace Logingrupa\CampaignpricingShopaholic\Classes\Event;

use Lovata\OrdersShopaholic\Controllers\PromoMechanisms;
use Lovata\OrdersShopaholic\Models\PromoMechanism;
use Lovata\Toolbox\Classes\Event\AbstractBackendFieldHandler;

/**
 * Class PromoMechanismFieldsHandler
 * @package Logingrupa\CampaignpricingShopaholic\Classes\Event
 *
 * Extends the promo mechanism backend form with a display_template field.
 * Uses a plain textarea so the admin's HTML (<strong>, <br>, <span>, ...) is
 * stored verbatim - a richeditor would wrap the value in <p> blocks and split
 * inline tags. Translatable via the October CMS v4 site switcher
 * (TranslatableModel behavior).
 */
class PromoMechanismFieldsHandler extends AbstractBackendFieldHandler
{
    /**
     * Extend form fields
     * @param \Backend\Widgets\Form $obWidget
     */
    protected function extendFields($obWidget): void
    {
        $obWidget->addFields([
            'property[campaign_pricing_enabled]' => [
                'label'   => 'logingrupa.campaignpricingshopaholic::lang.field.enabled',
                'comment' => 'logingrupa.campaignpricingshopaholic::lang.field.enabled_comment',
                'type'    => 'switch',
                'span'    => 'left',
                'default' => true,
                'tab'     => 'logingrupa.campaignpricingshopaholic::lang.field.tab_display',
            ],
            '_display_preview' => [
                'type'    => 'partial',
                'path'    => '$/logingrupa/campaignpricingshopaholic/partials/_display_preview.htm',
                'span'    => 'right',
                'tab'     => 'logingrupa.campaignpricingshopaholic::lang.field.tab_display',
                'trigger' => [
                    'action'    => 'show',
                    'field'     => 'property[campaign_pricing_enabled]',
                    'condition' => 'checked',
                ],
            ],
            'property[campaign_pricing_custom_template]' => [
                'label'   => 'logingrupa.campaignpricingshopaholic::lang.field.customize_enabled',
                'comment' => 'logingrupa.campaignpricingshopaholic::lang.field.customize_comment',
                'type'    => 'switch',
                'span'    => 'full',
                'default' => false,
                'tab'     => 'logingrupa.campaignpricingshopaholic::lang.field.tab_display',
                'trigger' => [
                    'action'    => 'show',
                    'field'     => 'property[campaign_pricing_enabled]',
                    'condition' => 'checked',
                ],
            ],
            'display_template' => [
                'label'        => 'logingrupa.campaignpricingshopaholic::lang.field.display_template',
                'type'         => 'textarea',
                'translatable' => true,
                'size'         => 'small',
                'span'         => 'left',
                'tab'          => 'logingrupa.campaignpricingshopaholic::lang.field.tab_display',
                'placeholder'  => 'logingrupa.campaignpricingshopaholic::lang.field.display_template_placeholder',
                'trigger' => [
                    'action'    => 'show',
                    'field'     => 'property[campaign_pricing_custom_template]',
                    'condition' => 'checked',
                ],
            ],
            '_display_template_help' => [
                'type'    => 'partial',
                'path'    => '$/logingrupa/campaignpricingshopaholic/partials/_display_template_help.htm',
                'span'    => 'right',
                'tab'     => 'logingrupa.campaignpricingshopaholic::lang.field.tab_display',
                'trigger' => [
                    'action'    => 'show',
                    'field'     => 'property[campaign_pricing_custom_template]',
                    'condition' => 'checked',
                ],
            ],
            '_display_custom_preview' => [
                'type'    => 'partial',
                'path'    => '$/logingrupa/campaignpricingshopaholic/partials/_display_custom_preview.htm',
                'span'    => 'left',
                'tab'     => 'logingrupa.campaignpricingshopaholic::lang.field.tab_display',
                'trigger' => [
                    'action'    => 'show',
                    'field'     => 'property[campaign_pricing_custom_template]',
                    'condition' => 'checked',
                ],
            ],
        ]);
    }

    /**
     * Get model class
     */
    protected function getModelClass(): string
    {
        return PromoMechanism::class;
    }

    /**
     * Get controller class
     */
    protected function getControllerClass(): string
    {
        return PromoMechanisms::class;
    }
}
