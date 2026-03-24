<?php namespace Logingrupa\CampaignpricingShopaholic\Components;

use Cms\Classes\ComponentBase;
use Lovata\Shopaholic\Classes\Item\OfferItem;
use Lovata\Shopaholic\Classes\Item\ProductItem;

/**
 * Class CampaignPricing
 * @package Logingrupa\CampaignpricingShopaholic\Components
 *
 * CMS component for displaying campaign pricing tiers on product pages.
 * Drop {% component 'CampaignPricing' %} on a page where obProduct is available.
 *
 * Pricing data is exposed via the OfferItem `campaign_pricing_list` accessor.
 * The onSwitchOffer AJAX handler updates tiers when the user selects a different offer.
 */
class CampaignPricing extends ComponentBase
{
    /**
     * Returns information about this component
     * @return array<string, string>
     */
    #[\Override]
    public function componentDetails(): array
    {
        return [
            'name'        => 'logingrupa.campaignpricingshopaholic::lang.component.name',
            'description' => 'logingrupa.campaignpricingshopaholic::lang.component.description',
        ];
    }

    /**
     * AJAX handler: update campaign pricing tiers when the offer changes.
     * Expects post data: product_id, offer_id.
     * Returns the updated default partial with the new offer's tiers.
     *
     * Usage in Twig (vanilla JS / Larajax):
     *   data-request="CampaignPricing::onSwitchOffer"
     *   data-request-data="product_id: 123, offer_id: 456"
     *   data-request-update="'CampaignPricing::default': '#campaignPricingTiers'"
     */
    public function onSwitchOffer(): void
    {
        $mProductId = input('product_id');
        $iProductId = is_numeric($mProductId) ? (int) $mProductId : 0;
        $mOfferId = input('offer_id');
        $iOfferId = is_numeric($mOfferId) ? (int) $mOfferId : 0;

        if ($iProductId === 0) {
            return;
        }

        $obProduct = ProductItem::make($iProductId);
        if ($obProduct->isEmpty()) {
            return;
        }

        // Resolve the specific offer, or fall back to default offer
        $obOffer = $iOfferId > 0
            ? OfferItem::make($iOfferId)
            : $obProduct->offer;

        $this->page['obProduct'] = $obProduct;
        $this->page['obOffer'] = $obOffer;
    }
}
