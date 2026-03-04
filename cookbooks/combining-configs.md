# Combining Configuration Files

Combine multiple configuration files into a single file, with support for deep merging and format conversion.

**Use cases:** Merging environment-specific configs, consolidating distributed configs, or creating unified config files from multiple sources.

## Basic File Combining

```php
use Cline\Ferret\Facades\Ferret;

// Combine multiple config files into one
// Later files override earlier files for conflicting keys
Ferret::combine('/path/to/combined.json', [
    '/path/to/base.json',
    '/path/to/overrides.json',
]);
```

## Deep vs Shallow Merging

Given these source files:
- `base.json`: `{"database": {"host": "localhost", "port": 3306}}`
- `overrides.json`: `{"database": {"host": "production.db.com"}}`

```php
// Deep merge (default) - nested arrays are merged recursively
Ferret::combine('/path/to/combined.json', [
    '/path/to/base.json',
    '/path/to/overrides.json',
], deep: true);
// Result: {"database": {"host": "production.db.com", "port": 3306}}

// Shallow merge - nested arrays are replaced entirely
Ferret::combine('/path/to/combined.json', [
    '/path/to/base.json',
    '/path/to/overrides.json',
], deep: false);
// Result: {"database": {"host": "production.db.com"}}
```

## Format Conversion During Combine

The output format is determined by the destination file extension.

```php
// Combine YAML and NEON files into JSON output
Ferret::combine('/path/to/output.json', [
    '/path/to/config.yaml',
    '/path/to/overrides.neon',
]);

// Combine JSON files into YAML output
Ferret::combine('/path/to/output.yaml', [
    '/path/to/app.json',
    '/path/to/database.json',
    '/path/to/cache.json',
]);
```

## Complete Example: Environment-Based Configuration

```php
function buildEnvironmentConfig(string $environment): void
{
    $configs = [
        base_path('config/app.base.yaml'),
        base_path("config/app.{$environment}.yaml"),
    ];

    // Add local overrides if they exist
    $localConfig = base_path('config/app.local.yaml');
    if (file_exists($localConfig)) {
        $configs[] = $localConfig;
    }

    Ferret::combine(
        base_path('config/app.yaml'),
        $configs,
    );
}

// Usage:
// buildEnvironmentConfig('production');
// buildEnvironmentConfig('staging');
// buildEnvironmentConfig('development');
```

## Complete Example: Microservice Configuration Consolidation

```php
// Combine configs from multiple microservices into a gateway config
function buildGatewayConfig(): void
{
    $serviceConfigs = glob(base_path('services/*/config.yaml')) ?: [];

    Ferret::combine(
        base_path('gateway/routes.yaml'),
        $serviceConfigs,
    );
}
```

## Complete Example: Multi-Layer Configuration

```php
function buildFinalConfig(): void
{
    Ferret::combine(storage_path('app/config.json'), [
        // Layer 1: Base defaults
        base_path('config/defaults.json'),

        // Layer 2: Environment-specific
        base_path('config/' . app()->environment() . '.json'),

        // Layer 3: Instance-specific overrides
        base_path('config/instance.json'),
    ]);
}
```

## Complete Example: Multi-Tenant Configuration

```php
// Each tenant has base config + custom overrides
function buildTenantConfig(string $tenantId): string
{
    $outputPath = storage_path("tenants/{$tenantId}/config.json");

    $sources = [
        base_path('config/tenant-defaults.json'),
        base_path('config/features.json'),
    ];

    // Add tenant-specific config if it exists
    $tenantConfig = base_path("tenants/{$tenantId}/config.json");
    if (file_exists($tenantConfig)) {
        $sources[] = $tenantConfig;
    }

    // Add tenant feature overrides if they exist
    $tenantFeatures = base_path("tenants/{$tenantId}/features.json");
    if (file_exists($tenantFeatures)) {
        $sources[] = $tenantFeatures;
    }

    Ferret::combine($outputPath, $sources);

    return $outputPath;
}

// Usage:
// $configPath = buildTenantConfig('acme-corp');
// Ferret::load($configPath, 'tenant');
// $maxUsers = Ferret::get('tenant', 'limits.max_users');
```
