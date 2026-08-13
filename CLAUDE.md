# Logingrupa.CampaignpricingShopaholic

Tiered campaign pricing on top of Lovata.CampaignsShopaholic promo mechanisms: per-offer
pricing tiers, locale-aware display_template on promo mechanisms, CampaignPricing component
with onSwitchOffer AJAX. Namespace Logingrupa\CampaignpricingShopaholic, composer package
logingrupa/oc-campaignpricing-plugin. Requires Lovata.Shopaholic + Lovata.CampaignsShopaholic.
README.md documents usage; assets/screenshots show the backend UI.

## Environment

- Parent app: C:\laragon\www\nc.
- This plugin dir is its OWN git repo - commit here, not in the root repo.

## Architecture map

- classes/event/       CampaignPricingModelHandler (cache invalidation),
                       CampaignPricingRelationHandler, OfferItemExtendHandler
                       (campaign_pricing_list accessor on OfferItem),
                       PromoMechanismFieldsHandler (display_template backend field),
                       PromoMechanismModelExtendHandler (display_template translatable),
                       PromoMechanismPricingHandler (price calculation hook)
- classes/store/       OfferCampaignPricingStore
- classes/collection/  CampaignPricingCollection
- classes/item/        CampaignPricingItem
- classes/tier/        TierResolverRegistry (tier merge/resolution)
- components/          CampaignPricing (+ campaignpricing/default.htm partial)
- lang/                en, lt, lv, nb, ru
- tests/               8 files
- updates/             version.yaml + add_display_template_to_promo_mechanism.php (adds
                       column to Lovata promo mechanism table)

## Quality gates - Makefile from plugin dir wraps root vendor/bin

```bash
make test        # pest with this plugin's phpunit.xml (SQLite in-memory)
make analyse     # phpstan (phpstan.neon + phpstan-baseline.neon), clean at level 10
make phpmd       # phpmd.xml ruleset
make pint-test   # pint --test with pint.json
make rector-dry  # rector dry run
make all         # pint-test + analyse + phpmd + test
```

composer lint does NOT cover this plugin (phpcs.xml scope excludes plugins/logingrupa) - fix
phpcs.xml scope or lint manually; `vendor/bin/phpcs --standard=phpcs.xml <plugin path>` won't
work either since the ruleset pins files; note as known gap.

## Ship

Ship via /nc-ship (root CLAUDE.md release flow); package logingrupa/oc-campaignpricing-plugin.

## Conventions

Root CLAUDE.md governs: Hungarian notation, Store -> Collection -> Item read path, Tiger-Style.

## Gotchas

- Locale-aware display_template resolves at RENDER time, not cache time (1.3.0 fix) - do
  not move template resolution back into cached data.
- Campaign date window is decided when tiers are READ, not when cached; the cache key is
  versioned so stale-format entries are ignored (1.4.2). Bump the key version if the cached
  shape changes.
- PromoMechanism lookups are memoized per request across tiers (1.4.1) - one find per mechanism.
