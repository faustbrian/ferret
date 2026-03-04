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
