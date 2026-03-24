<?php namespace Logingrupa\CampaignpricingShopaholic\Classes\Collection;

use ArrayIterator;
use Logingrupa\CampaignpricingShopaholic\Classes\Item\CampaignPricingItem;
use Lovata\Toolbox\Classes\Collection\ElementCollection;

/**
 * Class CampaignPricingCollection
 * @package Logingrupa\CampaignpricingShopaholic\Classes\Collection
 *
 * Collection of campaign pricing tiers, iterable in Twig.
 * Wraps raw tier data arrays as CampaignPricingItem instances.
 */
class CampaignPricingCollection extends ElementCollection
{
    public const ITEM_CLASS = CampaignPricingItem::class;

    /**
     * Convenience factory from tier data list
     * @param array<int, array<string, mixed>> $arTierDataList
     */
    public static function makeFromTierDataList(array $arTierDataList): static
    {
        return static::make($arTierDataList);
    }

    /**
     * Override iteration to use makeFromData instead of model-based make
     */
    #[\Override]
    public function getIterator(): ArrayIterator
    {
        if ($this->isEmpty()) {
            return new ArrayIterator([]);
        }

        $arItemList = [];
        foreach ($this->arElementIDList as $mTierData) {
            if (!is_array($mTierData)) {
                continue;
            }
            /** @var array<string, mixed> $mTierData */
            $arItemList[] = CampaignPricingItem::makeFromData($mTierData);
        }

        return new ArrayIterator($arItemList);
    }
}
