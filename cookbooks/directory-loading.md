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
