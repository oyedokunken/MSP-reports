<?php

namespace Tests\Unit;

use App\Services\SamlAuthenticationService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SamlAuthenticationServiceTest extends TestCase
{
    private SamlAuthenticationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SamlAuthenticationService();
    }

    public function test_format_certificate_adds_headers(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('formatCertificate');
        $method->setAccessible(true);

        $rawCert = 'MIICpDCCAYwCCQDU+pQ4P4gD8TANBgkqhkiG9w0BAQsFADAUMRIwEAYDVQQDDAls';
        $formatted = $method->invoke($this->service, $rawCert);

        $this->assertStringStartsWith('-----BEGIN CERTIFICATE-----', $formatted);
        $this->assertStringEndsWith('-----END CERTIFICATE-----', trim($formatted));
    }

    public function test_format_certificate_preserves_existing_headers(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('formatCertificate');
        $method->setAccessible(true);

        $certWithHeaders = "-----BEGIN CERTIFICATE-----\nMIICpDCCAYw\n-----END CERTIFICATE-----";
        $formatted = $method->invoke($this->service, $certWithHeaders);

        // Should not double-wrap
        $this->assertEquals(1, substr_count($formatted, '-----BEGIN CERTIFICATE-----'));
        $this->assertEquals(1, substr_count($formatted, '-----END CERTIFICATE-----'));
    }

    public function test_extract_user_attributes_from_saml_response(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('extractUserAttributes');
        $method->setAccessible(true);

        // Create a mock SAML response XML
        $samlXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion">
    <saml:Assertion>
        <saml:Subject>
            <saml:NameID>user@example.com</saml:NameID>
        </saml:Subject>
        <saml:AttributeStatement>
            <saml:Attribute Name="email">
                <saml:AttributeValue>user@example.com</saml:AttributeValue>
            </saml:Attribute>
            <saml:Attribute Name="firstName">
                <saml:AttributeValue>John</saml:AttributeValue>
            </saml:Attribute>
            <saml:Attribute Name="lastName">
                <saml:AttributeValue>Doe</saml:AttributeValue>
            </saml:Attribute>
        </saml:AttributeStatement>
    </saml:Assertion>
</samlp:Response>
XML;

        $doc = new \DOMDocument();
        $doc->loadXML($samlXml);

        $attributes = $method->invoke($this->service, $doc);

        $this->assertIsArray($attributes);
        $this->assertArrayHasKey('email', $attributes);
        $this->assertEquals('user@example.com', $attributes['email']);
    }
}
