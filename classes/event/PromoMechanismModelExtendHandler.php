<?php namespace Logingrupa\CampaignpricingShopaholic\Classes\Event;

use Lovata\OrdersShopaholic\Models\PromoMechanism;

/**
 * Class PromoMechanismModelExtendHandler
 * @package Logingrupa\CampaignpricingShopaholic\Classes\Event
 *
 * Extends the PromoMechanism model with:
 * - TranslatableModel behavior for display_template column
 * - display_template declared as translatable so the October CMS v4
 *   site switcher handles per-locale editing in the backend
 */
class PromoMechanismModelExtendHandler
{
    public function subscribe(): void
    {
        // Set translatable property BEFORE the behavior reads it
        PromoMechanism::extend(function (PromoMechanism $obModel): void {
            $obModel->addDynamicProperty('translatable', ['display_template']);

            if (!$obModel->isClassExtendedWith('RainLab.Translate.Behaviors.TranslatableModel')) {
                $obModel->implement[] = 'RainLab.Translate.Behaviors.TranslatableModel';
            }
        });
    }
}
