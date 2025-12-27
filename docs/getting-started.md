---
title: Getting Started
description: Install and start using Ferret for configuration management in PHP.
---

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

- [Directory Loading](/ferret/directory-loading/) - Load entire configuration directories
- [Format Conversion](/ferret/format-conversion/) - Convert between formats
- [Typed Accessors](/ferret/typed-accessors/) - Type-safe value access
- [Encryption](/ferret/encryption/) - Protect sensitive values
