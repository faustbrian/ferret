<?php

declare(strict_types=1);

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
    | This option controls how Ferret traverses the directory tree when
    | searching for configuration files. The strategy determines whether
    | the search is limited to the starting directory, ascends to find
    | project boundaries, or continues to the user's home directory.
    |
    | Supported strategies: "none", "project", "global"
    |
    */

    'search_strategy' => 'none',

    /*
    |--------------------------------------------------------------------------
    | Stop Directory
    |--------------------------------------------------------------------------
    |
    | When using the "global" search strategy, this option specifies the
    | directory at which the upward traversal should stop. If this value
    | is null, the search will continue until reaching the user's home
    | directory, providing maximum configuration discovery flexibility.
    |
    */

    'stop_dir' => null,

    /*
    |--------------------------------------------------------------------------
    | Search Places
    |--------------------------------------------------------------------------
    |
    | Here you may define custom locations where Ferret should look for
    | configuration files in each directory during traversal. When this
    | array is empty, Ferret will use its default search patterns:
    |
    | - package.json (with module property)
    | - .{module}rc (extensionless, JSON or YAML)
    | - .{module}rc.{json,yaml,yml,php,ini}
    | - .config/{module}rc and variants
    | - {module}.config.{php,json}
    |
    | You may use the {module} placeholder which will be replaced with
    | the module name during the search process. This allows you to
    | define flexible, reusable search patterns for your modules.
    |
    */

    'search_places' => [],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | This option controls whether Ferret should cache the results of its
    | search and load operations. When enabled, caching significantly
    | improves performance by avoiding repeated filesystem traversal
    | and file parsing operations on subsequent requests.
    |
    */

    'cache' => true,

    /*
    |--------------------------------------------------------------------------
    | Ignore Empty Files
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Ferret will skip any configuration files
    | that are empty or contain only whitespace. This allows the search to
    | continue ascending to parent directories until a valid configuration
    | is found, rather than stopping at the first empty file encountered.
    |
    */

    'ignore_empty' => true,

    /*
    |--------------------------------------------------------------------------
    | Encryption Settings
    |--------------------------------------------------------------------------
    |
    | Below are all of the encryption-related settings for Ferret. These
    | options control how configuration files are encrypted and decrypted
    | when using the encrypt() and decrypt() methods, including cipher
    | selection, key management, and environment-specific file handling.
    |
    */

    'encryption' => [

        /*
        |--------------------------------------------------------------------------
        | Use Application Key
        |--------------------------------------------------------------------------
        |
        | This option determines whether the encrypt and decrypt commands should
        | use the application's APP_KEY environment variable as the default
        | encryption key when no explicit --key option is provided. This can
        | also be enabled on a per-command basis using the --app-key flag.
        |
        | WARNING: When using APP_KEY for encryption, the encrypted files can
        | only be decrypted in environments that share the same APP_KEY value.
        | This coupling may have security and deployment implications that you
        | should carefully consider before enabling this functionality.
        |
        */

        'use_app_key' => false,

        /*
        |--------------------------------------------------------------------------
        | Default Cipher
        |--------------------------------------------------------------------------
        |
        | This option specifies the default encryption cipher that Ferret will
        | use when encrypting configuration files. Laravel supports both AES-256
        | and AES-128 encryption ciphers, which offer different key lengths
        | and performance characteristics for your security requirements.
        |
        | Supported ciphers: "AES-256-CBC", "AES-128-CBC"
        |
        */

        'cipher' => 'AES-256-CBC',

        /*
        |--------------------------------------------------------------------------
        | Environment Style
        |--------------------------------------------------------------------------
        |
        | This option controls how environment-specific encrypted configuration
        | files are organized within your project structure. You may choose to
        | append the environment name as a suffix to the filename, or organize
        | environment files into separate subdirectories for better isolation.
        |
        | Supported styles: "suffix", "directory"
        |
        */

        'env_style' => 'suffix',

        /*
        |--------------------------------------------------------------------------
        | Environment Directory
        |--------------------------------------------------------------------------
        |
        | When using the "directory" environment style, this option specifies
        | the base directory where environment-specific subdirectories will
        | be located. The path may be relative to the configuration file's
        | directory or an absolute path for more flexible organization.
        |
        | For example, with env_directory set to "environments" and an env
        | value of "production", a file at config/app.json would resolve
        | to config/environments/production/app.json during encryption.
        |
        | When this value is null, environment directories will be created
        | as siblings to the configuration file, such as config/production
        | for files originally located in the config directory.
        |
        */

        'env_directory' => null,

    ],

];

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
// Here endeth thy configuration, noble developer!                            //
// Beyond: code so wretched, even wyrms learned the scribing arts.            //
// Forsooth, they but penned "// TODO: remedy ere long"                       //
// Three realms have fallen since...                                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
//                                                  .~))>>                    //
//                                                 .~)>>                      //
//                                               .~))))>>>                    //
//                                             .~))>>             ___         //
//                                           .~))>>)))>>      .-~))>>         //
//                                         .~)))))>>       .-~))>>)>          //
//                                       .~)))>>))))>>  .-~)>>)>              //
//                   )                 .~))>>))))>>  .-~)))))>>)>             //
//                ( )@@*)             //)>))))))  .-~))))>>)>                 //
//              ).@(@@               //))>>))) .-~))>>)))))>>)>               //
//            (( @.@).              //))))) .-~)>>)))))>>)>                   //
//          ))  )@@*.@@ )          //)>))) //))))))>>))))>>)>                 //
//       ((  ((@@@.@@             |/))))) //)))))>>)))>>)>                    //
//      )) @@*. )@@ )   (\_(\-\b  |))>)) //)))>>)))))))>>)>                   //
//    (( @@@(.@(@ .    _/`-`  ~|b |>))) //)>>)))))))>>)>                      //
//     )* @@@ )@*     (@)  (@) /\b|))) //))))))>>))))>>                       //
//   (( @. )@( @ .   _/  /    /  \b)) //))>>)))))>>>_._                       //
//    )@@ (@@*)@@.  (6///6)- / ^  \b)//))))))>>)))>>   ~~-.                   //
// ( @jgs@@. @@@.*@_ VvvvvV//  ^  \b/)>>))))>>      _.     `bb                //
//  ((@@ @@@*.(@@ . - | o |' \ (  ^   \b)))>>        .'       b`,             //
//   ((@@).*@@ )@ )   \^^^/  ((   ^  ~)_        \  /           b `,           //
//     (@@. (@@ ).     `-'   (((   ^    `\ \ \ \ \|             b  `.         //
//       (*.@*              / ((((        \| | |  \       .       b `.        //
//                         / / (((((  \    \ /  _.-~\     Y,      b  ;        //
//                        / / / (((((( \    \.-~   _.`" _.-~`,    b  ;        //
//                       /   /   `(((((()    )    (((((~      `,  b  ;        //
//                     _/  _/      `"""/   /'                  ; b   ;        //
//                 _.-~_.-~           /  /'                _.'~bb _.'         //
//               ((((~~              / /'              _.'~bb.--~             //
//                                  ((((          __.-~bb.-~                  //
//                                              .'  b .~~                     //
//                                              :bb ,'                        //
//                                              ~~~~                          //
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
