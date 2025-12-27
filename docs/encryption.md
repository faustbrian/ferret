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
