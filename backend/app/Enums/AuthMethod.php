<?php

declare(strict_types=1);

namespace App\Enums;

enum AuthMethod: string
{
    case LOCAL = 'local';
    case M365 = 'm365';
    case SAML = 'saml';
    case KEYCLOAK = 'keycloak';
    case OIDC = 'oidc';
    case LDAP = 'ldap';
}
