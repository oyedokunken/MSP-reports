<?php

namespace Tests\Unit;

use App\Services\SamlAuthenticationService;
use Tests\TestCase;
use ReflectionClass;

class SamlAuthenticationServiceTest extends TestCase
{
    private SamlAuthenticationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SamlAuthenticationService();
    }

    public function test_format_certificate_removes_headers_for_metadata(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('formatCertificate');
        $method->setAccessible(true);

        $rawCert = "-----BEGIN CERTIFICATE-----\nMIICpDCCAYwCCQDU+pQ4P4gD8TANBgkqhkiG9w0BAQsFADAUMRIwEAYDVQQDDAls\n-----END CERTIFICATE-----";
        $formatted = $method->invoke($this->service, $rawCert);

        $this->assertStringNotContainsString('-----BEGIN CERTIFICATE-----', $formatted);
        $this->assertStringNotContainsString('-----END CERTIFICATE-----', $formatted);
        $this->assertStringNotContainsString("\n", $formatted);
    }

    public function test_format_certificate_for_validation_adds_headers(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('formatCertificateForValidation');
        $method->setAccessible(true);

        $rawCert = 'MIICpDCCAYwCCQDU+pQ4P4gD8TANBgkqhkiG9w0BAQsFADAUMRIwEAYDVQQDDAls';
        $formatted = $method->invoke($this->service, $rawCert);

        $this->assertStringStartsWith('-----BEGIN CERTIFICATE-----', $formatted);
        $this->assertStringEndsWith('-----END CERTIFICATE-----', trim($formatted));
    }

    public function test_extract_attributes_from_saml_response(): void
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('extractAttributes');
        $method->setAccessible(true);

        // Create a mock SAML response XML
        $samlXml = <<<XML
<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion">
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
XML;

        $assertion = new \SimpleXMLElement($samlXml);

        // Mock config for attributes
        config(['saml.attributes.email' => 'email']);
        config(['saml.attributes.firstname' => 'firstName']);
        config(['saml.attributes.lastname' => 'lastName']);

        $attributes = $method->invoke($this->service, $assertion);

        $this->assertIsArray($attributes);
        $this->assertArrayHasKey('email', $attributes);
        $this->assertEquals('user@example.com', $attributes['email']);
    }
}
