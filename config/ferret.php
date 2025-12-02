<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Search Strategy
    |--------------------------------------------------------------------------
    |
    | Determines how Ferret traverses directories when searching for
    | configuration files. Available strategies:
    |
    | - 'none'    : Only check the starting directory
    | - 'project' : Traverse upward until finding composer.json or package.json
    | - 'global'  : Traverse to home directory, also checks ~/.config/{module}/
    |
    */

    'search_strategy' => 'none',

    /*
    |--------------------------------------------------------------------------
    | Stop Directory
    |--------------------------------------------------------------------------
    |
    | When using the 'global' search strategy, searching will stop at this
    | directory. If not specified, defaults to the user's home directory.
    |
    */

    'stop_dir' => null,

    /*
    |--------------------------------------------------------------------------
    | Search Places
    |--------------------------------------------------------------------------
    |
    | Custom search places to check in each directory. If empty, defaults to:
    |
    | - package.json (with module property)
    | - .{module}rc (extensionless, JSON or YAML)
    | - .{module}rc.{json,yaml,yml,php,ini}
    | - .config/{module}rc and variants
    | - {module}.config.{php,json}
    |
    | Use placeholders like {module} to insert the module name.
    |
    */

    'search_places' => [],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Enable or disable caching of search and load operations. Caching
    | improves performance by avoiding repeated filesystem operations.
    |
    */

    'cache' => true,

    /*
    |--------------------------------------------------------------------------
    | Ignore Empty Files
    |--------------------------------------------------------------------------
    |
    | When enabled, empty configuration files will be skipped during search,
    | allowing the search to continue to the next candidate.
    |
    */

    'ignore_empty' => true,

    /*
    |--------------------------------------------------------------------------
    | Encryption Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the encrypt() and decrypt() methods.
    |
    */

    'encryption' => [

        /*
        |--------------------------------------------------------------------------
        | Default Cipher
        |--------------------------------------------------------------------------
        |
        | The default encryption cipher to use. Supported ciphers:
        | - 'AES-256-CBC' (default, 32-byte key)
        | - 'AES-128-CBC' (16-byte key)
        |
        */

        'cipher' => 'AES-256-CBC',

        /*
        |--------------------------------------------------------------------------
        | Environment Style
        |--------------------------------------------------------------------------
        |
        | How environment-specific files are organized when using the 'env' option:
        |
        | - 'suffix'    : config.production.json (environment as filename suffix)
        | - 'directory' : production/config.json (environment as parent directory)
        |
        */

        'env_style' => 'suffix',

        /*
        |--------------------------------------------------------------------------
        | Environment Directory
        |--------------------------------------------------------------------------
        |
        | When using 'directory' env_style, this is the base directory where
        | environment subdirectories are located. Relative to the config file's
        | directory, or an absolute path.
        |
        | Example: With env_directory = 'environments' and env = 'production':
        |   config/app.json -> config/environments/production/app.json
        |
        | If null, the environment directory is a sibling of the config file:
        |   config/app.json -> config/production/app.json
        |
        */

        'env_directory' => null,

    ],

];
