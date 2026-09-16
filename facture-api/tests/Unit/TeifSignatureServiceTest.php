<?php

namespace Tests\Unit;

use App\Services\Xml\TeifSignatureService;
use Tests\TestCase;
use RuntimeException;

class TeifSignatureServiceTest extends TestCase
{
    private string $sampleTeifXml;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_can_read_signer_certificate_info(): void
    {
        $certPath = __DIR__ . '/../../certs/test-cert.pem';
        $keyPath = __DIR__ . '/../../certs/test-key.pem';

        $service = new TeifSignatureService($certPath, $keyPath);
        $info = $service->getSignerCertificateInfo();

        $this->assertIsArray($info);
        $this->assertEquals('1234567R', $info['common_name']);
        $this->assertEquals('Ma Societe Test', $info['organization']);
        $this->assertEquals('TN', $info['country']);
        $this->assertNotEmpty($info['cert_digest_sha1']);
        $this->assertNotEmpty($info['cert_digest_sha256']);
        $this->assertNotNull($info['valid_from']);
        $this->assertNotNull($info['valid_to']);
    }

    public function test_sign_generates_valid_xades_b_enveloped_structure(): void
    {
        $certPath = __DIR__ . '/../../certs/test-cert.pem';
        $keyPath = __DIR__ . '/../../certs/test-key.pem';

        $service = new TeifSignatureService($certPath, $keyPath);
        $signedXml = $service->sign($this->sampleTeifXml);

        $this->assertNotEmpty($signedXml);
        $this->assertStringContainsString('<ds:Signature', $signedXml);
        $this->assertStringContainsString('Id="SigFrs"', $signedXml);
        $this->assertStringContainsString('<ds:SignedInfo>', $signedXml);
        $this->assertStringContainsString('Id="r-id-frs"', $signedXml);
        $this->assertStringContainsString('URI="#xades-SigFrs"', $signedXml);
        $this->assertStringContainsString('Id="value-SigFrs"', $signedXml);
        $this->assertStringContainsString('<xades:SignedProperties', $signedXml);
        $this->assertStringContainsString('Id="xades-SigFrs"', $signedXml);
        $this->assertStringContainsString('urn:2.16.788.1.2.1.3', $signedXml);
        $this->assertStringContainsString('<xades:ClaimedRole>Fournisseur</xades:ClaimedRole>', $signedXml);
    }

    public function test_sign_fails_with_missing_certificate(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Certificat ou clé introuvable');

        $service = new TeifSignatureService('/non/existent/cert.pem', '/non/existent/key.pem');
        $service->sign($this->sampleTeifXml);
    }
}
