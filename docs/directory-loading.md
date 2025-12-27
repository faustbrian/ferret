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
