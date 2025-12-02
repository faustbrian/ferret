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
