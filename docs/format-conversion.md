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
