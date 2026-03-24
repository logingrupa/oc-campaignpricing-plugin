<?php namespace Logingrupa\CampaignpricingShopaholic\Updates;

use October\Rain\Database\Updates\Migration;
use Schema;

/**
 * Add display_template column to lovata_orders_shopaholic_promo_mechanism table.
 * Stores the admin-customizable display text for campaign pricing tiers.
 * Translatable via RainLab.Translate (requires real column, not JSON property).
 */
class AddDisplayTemplateToPromoMechanism extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('lovata_orders_shopaholic_promo_mechanism', 'display_template')) {
            return;
        }

        Schema::table('lovata_orders_shopaholic_promo_mechanism', function ($table) {
            $table->text('display_template')->nullable()->after('property');
        });
    }

    public function down()
    {
        if (!Schema::hasColumn('lovata_orders_shopaholic_promo_mechanism', 'display_template')) {
            return;
        }

        Schema::table('lovata_orders_shopaholic_promo_mechanism', function ($table) {
            $table->dropColumn('display_template');
        });
    }
}
