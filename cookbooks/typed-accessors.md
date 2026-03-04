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
