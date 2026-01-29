<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default LDAP Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the LDAP connections below you wish
    | to use as your default connection for all LDAP operations. Of
    | course you may add as many connections as you'd like below.
    |
    */

    'default' => env('LDAP_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | LDAP Connections
    |--------------------------------------------------------------------------
    |
    | Below you may configure each LDAP connection your application requires
    | access to. Be sure to include a valid base DN - otherwise you may
    | not receive any results when performing LDAP search operations.
    |
    */

    'connections' => [

        'default' => [
            'hosts' => [env('LDAP_HOST', '127.0.0.1')],
            'username' => env('LDAP_USERNAME', 'cn=user,dc=local,dc=com'),
            'password' => env('LDAP_PASSWORD', 'secret'),
            'port' => env('LDAP_PORT', 389),
            'base_dn' => env('LDAP_BASE_DN', 'dc=local,dc=com'),
            'timeout' => env('LDAP_TIMEOUT', 5),
            'use_ssl' => env('LDAP_SSL', false),
            'use_tls' => env('LDAP_TLS', false),

            /*
            |--------------------------------------------------------------------------
            | Custom LDAP Options
            |--------------------------------------------------------------------------
            |
            | Custom LDAP options can be set here. These will be set on the underlying
            | LDAP connection. For a list of available options, see:
            | https://www.php.net/manual/en/function.ldap-set-option.php
            |
            */

            'options' => [
                // LDAP_OPT_PROTOCOL_VERSION => 3,
                // LDAP_OPT_REFERRALS => 0,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | LDAP Logging
    |--------------------------------------------------------------------------
    |
    | When LDAP logging is enabled, all LDAP search and authentication
    | operations are logged using the default application logging
    | driver. This can assist in debugging issues and more.
    |
    */

    'logging' => env('LDAP_LOGGING', true),

    /*
    |--------------------------------------------------------------------------
    | LDAP Cache
    |--------------------------------------------------------------------------
    |
    | LDAP caching enables the caching of paginated and search results. This
    | is great for running expensive operations that may take many seconds
    | to complete, such as a pagination request.
    |
    */

    'cache' => [
        'enabled' => env('LDAP_CACHE', false),
        'driver' => env('CACHE_DRIVER', 'file'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LDAP User Attributes
    |--------------------------------------------------------------------------
    |
    | Here you may configure the LDAP attribute mappings for user lookup.
    |
    */

    'user_attributes' => [
        'username' => env('LDAP_USER_ATTRIBUTE_USERNAME', 'samaccountname'),
        'email' => env('LDAP_USER_ATTRIBUTE_EMAIL', 'mail'),
        'firstname' => env('LDAP_USER_ATTRIBUTE_FIRSTNAME', 'givenname'),
        'lastname' => env('LDAP_USER_ATTRIBUTE_LASTNAME', 'sn'),
        'displayname' => env('LDAP_USER_ATTRIBUTE_DISPLAYNAME', 'displayname'),
    ],

];
