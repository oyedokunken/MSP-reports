<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    'oauth' => [
        'enabled' => env('OAUTH_ENABLED', false),
        'auto_provision' => env('OAUTH_AUTO_PROVISION', true),
        'microsoft' => [
            'scopes' => explode(' ', env('AZURE_SCOPES', 'openid profile email User.Read')),
        ],
        'keycloak' => [
            'enabled' => env('KEYCLOAK_ENABLED', false),
        ],
    ],

    'saml' => [
        'enabled' => env('SAML_ENABLED', false),
    ],

    'ldap' => [
        'enabled' => env('LDAP_ENABLED', false),
    ],
];
