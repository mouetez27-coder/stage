<?php

namespace Tests\Unit;

use App\Services\Xml\TeifSignatureService;
use App\Services\Xml\TeifSignatureVerifierService;
use Tests\TestCase;

class TeifSignatureVerifierServiceTest extends TestCase
{
    private TeifSignatureService $signer;
    private TeifSignatureVerifierService $verifier;
    private string $sampleTeifXml;

    protected function setUp(): void
    {
        parent::setUp();

        $certPath = __DIR__ . '/../../certs/test-cert.pem';
        $keyPath = __DIR__ . '/../../certs/test-key.pem';

        $this->signer = new TeifSignatureService($certPath, $keyPath);
        $this->verifier = new TeifSignatureVerifierService();

        $this->sampleTeifXml = '<?xml version="1.0" encoding="UTF-8"?>
<TEIF version="1.9.0" controlingAgency="TTN">
  <InvoiceHeader>
    <MessageSenderIdentifier type="I-01">1234567A</MessageSenderIdentifier>
    <MessageRecieverIdentifier type="I-01">7654321B</MessageRecieverIdentifier>
  </InvoiceHeader>
  <InvoiceBody>
    <Bgm>
      <DocumentIdentifier>FAC-2026-TEST</DocumentIdentifier>
    </Bgm>
  </InvoiceBody>
</TEIF>';
    }

    public function test_verify_valid_signed_xml(): void
    {
        $signedXml = $this->signer->sign($this->sampleTeifXml);
        $report = $this->verifier->verify($signedXml);

        $this->assertTrue($report['is_valid']);
        $this->assertEmpty($report['errors']);
        $this->assertTrue($report['checks']['has_signature']);
        $this->assertTrue($report['checks']['rsa_signature']);
        $this->assertTrue($report['checks']['document_digest']);
        $this->assertTrue($report['checks']['signed_properties_digest']);
        $this->assertTrue($report['checks']['certificate_digest']);
        $this->assertEquals('1234567R', $report['signer']['common_name']);
        $this->assertEquals('urn:2.16.788.1.2.1.3', $report['metadata']['policy_oid']);
        $this->assertEquals('Fournisseur', $report['metadata']['claimed_role']);
    }

    public function test_verify_detects_document_tampering(): void
    {
        $signedXml = $this->signer->sign($this->sampleTeifXml);

        // Altération du document après signature (ex: modification du numéro ou montant)
        $tamperedXml = str_replace('FAC-2026-TEST', 'FAC-2026-FRAUD', $signedXml);

        $report = $this->verifier->verify($tamperedXml);

        $this->assertFalse($report['is_valid']);
        $this->assertFalse($report['checks']['document_digest']);
        $this->assertNotEmpty($report['errors']);
        $this->assertStringContainsString('Altération détectée', $report['errors'][0]);
    }

    public function test_verify_detects_signature_tampering(): void
    {
        $signedXml = $this->signer->sign($this->sampleTeifXml);

        // Altérer un caractère de la signature RSA
        $tamperedXml = preg_replace(
            '/(<ds:SignatureValue[^>]*>)(.)/s',
            '${1}A',
            $signedXml
        );

        $report = $this->verifier->verify($tamperedXml);

        $this->assertFalse($report['is_valid']);
        $this->assertFalse($report['checks']['rsa_signature']);
    }

    public function test_verify_returns_error_for_unsigned_xml(): void
    {
        $report = $this->verifier->verify($this->sampleTeifXml);

        $this->assertFalse($report['is_valid']);
        $this->assertFalse($report['checks']['has_signature']);
        $this->assertStringContainsString('Aucun bloc <ds:Signature>', $report['errors'][0]);
    }

    public function test_verify_returns_error_for_malformed_xml(): void
    {
        $report = $this->verifier->verify('<malformed><unclosed>');

        $this->assertFalse($report['is_valid']);
        $this->assertStringContainsString('mal formé', $report['errors'][0]);
    }
}
