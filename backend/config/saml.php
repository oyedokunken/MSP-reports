<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SAML 2.0 Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SAML 2.0 Single Sign-On authentication.
    | This follows the SAML 2.0 specification for Service Provider (SP) setup.
    |
    */

    'enabled' => env('SAML_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Identity Provider (IdP) Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the external Identity Provider that will authenticate users.
    |
    */

    'idp' => [
        'entity_id' => env('SAML_IDP_ENTITY_ID'),
        'sso_url' => env('SAML_IDP_SSO_URL'),
        'slo_url' => env('SAML_IDP_SLO_URL'),
        'x509_cert' => env('SAML_IDP_x509_CERT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Provider (SP) Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for this application as a SAML Service Provider.
    |
    */

    'sp' => [
        'entity_id' => env('SAML_SP_ENTITY_ID', env('APP_URL') . '/saml/metadata'),
        'acs_url' => env('SAML_SP_ACS_URL', env('APP_URL') . '/api/auth/saml/acs'),
        'sls_url' => env('SAML_SP_SLS_URL', env('APP_URL') . '/api/auth/saml/sls'),
        'x509_cert' => env('SAML_SP_x509_CERT'),
        'private_key' => env('SAML_SP_PRIVATE_KEY'),
        
        // Name ID format
        'name_id_format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
    ],

    /*
    |--------------------------------------------------------------------------
    | SAML Attribute Mapping
    |--------------------------------------------------------------------------
    |
    | Map SAML assertion attributes to user model fields.
    |
    */

    'attributes' => [
        'email' => env('SAML_ATTR_EMAIL', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress'),
        'firstname' => env('SAML_ATTR_FIRSTNAME', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname'),
        'lastname' => env('SAML_ATTR_LASTNAME', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname'),
        'displayname' => env('SAML_ATTR_DISPLAYNAME', 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SAML Settings
    |--------------------------------------------------------------------------
    |
    | Additional SAML configuration options.
    |
    */

    'settings' => [
        // Auto-provision users on first login
        'auto_provision' => env('SAML_AUTO_PROVISION', true),
        
        // Strict mode enforces strict XML validation
        'strict' => env('SAML_STRICT_MODE', true),
        
        // Debug mode logs detailed SAML information
        'debug' => env('SAML_DEBUG', false),
        
        // Security settings
        'security' => [
            'nameIdEncrypted' => false,
            'authnRequestsSigned' => true,
            'logoutRequestSigned' => true,
            'logoutResponseSigned' => true,
            'signMetadata' => true,
            'wantMessagesSigned' => true,
            'wantAssertionsSigned' => true,
            'wantAssertionsEncrypted' => false,
            'wantNameIdEncrypted' => false,
            'requestedAuthnContext' => true,
            'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
            'digestAlgorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
        ],
        
        // Contact information for SP metadata
        'contact_person' => [
            'technical' => [
                'givenName' => env('SAML_CONTACT_NAME', 'Technical Support'),
                'emailAddress' => env('SAML_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS')),
            ],
        ],
        
        // Organization information for SP metadata
        'organization' => [
            'name' => env('APP_NAME', 'MSP Reporting Platform'),
            'displayname' => env('APP_NAME', 'MSP Reporting Platform'),
            'url' => env('APP_URL'),
        ],
    ],
];
