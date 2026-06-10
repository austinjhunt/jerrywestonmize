# melograno/usage-tracker

Shared WordPress library for anonymous plugin usage telemetry across Melograno products (Amelia, wpDataTables, IvyForms).

## Plugin integration

After Composer autoload is loaded:

```php
\Melograno\UsageTracker\Core\UsageTracker::init(
    new \Melograno\UsageTracker\Collectors\Plugin\AmeliaCollector(),
    __FILE__ // activation: default consent opt-in; deactivation: clear cron
);
```

Each product collector (`AmeliaCollector`, `WpDataTablesCollector`, `IvyFormsCollector`) defines `getPluginSlug()` and `getConsentOptionName()`.

Host plugin settings UI should read/write consent via `ConsentManager` using the same collector instance (or `getConsentOptionName()`).

### Build-time setup (once per plugin)

1. Add the dependency:

```json
"require": {
  "melograno/usage-tracker": "^1.0"
}
```

2. Optionally include in [Strauss](https://github.com/BrianHenryIE/strauss) `packages` if you need a prefixed copy (`AmeliaVendor\Melograno\UsageTracker\...`). Amelia loads the package **without** Strauss prefixing so `Melograno\UsageTracker` resolves via Composer autoload.

3. Run `composer install` (and Strauss if used) before packaging the plugin.

### Local path repository (development)

```json
"repositories": [
  {
    "type": "path",
    "url": "../plugin-usage-tracker"
  }
]
```

## What the library provides

- Weekly WP Cron send to `https://bi.melograno.io/v1/usage` (override with `MELOGRANO_USAGE_TRACKER_ENDPOINT`)
- Per-plugin consent `wp_option` (name from collector; host UI writes it; library reads it for cron/send)
- Common collectors (WP environment, activation stats)
- Product collectors: `AmeliaCollector`, `WpDataTablesCollector`, `IvyFormsCollector`
- `sha256` site identifier (no raw site URL in payload)
- Deactivation (when `__FILE__` passed to `init`): clears scheduled cron only; consent option is kept
- Uninstall: host plugin should `ConsentManager::delete()` using the collector’s option name

## Development

```bash
composer install
composer test
```

## Payload schema

```json
{
  "schema_version": 1,
  "sent_at": "2026-05-19T12:00:00Z",
  "plugin": "ameliabooking",
  "site_id": "<sha256 of site_url>",
  "common": { "environment": {}, "activation": {} },
  "plugin_data": {}
}
```
