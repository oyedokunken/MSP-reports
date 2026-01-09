<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class SamlAuthenticationService
{
    /**
     * Check if SAML is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return config('saml.enabled', false) === true || config('saml.enabled', false) === 'true';
    }

    /**
     * Check if auto-provisioning is enabled
     *
     * @return bool
     */
    public function isAutoProvisionEnabled(): bool
    {
        return config('saml.settings.auto_provision', true) === true;
    }

    /**
     * Validate SAML configuration
     *
     * @return array
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        // Validate IdP configuration
        if (empty(config('saml.idp.entity_id'))) {
            $errors[] = 'SAML_IDP_ENTITY_ID is not configured';
        }

        if (empty(config('saml.idp.sso_url'))) {
            $errors[] = 'SAML_IDP_SSO_URL is not configured';
        }

        if (empty(config('saml.idp.x509_cert'))) {
            $errors[] = 'SAML_IDP_x509_CERT is not configured';
        }

        // Validate SP configuration
        if (empty(config('saml.sp.entity_id'))) {
            $errors[] = 'SAML_SP_ENTITY_ID is not configured';
        }

        if (empty(config('saml.sp.acs_url'))) {
            $errors[] = 'SAML_SP_ACS_URL is not configured';
        }

        if (empty(config('saml.sp.x509_cert'))) {
            $errors[] = 'SAML_SP_x509_CERT is not configured';
        }

        if (empty(config('saml.sp.private_key'))) {
            $errors[] = 'SAML_SP_PRIVATE_KEY is not configured';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Generate SAML metadata XML
     *
     * @return string
     */
    public function generateMetadata(): string
    {
        $entityId = config('saml.sp.entity_id');
        $acsUrl = config('saml.sp.acs_url');
        $slsUrl = config('saml.sp.sls_url');
        $cert = $this->formatCertificate(config('saml.sp.x509_cert'));
        
        $orgName = config('saml.settings.organization.name');
        $orgDisplayName = config('saml.settings.organization.displayname');
        $orgUrl = config('saml.settings.organization.url');
        
        $contactName = config('saml.settings.contact_person.technical.givenName');
        $contactEmail = config('saml.settings.contact_person.technical.emailAddress');

        $metadata = <<<XML
<?xml version="1.0"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
                     xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
                     entityID="{$entityId}">
    <md:SPSSODescriptor AuthnRequestsSigned="true" WantAssertionsSigned="true" protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <md:KeyDescriptor use="signing">
            <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                <ds:X509Data>
                    <ds:X509Certificate>{$cert}</ds:X509Certificate>
                </ds:X509Data>
            </ds:KeyInfo>
        </md:KeyDescriptor>
        <md:KeyDescriptor use="encryption">
            <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                <ds:X509Data>
                    <ds:X509Certificate>{$cert}</ds:X509Certificate>
                </ds:X509Data>
            </ds:KeyInfo>
        </md:KeyDescriptor>
        <md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"
                                Location="{$slsUrl}"/>
        <md:NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress</md:NameIDFormat>
        <md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
                                     Location="{$acsUrl}"
                                     index="1"/>
    </md:SPSSODescriptor>
    <md:Organization>
        <md:OrganizationName xml:lang="en-US">{$orgName}</md:OrganizationName>
        <md:OrganizationDisplayName xml:lang="en-US">{$orgDisplayName}</md:OrganizationDisplayName>
        <md:OrganizationURL xml:lang="en-US">{$orgUrl}</md:OrganizationURL>
    </md:Organization>
    <md:ContactPerson contactType="technical">
        <md:GivenName>{$contactName}</md:GivenName>
        <md:EmailAddress>{$contactEmail}</md:EmailAddress>
    </md:ContactPerson>
</md:EntityDescriptor>
XML;

        return $metadata;
    }

    /**
     * Generate SAML AuthnRequest
     *
     * @return array [url, request_id]
     */
    public function generateAuthnRequest(): array
    {
        $requestId = $this->generateUniqueId();
        $issueInstant = gmdate('Y-m-d\TH:i:s\Z');
        $entityId = config('saml.sp.entity_id');
        $acsUrl = config('saml.sp.acs_url');
        $idpSsoUrl = config('saml.idp.sso_url');

        $authnRequest = <<<XML
<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                    ID="{$requestId}"
                    Version="2.0"
                    IssueInstant="{$issueInstant}"
                    Destination="{$idpSsoUrl}"
                    AssertionConsumerServiceURL="{$acsUrl}"
                    ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">
    <saml:Issuer>{$entityId}</saml:Issuer>
    <samlp:NameIDPolicy Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress" AllowCreate="true"/>
</samlp:AuthnRequest>
XML;

        // Encode and deflate the request
        $encodedRequest = base64_encode(gzdeflate($authnRequest));

        // Build redirect URL
        $redirectUrl = $idpSsoUrl . '?SAMLRequest=' . urlencode($encodedRequest);

        // Store request ID for validation
        Cache::put('saml_request_' . $requestId, true, now()->addMinutes(5));

        return [
            'url' => $redirectUrl,
            'request_id' => $requestId
        ];
    }

    /**
     * Parse and validate SAML response
     *
     * @param string $samlResponse Base64 encoded SAML response
     * @return array|null User data if valid, null if invalid
     */
    public function parseResponse(string $samlResponse): ?array
    {
        try {
            // Decode the SAML response
            $decodedResponse = base64_decode($samlResponse);
            
            if (!$decodedResponse) {
                Log::error('SAML: Failed to decode response');
                return null;
            }

            // Parse XML
            $xml = new \SimpleXMLElement($decodedResponse);
            $xml->registerXPathNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
            $xml->registerXPathNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

            // Validate signature
            if (!$this->validateSignature($decodedResponse)) {
                Log::error('SAML: Signature validation failed');
                return null;
            }

            // Extract assertion
            $assertions = $xml->xpath('//saml:Assertion');
            if (empty($assertions)) {
                Log::error('SAML: No assertion found in response');
                return null;
            }

            $assertion = $assertions[0];

            // Validate assertion
            if (!$this->validateAssertion($assertion)) {
                Log::error('SAML: Assertion validation failed');
                return null;
            }

            // Extract user attributes
            $userData = $this->extractAttributes($assertion);

            if (!$userData) {
                Log::error('SAML: Failed to extract user attributes');
                return null;
            }

            Log::info('SAML: Response parsed successfully', [
                'email' => $userData['email']
            ]);

            return $userData;

        } catch (Exception $e) {
            Log::error('SAML: Failed to parse response', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Validate SAML signature
     *
     * @param string $xml
     * @return bool
     */
    protected function validateSignature(string $xml): bool
    {
        try {
            // In production, use proper XML signature validation library
            // For now, we'll do basic certificate validation
            $idpCert = config('saml.idp.x509_cert');
            
            if (empty($idpCert)) {
                return false;
            }

            // TODO: Implement proper XML signature validation using xmlseclibs
            // This is a placeholder that should be replaced with proper validation
            
            return true;

        } catch (Exception $e) {
            Log::error('SAML: Signature validation error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate SAML assertion
     *
     * @param \SimpleXMLElement $assertion
     * @return bool
     */
    protected function validateAssertion(\SimpleXMLElement $assertion): bool
    {
        try {
            // Check assertion ID for replay attacks
            $assertionId = (string)$assertion['ID'];
            if (Cache::has('saml_assertion_' . $assertionId)) {
                Log::error('SAML: Replay attack detected', ['assertion_id' => $assertionId]);
                return false;
            }
            Cache::put('saml_assertion_' . $assertionId, true, now()->addHours(24));

            // Validate NotBefore and NotOnOrAfter
            $conditions = $assertion->xpath('saml:Conditions');
            if (!empty($conditions)) {
                $condition = $conditions[0];
                $notBefore = (string)$condition['NotBefore'];
                $notOnOrAfter = (string)$condition['NotOnOrAfter'];

                $now = time();
                if ($notBefore && strtotime($notBefore) > $now) {
                    Log::error('SAML: Assertion not yet valid');
                    return false;
                }

                if ($notOnOrAfter && strtotime($notOnOrAfter) <= $now) {
                    Log::error('SAML: Assertion expired');
                    return false;
                }

                // Validate audience restriction
                $audiences = $condition->xpath('saml:AudienceRestriction/saml:Audience');
                if (!empty($audiences)) {
                    $audience = (string)$audiences[0];
                    $spEntityId = config('saml.sp.entity_id');
                    if ($audience !== $spEntityId) {
                        Log::error('SAML: Audience restriction failed', [
                            'expected' => $spEntityId,
                            'received' => $audience
                        ]);
                        return false;
                    }
                }
            }

            return true;

        } catch (Exception $e) {
            Log::error('SAML: Assertion validation error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Extract user attributes from SAML assertion
     *
     * @param \SimpleXMLElement $assertion
     * @return array|null
     */
    protected function extractAttributes(\SimpleXMLElement $assertion): ?array
    {
        try {
            $attributes = [];
            $attributeStatements = $assertion->xpath('saml:AttributeStatement/saml:Attribute');

            foreach ($attributeStatements as $attribute) {
                $name = (string)$attribute['Name'];
                $values = $attribute->xpath('saml:AttributeValue');
                if (!empty($values)) {
                    $attributes[$name] = (string)$values[0];
                }
            }

            // Map SAML attributes to user data
            $emailAttr = config('saml.attributes.email');
            $firstnameAttr = config('saml.attributes.firstname');
            $lastnameAttr = config('saml.attributes.lastname');
            $displaynameAttr = config('saml.attributes.displayname');

            $email = $attributes[$emailAttr] ?? null;
            $firstname = $attributes[$firstnameAttr] ?? '';
            $lastname = $attributes[$lastnameAttr] ?? '';
            $displayname = $attributes[$displaynameAttr] ?? trim($firstname . ' ' . $lastname);

            if (!$email) {
                Log::error('SAML: Email attribute not found', [
                    'expected_attribute' => $emailAttr,
                    'available_attributes' => array_keys($attributes)
                ]);
                return null;
            }

            return [
                'email' => strtolower(trim($email)),
                'name' => $displayname ?: $email,
                'firstname' => $firstname,
                'lastname' => $lastname,
            ];

        } catch (Exception $e) {
            Log::error('SAML: Failed to extract attributes', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Format certificate for XML (remove headers and newlines)
     *
     * @param string $cert
     * @return string
     */
    protected function formatCertificate(string $cert): string
    {
        $cert = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'], '', $cert);
        $cert = str_replace(["\r", "\n", " "], '', $cert);
        return $cert;
    }

    /**
     * Generate unique ID for SAML requests
     *
     * @return string
     */
    protected function generateUniqueId(): string
    {
        return '_' . bin2hex(random_bytes(21));
    }
}
