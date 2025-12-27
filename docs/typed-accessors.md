---
title: Typed Accessors
description: Type-safe configuration value access with automatic casting.
---

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
