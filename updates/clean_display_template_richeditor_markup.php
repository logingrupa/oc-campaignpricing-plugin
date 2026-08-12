<?php namespace Logingrupa\CampaignpricingShopaholic\Updates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/**
 * One-time cleanup of display_template values saved while the backend field
 * was a richeditor. Froala wrapped every value in <p> blocks and could split
 * one <strong> run into two adjacent tags. The field is now a plain textarea
 * (the admin's HTML is stored verbatim), so stored values are unwrapped once
 * here instead of being sanitized on every render.
 */
class CleanDisplayTemplateRicheditorMarkup extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('lovata_orders_shopaholic_promo_mechanism', 'display_template')) {
            return;
        }

        // Base column
        $obRowList = DB::table('lovata_orders_shopaholic_promo_mechanism')
            ->whereNotNull('display_template')
            ->where('display_template', '!=', '')
            ->get(['id', 'display_template']);

        foreach ($obRowList as $obRow) {
            $sCleaned = $this->cleanTemplate((string) $obRow->display_template);
            if ($sCleaned !== (string) $obRow->display_template) {
                DB::table('lovata_orders_shopaholic_promo_mechanism')
                    ->where('id', $obRow->id)
                    ->update(['display_template' => $sCleaned]);
            }
        }

        // Translated values stored by RainLab.Translate
        if (!Schema::hasTable('rainlab_translate_attributes')) {
            return;
        }

        $obTranslateRowList = DB::table('rainlab_translate_attributes')
            ->where('model_type', 'Lovata\OrdersShopaholic\Models\PromoMechanism')
            ->get(['id', 'attribute_data']);

        foreach ($obTranslateRowList as $obRow) {
            $arAttributeList = json_decode((string) $obRow->attribute_data, true);
            if (!is_array($arAttributeList) || !isset($arAttributeList['display_template'])) {
                continue;
            }

            $sCleaned = $this->cleanTemplate((string) $arAttributeList['display_template']);
            if ($sCleaned === (string) $arAttributeList['display_template']) {
                continue;
            }

            $arAttributeList['display_template'] = $sCleaned;
            DB::table('rainlab_translate_attributes')
                ->where('id', $obRow->id)
                ->update(['attribute_data' => json_encode($arAttributeList, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        // One-way data cleanup - nothing to restore
    }

    /**
     * Unwrap richeditor markup: paragraph boundaries become <br>, wrapping
     * <p> tags are dropped, adjacent <strong> runs are merged.
     */
    private function cleanTemplate(string $sTemplate): string
    {
        $sTemplate = (string) preg_replace('#</p>\s*<p[^>]*>#i', '<br>', $sTemplate);
        $sTemplate = (string) preg_replace('#</?p[^>]*>#i', '', $sTemplate);
        $sTemplate = str_replace('</strong><strong>', '', $sTemplate);

        return trim($sTemplate);
    }
}
