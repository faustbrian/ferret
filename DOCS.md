## Table of Contents

1. [Directory Loading](#doc-cookbooks-directory-loading) (`cookbooks/directory-loading.md`)
2. [Combining Configs](#doc-cookbooks-combining-configs) (`cookbooks/combining-configs.md`)
3. [Format Conversion](#doc-cookbooks-format-conversion) (`cookbooks/format-conversion.md`)
4. [Encryption](#doc-cookbooks-encryption) (`cookbooks/encryption.md`)
5. [Typed Accessors](#doc-cookbooks-typed-accessors) (`cookbooks/typed-accessors.md`)
6. [Overview](#doc-docs-readme) (`docs/README.md`)
7. [Directory Loading](#doc-docs-directory-loading) (`docs/directory-loading.md`)
8. [Encryption](#doc-docs-encryption) (`docs/encryption.md`)
9. [Format Conversion](#doc-docs-format-conversion) (`docs/format-conversion.md`)
10. [Typed Accessors](#doc-docs-typed-accessors) (`docs/typed-accessors.md`)
<a id="doc-cookbooks-directory-loading"></a>

# Loading Configuration Directories

Load an entire directory of configuration files into a namespaced structure, allowing dot-notation access across files.

**Use case:** Loading 60+ carrier contract NEON files into memory for a 4PL system.

## Basic Directory Loading

```php
use Cline\Ferret\Facades\Ferret;

// Load all configuration files from a directory
// Each file becomes a top-level key (filename without extension)
$result = Ferret::loadDirectory('/path/to/carriers', 'carriers');

// Access individual carrier configurations using dot notation
$upsName = Ferret::get('carriers', 'ups.name');                    // 'UPS'
$upsRate = Ferret::get('carriers', 'ups.rates.domestic');          // 5.99
$fedexServices = Ferret::get('carriers', 'fedex.services');        // ['ground', 'express', 'priority']
$dhlIntlRate = Ferret::get('carriers', 'dhl.rates.international'); // 12.99
```

## Pattern-Based Loading

```php
// Load only NEON files (useful when directory has mixed file types)
Ferret::loadDirectory('/path/to/carriers', 'neon-carriers', '*.neon');

// Load only JSON files
Ferret::loadDirectory('/path/to/carriers', 'json-carriers', '*.json');

// Load files matching a specific pattern
Ferret::loadDirectory('/path/to/carriers', 'v2-carriers', 'carrier-*-v2.*');
```

## Custom Key Extraction

When filenames follow a specific pattern, extract custom keys using regex.

```php
// Files: carrier-ups-v1.neon, carrier-fedex-v1.neon
// Pattern extracts: ups, fedex
Ferret::loadDirectory(
    '/path/to/carriers',
    'carriers',
    '*.neon',
    '/carrier-([a-z]+)-v\d+\.neon/',  // Regex with capture group
);

// Now access as:
Ferret::get('carriers', 'ups.rates.domestic');  // Not 'carrier-ups-v1.rates.domestic'
```

## Mutating Directory-Loaded Configurations

```php
// Load the carrier directory
Ferret::loadDirectory('/path/to/carriers', 'carriers', '*.neon');

// Update a specific carrier's rate
Ferret::set('carriers', 'ups.rates.domestic', 7.99);

// Add a new service to a carrier
Ferret::push('carriers', 'fedex.services', 'same-day');

// Check if changes were made
if (Ferret::isDirty('carriers')) {
    // Note: save() saves to a single file, so for directory-loaded configs
    // you would typically export individual sections back to their files
    $upsConfig = Ferret::get('carriers', 'ups');
    // Then use the loader directly or convert() to write back
}
```

## Complete Example: 4PL Carrier System

```php
// Load all carrier contracts once at application boot
Ferret::loadDirectory(
    base_path('config/carriers'),
    'carriers',
    '*.neon',
);

// In a service class, look up carrier rates
function getShippingRate(string $carrierCode, string $rateType): ?float
{
    return Ferret::get('carriers', "{$carrierCode}.rates.{$rateType}");
}

// Get all available carriers
function getCarrierCodes(): array
{
    $allCarriers = Ferret::get('carriers');

    return array_keys($allCarriers);
}

// Check if a carrier supports a specific service
function carrierSupportsService(string $carrierCode, string $service): bool
{
    $services = Ferret::get('carriers', "{$carrierCode}.services", []);

    return in_array($service, $services, true);
}

// Usage:
// getShippingRate('ups', 'domestic')      -> 5.99
// getShippingRate('dhl', 'international') -> 12.99
// getCarrierCodes()                       -> ['ups', 'fedex', 'dhl']
// carrierSupportsService('ups', 'ground') -> true
```

<a id="doc-cookbooks-combining-configs"></a>

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

<a id="doc-cookbooks-format-conversion"></a>

# Format Conversion

Convert configuration files between different formats (JSON, YAML, NEON, TOML, XML, INI, PHP) and export configurations to strings.

**Use case:** Migrating from one config format to another, or generating config files for different systems from a single source.

## File-to-File Conversion

```php
use Cline\Ferret\Facades\Ferret;

// Convert YAML to JSON
Ferret::convert('/path/to/config.yaml', '/path/to/config.json');

// Convert NEON to YAML
Ferret::convert('/path/to/config.neon', '/path/to/config.yaml');

// Convert JSON to XML
Ferret::convert('/path/to/config.json', '/path/to/config.xml');

// Convert TOML to JSON
Ferret::convert('/path/to/config.toml', '/path/to/config.json');

// Convert INI to PHP array
Ferret::convert('/path/to/config.ini', '/path/to/config.php');
```

## In-Memory Format Conversion

Load a configuration and export to different formats without writing files.

```php
Ferret::load('/path/to/myapp.yaml', 'myapp');

// Export to JSON string
$jsonString = Ferret::toFormat('myapp', 'json');
// {"database":{"host":"localhost","port":5432},"debug":true}

// Export to YAML string
$yamlString = Ferret::toFormat('myapp', 'yaml');
// database:
//   host: localhost
//   port: 5432
// debug: true

// Export to NEON string
$neonString = Ferret::toFormat('myapp', 'neon');
// database:
//     host: localhost
//     port: 5432
// debug: true

// Export to TOML string
$tomlString = Ferret::toFormat('myapp', 'toml');
// debug = true
// [database]
// host = "localhost"
// port = 5432

// Export to XML string
$xmlString = Ferret::toFormat('myapp', 'xml');
// <?xml version="1.0" encoding="UTF-8"?>
// <config><database><host>localhost</host><port>5432</port></database><debug>true</debug></config>
```

## Getting Loaders Directly

```php
// Get a loader instance for manual encoding/decoding
$jsonLoader = Ferret::getLoader('json');
$yamlLoader = Ferret::getLoader('yaml');
$neonLoader = Ferret::getLoader('neon');
$tomlLoader = Ferret::getLoader('toml');
$xmlLoader = Ferret::getLoader('xml');

// Encode data manually
$data = ['key' => 'value', 'nested' => ['a' => 1, 'b' => 2]];
$encoded = $jsonLoader->encode($data);
// {"key":"value","nested":{"a":1,"b":2}}

// Load and encode in one step
$config = $yamlLoader->load('/path/to/source.yaml');
$output = $xmlLoader->encode($config);
```

## Complete Example: Multi-Format Export System

```php
// Load your master configuration
Ferret::load(base_path('config/app.yaml'), 'app');

// Export to multiple formats for different consumers
function exportConfigToAllFormats(string $moduleName, string $outputDir): void
{
    $formats = ['json', 'yaml', 'neon', 'toml', 'xml'];

    foreach ($formats as $format) {
        $content = Ferret::toFormat($moduleName, $format);
        $filepath = "{$outputDir}/config.{$format}";
        file_put_contents($filepath, $content);
    }
}

// Usage:
// exportConfigToAllFormats('app', '/var/www/exports');
// Creates: config.json, config.yaml, config.neon, config.toml, config.xml
```

## Complete Example: Batch Migration

```php
// Batch convert all YAML files in a directory to NEON
function migrateYamlToNeon(string $sourceDir, string $destDir): array
{
    $converted = [];
    $files = glob("{$sourceDir}/*.yaml") ?: [];

    foreach ($files as $yamlFile) {
        $basename = basename($yamlFile, '.yaml');
        $neonFile = "{$destDir}/{$basename}.neon";

        Ferret::convert($yamlFile, $neonFile);
        $converted[] = $neonFile;
    }

    return $converted;
}

// Usage:
// $converted = migrateYamlToNeon('/old/configs', '/new/configs');
// echo count($converted) . ' files converted';
```

## Supported Formats

| Format | Extension | Read | Write |
|--------|-----------|------|-------|
| JSON   | .json     | ✓    | ✓     |
| YAML   | .yaml     | ✓    | ✓     |
| NEON   | .neon     | ✓    | ✓     |
| TOML   | .toml     | ✓    | ✓     |
| XML    | .xml      | ✓    | ✓     |
| INI    | .ini      | ✓    | ✓     |
| PHP    | .php      | ✓    | ✓     |

<a id="doc-cookbooks-encryption"></a>

# Configuration Encryption

Encrypt and decrypt configuration files for secure storage and deployment using Laravel's Encrypter with AES-256-CBC.

**Use cases:** Encrypting sensitive configs at rest, storing encrypted configs in version control, and decrypting during deployment.

## Basic Encryption

```php
use Cline\Ferret\Facades\Ferret;

// Encrypt a configuration file (generates a new key)
$result = Ferret::encrypt('/path/to/secrets.json');

// $result contains:
// [
//     'path' => '/path/to/secrets.json.encrypted',  // The encrypted file
//     'key'  => 'base64:ABC123...',                 // Store this securely!
// ]
```

> **Important:** Save the key securely (environment variable, secret manager, etc.). You'll need it to decrypt the file later.

## Decryption

```php
// Decrypt using the key from encryption
$decryptedPath = Ferret::decrypt(
    '/path/to/secrets.json.encrypted',
    'base64:ABC123...',  // The key from encrypt()
);

// Returns: '/path/to/secrets.json' (original filename without .encrypted)
```

## Using a Custom Key

```php
// Generate your own key (must be valid for AES-256-CBC = 32 bytes)
$myKey = 'base64:' . base64_encode(random_bytes(32));

// Encrypt with your key
$result = Ferret::encrypt('/path/to/config.yaml', $myKey);

// Decrypt with the same key
Ferret::decrypt('/path/to/config.yaml.encrypted', $myKey);
```

## Force Overwrite

```php
// By default, decrypt() throws if the target file already exists
// Use force: true to overwrite
$decryptedPath = Ferret::decrypt(
    '/path/to/secrets.json.encrypted',
    $key,
    force: true,
);
```

## Custom Cipher

```php
// Use a different cipher (default is AES-256-CBC)
$result = Ferret::encrypt(
    '/path/to/config.json',
    cipher: 'AES-128-CBC',  // 16-byte key
);

// Decrypt with matching cipher
Ferret::decrypt(
    '/path/to/config.json.encrypted',
    $result['key'],
    cipher: 'AES-128-CBC',
);
```

## Environment-Specific Files: Suffix Style (Default)

Suffix style transforms `config.json` → `config.production.json`. This matches Laravel's `.env` pattern.

```php
$result = Ferret::encrypt(
    '/path/to/config.json',
    env: 'production',  // Encrypts /path/to/config.production.json
);

// Decrypts /path/to/config.production.json.encrypted
Ferret::decrypt(
    '/path/to/config.json',
    $result['key'],
    env: 'production',
);
```

## Environment-Specific Files: Directory Style

Directory style transforms `config/carriers/dhl.neon` → `config/carriers/production/dhl.neon`. Perfect for organizing configs in environment subdirectories.

```php
$result = Ferret::encrypt(
    '/path/to/config/carriers/dhl.neon',
    env: 'production',
    envStyle: 'directory',
);

Ferret::decrypt(
    '/path/to/config/carriers/dhl.neon',
    $result['key'],
    env: 'production',
    envStyle: 'directory',
);
```

## Prune Option

Delete the source file after the operation completes.

```php
// Delete the original file after encryption
$result = Ferret::encrypt('/path/to/secrets.json', prune: true);
// /path/to/secrets.json is deleted, only .encrypted remains

// Delete the encrypted file after decryption
Ferret::decrypt('/path/to/secrets.json.encrypted', $key, prune: true);
// .encrypted file is deleted, only decrypted file remains
```

## Custom Output Location

```php
// Output to a different directory
$decryptedPath = Ferret::decrypt(
    '/path/to/secrets.json.encrypted',
    $key,
    path: '/var/www/app/config',
);
// Returns: /var/www/app/config/secrets.json

// Use a custom filename
$decryptedPath = Ferret::decrypt(
    '/path/to/secrets.json.encrypted',
    $key,
    filename: 'decrypted-secrets.json',
);
// Returns: /path/to/decrypted-secrets.json

// Combine path and filename
$decryptedPath = Ferret::decrypt(
    '/path/to/secrets.json.encrypted',
    $key,
    path: '/var/www/app/config',
    filename: 'app-secrets.json',
);
// Returns: /var/www/app/config/app-secrets.json
```

## Directory Encryption

Encrypt all files in a directory with a single key. Perfect for encrypting entire config directories like `.ferret/`.

```php
use Cline\Ferret\Facades\Ferret;

// Encrypt all files in a directory
$result = Ferret::encryptDirectory('/path/to/.ferret');

// $result contains:
// [
//     'files' => [
//         ['path' => '/path/to/.ferret/config.json.encrypted', 'key' => '...'],
//         ['path' => '/path/to/.ferret/secrets.yaml.encrypted', 'key' => '...'],
//     ],
//     'key' => 'base64:ABC123...',  // Same key for all files
// ]
```

### Recursive Directory Encryption

```php
// Encrypt files in subdirectories too
$result = Ferret::encryptDirectory(
    '/path/to/.ferret',
    recursive: true,
);
```

### Filter Files with Glob Pattern

```php
// Only encrypt JSON files
$result = Ferret::encryptDirectory(
    '/path/to/.ferret',
    glob: '*.json',
);

// Encrypt YAML files recursively
$result = Ferret::encryptDirectory(
    '/path/to/.ferret',
    recursive: true,
    glob: '*.yaml',
);
```

### Directory Decryption

```php
// Decrypt all .encrypted files in directory
$decryptedPaths = Ferret::decryptDirectory(
    '/path/to/.ferret',
    'base64:ABC123...',  // Key from encryptDirectory()
);

// Returns array of decrypted file paths:
// ['/path/to/.ferret/config.json', '/path/to/.ferret/secrets.yaml']

// Recursive decryption
$decryptedPaths = Ferret::decryptDirectory(
    '/path/to/.ferret',
    $key,
    recursive: true,
);
```

### Prune and Force Options

```php
// Delete originals after encryption
$result = Ferret::encryptDirectory(
    '/path/to/.ferret',
    prune: true,
);

// Overwrite existing encrypted files
$result = Ferret::encryptDirectory(
    '/path/to/.ferret',
    force: true,
);

// Delete encrypted files after decryption, overwrite existing
$decryptedPaths = Ferret::decryptDirectory(
    '/path/to/.ferret',
    $key,
    prune: true,
    force: true,
);
```

### CLI Commands for Directories

```bash
# Encrypt directory
php artisan ferret:encrypt .ferret

# Encrypt recursively with glob filter
php artisan ferret:encrypt .ferret --recursive --glob='*.json'

# Delete originals after encryption
php artisan ferret:encrypt .ferret --recursive --prune

# Decrypt directory
php artisan ferret:decrypt .ferret --key="base64:ABC123..."

# Decrypt recursively, keep encrypted files
php artisan ferret:decrypt .ferret --key="..." --recursive --keep
```

## Complete Example: Secure Deployment Workflow

```php
/**
 * Encrypt sensitive configs before committing to version control.
 * Run this locally before pushing code.
 */
function encryptForDeployment(): void
{
    $sensitiveFiles = [
        base_path('config/secrets.json'),
        base_path('config/api-keys.yaml'),
        base_path('config/database-credentials.neon'),
    ];

    $deployKey = env('CONFIG_ENCRYPTION_KEY');

    foreach ($sensitiveFiles as $filepath) {
        if (file_exists($filepath)) {
            Ferret::encrypt($filepath, $deployKey);
            unlink($filepath);  // Delete unencrypted version
            echo "Encrypted: {$filepath}\n";
        }
    }
}

/**
 * Decrypt sensitive configs during deployment.
 * Run this on the server after pulling code.
 */
function decryptForRuntime(): void
{
    $encryptedFiles = glob(base_path('config/*.encrypted')) ?: [];
    $deployKey = env('CONFIG_ENCRYPTION_KEY');

    foreach ($encryptedFiles as $encryptedPath) {
        Ferret::decrypt($encryptedPath, $deployKey, force: true);
        unlink($encryptedPath);  // Delete encrypted version
        echo "Decrypted: {$encryptedPath}\n";
    }
}
```

## Complete Example: Per-Environment Keys

```php
/**
 * Each environment has its own encryption key.
 * Encrypt once per environment, store encrypted files in version control.
 */
function encryptForEnvironment(string $environment): void
{
    $keyEnvVar = mb_strtoupper($environment) . '_CONFIG_KEY';
    $key = env($keyEnvVar);

    if ($key === null) {
        throw new RuntimeException("Missing encryption key: {$keyEnvVar}");
    }

    $configPath = base_path("config/secrets.{$environment}.json");
    $result = Ferret::encrypt($configPath, $key);

    echo "Encrypted {$configPath} -> {$result['path']}\n";
}

// Usage:
// PRODUCTION_CONFIG_KEY=base64:xxx encryptForEnvironment('production');
// STAGING_CONFIG_KEY=base64:yyy encryptForEnvironment('staging');
```

## Complete Example: Key Rotation

```php
function rotateEncryptionKey(string $filepath, string $oldKey, string $newKey): void
{
    // Decrypt with old key
    $decryptedPath = Ferret::decrypt($filepath, $oldKey, force: true);

    // Re-encrypt with new key
    Ferret::encrypt($decryptedPath, $newKey);

    // Clean up unencrypted file
    unlink($decryptedPath);
}
```

## Complete Example: Directory-Style Environment Workflow

```php
/**
 * Encrypt carrier configs for deployment.
 * Structure: config/carriers/{env}/carrier.neon
 */
function encryptCarrierConfigs(string $environment): void
{
    $key = env(mb_strtoupper($environment) . '_CONFIG_KEY');
    $carriers = ['dhl', 'fedex', 'ups'];

    foreach ($carriers as $carrier) {
        $basePath = base_path("config/carriers/{$carrier}.neon");

        Ferret::encrypt(
            $basePath,
            $key,
            env: $environment,
            envStyle: 'directory',
            prune: true,
        );
    }
}

/**
 * Decrypt carrier configs during deployment.
 */
function decryptCarrierConfigs(string $environment): void
{
    $key = env(mb_strtoupper($environment) . '_CONFIG_KEY');
    $carriers = ['dhl', 'fedex', 'ups'];

    foreach ($carriers as $carrier) {
        $basePath = base_path("config/carriers/{$carrier}.neon");

        Ferret::decrypt(
            $basePath,
            $key,
            env: $environment,
            envStyle: 'directory',
            prune: true,
            force: true,
        );
    }
}
```

<a id="doc-cookbooks-typed-accessors"></a>

# Typed Configuration Accessors

Access configuration values with automatic type casting and validation, similar to Laravel's `Config::string()`, `Config::integer()`, etc.

**Use case:** Ensuring configuration values are the expected type at runtime, with clear error messages when misconfigured.

## Basic Usage

```php
use Cline\Ferret\Facades\Ferret;

// Load your configuration
Ferret::load('/path/to/config.json', 'app');

// Access values with type guarantees
$host = Ferret::string('app', 'database.host');     // Returns string
$port = Ferret::integer('app', 'database.port');    // Returns int
$timeout = Ferret::float('app', 'database.timeout'); // Returns float
$debug = Ferret::boolean('app', 'debug');           // Returns bool
$servers = Ferret::array('app', 'cache.servers');   // Returns array
$features = Ferret::collection('app', 'features');  // Returns Collection
```

## Type Casting Behavior

### String

Casts scalars to string. Throws for null or array.

```php
// config: {"port": 5432, "debug": true}
Ferret::string('app', 'port');   // "5432"
Ferret::string('app', 'debug');  // "1" (true becomes "1", false becomes "")
```

### Integer

Casts numeric values to int. Throws for non-numeric strings, null, or array.

```php
// config: {"port": "5432", "ratio": 3.14}
Ferret::integer('app', 'port');   // 5432 (string cast to int)
Ferret::integer('app', 'ratio');  // 3 (float truncated to int)
```

### Float

Casts numeric values to float. Throws for non-numeric strings, null, or array.

```php
// config: {"ratio": "3.14159", "count": 42}
Ferret::float('app', 'ratio');  // 3.14159
Ferret::float('app', 'count');  // 42.0
```

### Boolean

Uses `filter_var` with `FILTER_VALIDATE_BOOLEAN`. Handles common boolean representations:

```php
// All return true:
Ferret::boolean('app', 'enabled');  // true, "true", "1", "yes", "on", 1

// All return false:
Ferret::boolean('app', 'disabled'); // false, "false", "0", "no", "off", 0, ""
```

### Array

Returns array values directly. Throws for non-array values.

```php
// config: {"servers": ["redis1", "redis2"], "database": {"host": "localhost"}}
Ferret::array('app', 'servers');           // ["redis1", "redis2"]
Ferret::array('app', 'database');          // ["host" => "localhost"]
Ferret::array('app', 'database.options');  // Works with dot notation
```

### Collection

Returns a Laravel Collection instance from array values.

```php
// config: {"features": ["dark-mode", "api-v2", "webhooks"]}
$features = Ferret::collection('app', 'features');

$features->count();           // 3
$features->contains('api-v2'); // true
$features->filter(fn($f) => str_starts_with($f, 'api-'));
```

## Error Handling

All typed accessors throw `InvalidArgumentException` with descriptive messages:

```php
// config: {"name": null, "items": ["a", "b"]}

Ferret::string('app', 'name');
// InvalidArgumentException: Configuration value for [app.name] must be a string, null given.

Ferret::string('app', 'items');
// InvalidArgumentException: Configuration value for [app.items] must be a string, array given.

Ferret::integer('app', 'nonexistent');
// InvalidArgumentException: Configuration value for [app.nonexistent] must be an integer, null given.

Ferret::integer('app', 'name');
// InvalidArgumentException: Configuration value for [app.name] must be an integer, non-numeric string given.
```

## Complete Example: Application Config

```php
// config/app.json
{
    "name": "My Application",
    "debug": true,
    "timezone": "UTC",
    "connections": {
        "database": {
            "host": "localhost",
            "port": 5432,
            "timeout": 30.5
        },
        "redis": {
            "host": "127.0.0.1",
            "port": 6379
        }
    },
    "features": ["dark-mode", "api-v2"],
    "limits": {
        "requests_per_minute": 60,
        "max_upload_mb": 10.5
    }
}
```

```php
Ferret::load(config_path('app.json'), 'app');

// Application settings
$appName = Ferret::string('app', 'name');
$isDebug = Ferret::boolean('app', 'debug');
$timezone = Ferret::string('app', 'timezone');

// Database connection
$dbHost = Ferret::string('app', 'connections.database.host');
$dbPort = Ferret::integer('app', 'connections.database.port');
$dbTimeout = Ferret::float('app', 'connections.database.timeout');

// Feature flags as collection for easy filtering
$features = Ferret::collection('app', 'features');
$hasApiV2 = $features->contains('api-v2');

// Rate limiting
$rateLimit = Ferret::integer('app', 'limits.requests_per_minute');
$maxUpload = Ferret::float('app', 'limits.max_upload_mb');
```

## Comparison with `get()`

| Method | Returns | On Missing Key | On Wrong Type |
|--------|---------|----------------|---------------|
| `get('m', 'key')` | `mixed` | Returns default | Returns as-is |
| `string('m', 'key')` | `string` | Throws | Casts or throws |
| `integer('m', 'key')` | `int` | Throws | Casts or throws |
| `float('m', 'key')` | `float` | Throws | Casts or throws |
| `boolean('m', 'key')` | `bool` | Throws | Casts via filter_var |
| `array('m', 'key')` | `array` | Throws | Throws |
| `collection('m', 'key')` | `Collection` | Throws | Throws |

Use `get()` when you need defaults or mixed types. Use typed accessors when you need type guarantees.

<a id="doc-docs-readme"></a>

Ferret is a configuration management library for PHP that supports multiple formats including PHP arrays, JSON, YAML, TOML, INI, XML, and NEON.

## Installation

```bash
composer require cline/ferret
```

## Basic Usage

```php
use Cline\Ferret\Config;

// Load a configuration file
$config = Config::load('config.json');

// Access values
$value = $config->get('database.host');

// Set values
$config->set('database.port', 3306);

// Save changes
$config->save('config.json');
```

## Supported Formats

Ferret automatically detects the format based on file extension:

| Extension | Format |
|-----------|--------|
| `.php` | PHP array |
| `.json` | JSON |
| `.yaml`, `.yml` | YAML |
| `.toml` | TOML |
| `.ini` | INI |
| `.xml` | XML |
| `.neon` | NEON |

## Next Steps

- [Directory Loading](#doc-docs-directory-loading) - Load entire configuration directories
- [Format Conversion](#doc-docs-format-conversion) - Convert between formats
- [Typed Accessors](#doc-docs-typed-accessors) - Type-safe value access
- [Encryption](#doc-docs-encryption) - Protect sensitive values

<a id="doc-docs-directory-loading"></a>

Load and merge configuration from entire directories.

**Use case:** Loading environment-specific configurations, managing modular config files, or combining multiple configuration sources.

## Basic Directory Loading

```php
use Cline\Ferret\Config;

// Load all config files from a directory
$config = Config::loadDirectory('config/');

// Files are merged in alphabetical order
// config/
//   app.json      -> loaded first
//   database.json -> merged second
//   cache.json    -> merged third
```

## Environment-Specific Loading

```php
// Load base config, then environment overlay
$config = Config::loadDirectory('config/');
$config->merge(Config::loadDirectory('config/production/'));
```

## Mixed Formats

Directories can contain mixed file formats:

```php
// config/
//   app.json
//   database.yaml
//   cache.toml
//   features.php

$config = Config::loadDirectory('config/');
// All formats are loaded and merged together
```

## Recursive Loading

```php
// Load configs recursively from subdirectories
$config = Config::loadDirectory('config/', recursive: true);

// config/
//   app.json
//   services/
//     mail.json
//     queue.json
```

## Filtering Files

```php
// Only load specific formats
$config = Config::loadDirectory('config/', extensions: ['json', 'yaml']);

// Exclude certain files
$config = Config::loadDirectory('config/', exclude: ['secrets.json']);
```

## Merge Strategies

### Deep Merge (Default)

```php
// config/base.json: {"database": {"host": "localhost"}}
// config/prod.json: {"database": {"port": 3306}}

$config = Config::loadDirectory('config/');
// Result: {"database": {"host": "localhost", "port": 3306}}
```

### Shallow Merge

```php
$config = Config::loadDirectory('config/', deepMerge: false);
// Later files completely replace earlier values
```

<a id="doc-docs-encryption"></a>

Encrypt sensitive configuration values for secure storage.

**Use case:** Storing API keys, database passwords, and other secrets safely in configuration files that may be committed to version control.

## Basic Encryption

```php
use Cline\Ferret\Config;
use Cline\Ferret\Encryption\Key;

// Generate or load an encryption key
$key = Key::generate();
// Or load from environment
$key = Key::fromString(getenv('CONFIG_KEY'));

// Create encrypted config
$config = Config::encrypted('config.json', $key);

// Use normally - encryption is transparent
$config->set('database.password', 'secret123');
$config->save();

// Value is encrypted in the file
// {"database": {"password": "ENC:abc123..."}}
```

## Selective Encryption

```php
// Only encrypt specific keys
$config = Config::load('config.json');
$config->setEncrypted('api_key', 'sk-secret', $key);
$config->setEncrypted('database.password', 'dbpass', $key);

// Other values remain unencrypted
$config->set('app.name', 'My App');
```

## Reading Encrypted Values

```php
// Automatic decryption when key is provided
$config = Config::encrypted('config.json', $key);
$password = $config->get('database.password'); // Returns decrypted value

// Without key, encrypted values remain as-is
$config = Config::load('config.json');
$password = $config->get('database.password'); // Returns "ENC:abc123..."
```

## Key Management

```php
use Cline\Ferret\Encryption\Key;

// Generate new key
$key = Key::generate();
echo $key->toString(); // Base64 encoded key

// Load from string
$key = Key::fromString('base64-encoded-key');

// Load from file
$key = Key::fromFile('/path/to/key.txt');

// Load from environment
$key = Key::fromEnv('CONFIG_ENCRYPTION_KEY');
```

## Re-encrypting

```php
// Rotate encryption key
$oldKey = Key::fromEnv('OLD_KEY');
$newKey = Key::generate();

$config = Config::encrypted('config.json', $oldKey);
$config->reEncrypt($newKey);
$config->save();

// Update your environment with new key
echo "New key: " . $newKey->toString();
```

## Encryption Algorithms

```php
use Cline\Ferret\Encryption\Algorithm;

// Default: AES-256-GCM
$config = Config::encrypted('config.json', $key);

// Specify algorithm
$config = Config::encrypted('config.json', $key, Algorithm::AES256CBC);
```

<a id="doc-docs-format-conversion"></a>

Convert configuration files between different formats.

**Use case:** Migrating configurations, generating configs for different tools, or normalizing config formats across projects.

## Basic Conversion

```php
use Cline\Ferret\Config;

// Load from one format
$config = Config::load('config.json');

// Save to another format
$config->save('config.yaml');
$config->save('config.toml');
$config->save('config.xml');
```

## Direct Conversion

```php
use Cline\Ferret\Converter;

// Convert file directly
Converter::convert('config.json', 'config.yaml');

// Convert string content
$yaml = Converter::fromJson($jsonString)->toYaml();
$toml = Converter::fromYaml($yamlString)->toToml();
```

## Format-Specific Options

### JSON Options

```php
$config->save('config.json', [
    'pretty' => true,        // Pretty print
    'flags' => JSON_UNESCAPED_SLASHES,
]);
```

### YAML Options

```php
$config->save('config.yaml', [
    'inline' => 4,           // Inline depth
    'indent' => 2,           // Indentation spaces
]);
```

### XML Options

```php
$config->save('config.xml', [
    'rootElement' => 'config',
    'version' => '1.0',
    'encoding' => 'UTF-8',
]);
```

## Batch Conversion

```php
use Cline\Ferret\Converter;

// Convert all files in a directory
Converter::convertDirectory(
    source: 'config/',
    destination: 'config-yaml/',
    targetFormat: 'yaml'
);
```

## Handling Format Limitations

Some formats have limitations:

```php
// XML doesn't support numeric keys well
// TOML doesn't support null values
// INI only supports 2 levels of nesting

// Use format-specific adapters for edge cases
$config = Config::load('complex.json');
$config->save('simple.ini', [
    'flatten' => true,  // Flatten nested structures
]);
```

<a id="doc-docs-typed-accessors"></a>

Type-safe configuration value access with automatic casting.

**Use case:** Ensuring type safety when reading configuration values, providing defaults, and validating configuration structure.

## Basic Typed Access

```php
use Cline\Ferret\Config;

$config = Config::load('config.json');

// String values
$host = $config->getString('database.host');
$host = $config->getString('database.host', 'localhost'); // with default

// Integer values
$port = $config->getInt('database.port');
$port = $config->getInt('database.port', 3306);

// Float values
$timeout = $config->getFloat('connection.timeout');
$timeout = $config->getFloat('connection.timeout', 30.0);

// Boolean values
$debug = $config->getBool('app.debug');
$debug = $config->getBool('app.debug', false);

// Array values
$hosts = $config->getArray('cluster.hosts');
$hosts = $config->getArray('cluster.hosts', []);
```

## Strict Mode

```php
// Throws exception if type doesn't match
$port = $config->getInt('database.port', strict: true);

// Throws exception if key doesn't exist
$host = $config->getString('database.host', required: true);
```

## Nullable Values

```php
// Returns null if not found (no exception)
$optional = $config->getStringOrNull('optional.setting');
$maybeInt = $config->getIntOrNull('optional.count');
```

## Complex Types

```php
// Get nested configuration as Config object
$database = $config->getConfig('database');
$host = $database->getString('host');

// Get as associative array
$settings = $config->getAssoc('app.settings');

// Get as list (indexed array)
$items = $config->getList('app.items');
```

## Type Coercion

```php
// Automatic type coercion
$config->set('port', '3306');
$port = $config->getInt('port'); // Returns 3306 as integer

// String "true"/"false" to boolean
$config->set('debug', 'true');
$debug = $config->getBool('debug'); // Returns true

// Numeric strings to numbers
$config->set('rate', '0.5');
$rate = $config->getFloat('rate'); // Returns 0.5
```

## Validation

```php
// Validate entire configuration structure
$config->validate([
    'database.host' => 'string|required',
    'database.port' => 'int|min:1|max:65535',
    'app.debug' => 'bool',
    'app.env' => 'string|in:local,staging,production',
]);
```
