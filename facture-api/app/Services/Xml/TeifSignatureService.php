<?php

namespace App\Services\Xml;

use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Implémente la signature XAdES-B enveloppée conforme aux
 * "Spécifications techniques de la signature électronique pour la
 * plateforme El Fatoora" (TTN, v3.0).
 *
 * Valeurs fixes imposées par la spec (à ne jamais changer) :
 * - Id du bloc <ds:Signature> = "SigFrs"
 * - Algorithme de signature = RSA-SHA256
 * - Digest = SHA256 (sauf CertDigest qui utilise SHA1, cf. Figure 22 de la spec)
 * - Politique de signature = OID urn:2.16.788.1.2.1.3 (Tunisie TradeNet)
 */
class TeifSignatureService
{
    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    private const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';
    private const C14N_EXC = 'http://www.w3.org/2001/10/xml-exc-c14n#';
    private const SIG_METHOD = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    private const DIGEST_SHA256 = 'http://www.w3.org/2001/04/xmlenc#sha256';
    private const DIGEST_SHA1 = 'http://www.w3.org/2000/09/xmldsig#sha1';

    // Politique de signature officielle TTN (fixe, cf. section 5.5.1 de la spec)
    private const POLICY_OID = 'urn:2.16.788.1.2.1.3';
    private const POLICY_DESCRIPTION = 'Politique de Signature Electronique de Tunisie TradeNet';
    private const POLICY_HASH = 'ZKLu5TojntPu+bUfZyjaEDvkYsAh7eyyV+Hf8nUSQEE=';
    private const POLICY_URL = 'https://www.tradenet.com.tn/Politique_Signature_Electronique_Tunisie_TradeNet.pdf';

    private string $certPath;
    private string $keyPath;
    private ?string $passphrase;

    public function __construct(
        ?string $certPath = null,
        ?string $keyPath = null,
        ?string $passphrase = null
    ) {
        try {
            $configuredCert = $certPath ?? (function_exists('config') ? config('teif.signature_cert_path') : null) ?? 'certs/test-cert.pem';
            $configuredKey  = $keyPath  ?? (function_exists('config') ? config('teif.signature_key_path') : null) ?? 'certs/test-key.pem';
            $this->passphrase = $passphrase ?? (function_exists('config') ? config('teif.signature_passphrase') : null);
        } catch (\Throwable) {
            $configuredCert = $certPath ?? 'certs/test-cert.pem';
            $configuredKey  = $keyPath ?? 'certs/test-key.pem';
            $this->passphrase = $passphrase;
        }

        $this->certPath = $this->resolvePath($configuredCert);
        $this->keyPath  = $this->resolvePath($configuredKey);
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^([a-zA-Z]:[\\\\\/]|\/|\\\\)/', $path)) {
            return $path;
        }
        return function_exists('base_path') ? base_path($path) : $path;
    }

    /**
     * Retourne les métadonnées et la validité du certificat de signature configuré.
     */
    public function getSignerCertificateInfo(): array
    {
        if (!file_exists($this->certPath)) {
            throw new RuntimeException("Certificat introuvable à l'emplacement : {$this->certPath}");
        }

        $certPem = file_get_contents($this->certPath);
        $certData = openssl_x509_parse($certPem, true);
        if (!$certData) {
            throw new RuntimeException('Impossible de lire le certificat X.509.');
        }

        $now = time();
        $validFrom = $certData['validFrom_time_t'] ?? null;
        $validTo = $certData['validTo_time_t'] ?? null;

        $isValid = true;
        if ($validFrom && $now < $validFrom) {
            $isValid = false;
        }
        if ($validTo && $now > $validTo) {
            $isValid = false;
        }

        $derBase64 = $this->pemToDerBase64($certPem);
        $derBinary = base64_decode($derBase64);

        return [
            'subject_dn' => $certData['name'] ?? '',
            'common_name' => $certData['subject']['CN'] ?? '',
            'organization' => $certData['subject']['O'] ?? '',
            'country' => $certData['subject']['C'] ?? '',
            'issuer_dn' => isset($certData['issuer']) ? (is_array($certData['issuer']) ? ($certData['issuer']['name'] ?? json_encode($certData['issuer'])) : $certData['issuer']) : '',
            'serial_number' => $certData['serialNumber'] ?? '0',
            'valid_from' => $validFrom ? gmdate('Y-m-d\TH:i:s\Z', $validFrom) : null,
            'valid_to' => $validTo ? gmdate('Y-m-d\TH:i:s\Z', $validTo) : null,
            'is_valid' => $isValid,
            'cert_digest_sha1' => base64_encode(hash('sha1', $derBinary, true)),
            'cert_digest_sha256' => base64_encode(hash('sha256', $derBinary, true)),
        ];
    }

    /**
     * Signe un XML TEIF et retourne le XML final signé (ds:Signature ajouté en fin de racine).
     */
    public function sign(string $xmlContent): string
    {
        if (!file_exists($this->certPath) || !file_exists($this->keyPath)) {
            throw new RuntimeException(
                "Certificat ou clé introuvable. Attendu : {$this->certPath} et {$this->keyPath}."
            );
        }

        $certPem = file_get_contents($this->certPath);
        $keyPem = file_get_contents($this->keyPath);

        $privateKey = openssl_pkey_get_private($keyPem, $this->passphrase ?? '');
        if (!$privateKey) {
            throw new RuntimeException('Impossible de charger la clé privée : ' . openssl_error_string());
        }

        $certData = openssl_x509_parse($certPem, true);
        if (!$certData) {
            throw new RuntimeException('Impossible de lire le certificat X509.');
        }

        // === 1. Charger le document et canonicaliser AVANT signature ===
        // (équivaut fonctionnellement aux transforms XPath "not(ancestor-or-self::ds:Signature)"
        // et "not(ancestor-or-self::RefTtnVal)" : ces éléments n'existent pas encore ici)
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xmlContent);

        $canonicalDoc = $dom->C14N(true, false);
        $digestRIdFrs = base64_encode(hash('sha256', $canonicalDoc, true));

        // === 2. Construire xades:SignedProperties ===
        $sigDom = new DOMDocument('1.0', 'UTF-8');

        $signingTime = gmdate('Y-m-d\TH:i:s\Z');

        $certDerBase64 = $this->pemToDerBase64($certPem);
        $certDigestSha1 = base64_encode(hash('sha1', base64_decode($certDerBase64), true));
        $issuerSerialV2 = $this->buildIssuerSerialV2($certPem, $certData);

        $signedProperties = $this->buildSignedPropertiesXml(
            $sigDom, $signingTime, $certDigestSha1, $issuerSerialV2
        );

        $sigDom->appendChild($signedProperties);
        $canonicalSignedProperties = $signedProperties->C14N(true, false);
        $digestXadesSigFrs = base64_encode(hash('sha256', $canonicalSignedProperties, true));

        // === 3. Construire ds:SignedInfo ===
        $signedInfoDom = new DOMDocument('1.0', 'UTF-8');
        $signedInfo = $this->buildSignedInfoXml($signedInfoDom, $digestRIdFrs, $digestXadesSigFrs);
        $signedInfoDom->appendChild($signedInfo);

        $canonicalSignedInfo = $signedInfo->C14N(true, false);

        // === 4. Signer SignedInfo avec RSA-SHA256 ===
        $signature = '';
        $ok = openssl_sign($canonicalSignedInfo, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new RuntimeException('Échec de la signature RSA-SHA256 : ' . openssl_error_string());
        }
        $signatureValueB64 = base64_encode($signature);

        // === 5. Assembler le bloc ds:Signature complet dans le document final ===
        $signatureNode = $this->assembleSignatureNode(
            $dom,
            $canonicalSignedInfo,
            $signatureValueB64,
            $certDerBase64,
            $signingTime,
            $certDigestSha1,
            $issuerSerialV2
        );

        $dom->documentElement->appendChild($signatureNode);

        return $dom->saveXML();
    }

    // ===========================
    // Construction des blocs
    // ===========================

    private function buildSignedInfoXml(DOMDocument $dom, string $digestRIdFrs, string $digestXadesSigFrs): DOMElement
    {
        $signedInfo = $dom->createElementNS(self::NS_DS, 'ds:SignedInfo');

        $canon = $dom->createElementNS(self::NS_DS, 'ds:CanonicalizationMethod');
        $canon->setAttribute('Algorithm', self::C14N_EXC);
        $signedInfo->appendChild($canon);

        $sigMethod = $dom->createElementNS(self::NS_DS, 'ds:SignatureMethod');
        $sigMethod->setAttribute('Algorithm', self::SIG_METHOD);
        $signedInfo->appendChild($sigMethod);

        // Reference 1 : r-id-frs (le document entier, hors Signature/RefTtnVal)
        $ref1 = $dom->createElementNS(self::NS_DS, 'ds:Reference');
        $ref1->setAttribute('Id', 'r-id-frs');
        $ref1->setAttribute('Type', '');
        $ref1->setAttribute('URI', '');

        $transforms1 = $dom->createElementNS(self::NS_DS, 'ds:Transforms');

        $t1 = $dom->createElementNS(self::NS_DS, 'ds:Transform');
        $t1->setAttribute('Algorithm', 'http://www.w3.org/TR/1999/REC-xpath-19991116');
        $xpath1 = $dom->createElementNS(self::NS_DS, 'ds:XPath', 'not(ancestor-or-self::ds:Signature)');
        $t1->appendChild($xpath1);
        $transforms1->appendChild($t1);

        $t2 = $dom->createElementNS(self::NS_DS, 'ds:Transform');
        $t2->setAttribute('Algorithm', 'http://www.w3.org/TR/1999/REC-xpath-19991116');
        $xpath2 = $dom->createElementNS(self::NS_DS, 'ds:XPath', 'not(ancestor-or-self::RefTtnVal)');
        $t2->appendChild($xpath2);
        $transforms1->appendChild($t2);

        $t3 = $dom->createElementNS(self::NS_DS, 'ds:Transform');
        $t3->setAttribute('Algorithm', self::C14N_EXC);
        $transforms1->appendChild($t3);

        $ref1->appendChild($transforms1);

        $digestMethod1 = $dom->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod1->setAttribute('Algorithm', self::DIGEST_SHA256);
        $ref1->appendChild($digestMethod1);

        $ref1->appendChild($dom->createElementNS(self::NS_DS, 'ds:DigestValue', $digestRIdFrs));

        $signedInfo->appendChild($ref1);

        // Reference 2 : #xades-SigFrs
        $ref2 = $dom->createElementNS(self::NS_DS, 'ds:Reference');
        $ref2->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        $ref2->setAttribute('URI', '#xades-SigFrs');

        $transforms2 = $dom->createElementNS(self::NS_DS, 'ds:Transforms');
        $t4 = $dom->createElementNS(self::NS_DS, 'ds:Transform');
        $t4->setAttribute('Algorithm', self::C14N_EXC);
        $transforms2->appendChild($t4);
        $ref2->appendChild($transforms2);

        $digestMethod2 = $dom->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod2->setAttribute('Algorithm', self::DIGEST_SHA256);
        $ref2->appendChild($digestMethod2);

        $ref2->appendChild($dom->createElementNS(self::NS_DS, 'ds:DigestValue', $digestXadesSigFrs));

        $signedInfo->appendChild($ref2);

        return $signedInfo;
    }

    private function buildSignedPropertiesXml(
        DOMDocument $dom,
        string $signingTime,
        string $certDigestSha1,
        string $issuerSerialV2Base64,
    ): DOMElement {
        $signedProperties = $dom->createElementNS(self::NS_XADES, 'xades:SignedProperties');
        $signedProperties->setAttribute('Id', 'xades-SigFrs');

        $signedSigProps = $dom->createElementNS(self::NS_XADES, 'xades:SignedSignatureProperties');

        $signedSigProps->appendChild($dom->createElementNS(self::NS_XADES, 'xades:SigningTime', $signingTime));

        $signingCertV2 = $dom->createElementNS(self::NS_XADES, 'xades:SigningCertificateV2');
        $cert = $dom->createElementNS(self::NS_XADES, 'xades:Cert');

        $certDigest = $dom->createElementNS(self::NS_XADES, 'xades:CertDigest');
        $digestMethod = $dom->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::DIGEST_SHA1); // conforme à Figure 22 de la spec
        $certDigest->appendChild($digestMethod);
        $certDigest->appendChild($dom->createElementNS(self::NS_DS, 'ds:DigestValue', $certDigestSha1));
        $cert->appendChild($certDigest);

        $cert->appendChild($dom->createElementNS(self::NS_XADES, 'xades:IssuerSerialV2', $issuerSerialV2Base64));

        $signingCertV2->appendChild($cert);
        $signedSigProps->appendChild($signingCertV2);

        // Politique de signature (valeurs fixes officielles TTN)
        $policyIdentifier = $dom->createElementNS(self::NS_XADES, 'xades:SignaturePolicyIdentifier');
        $policyId = $dom->createElementNS(self::NS_XADES, 'xades:SignaturePolicyId');

        $sigPolicyId = $dom->createElementNS(self::NS_XADES, 'xades:SigPolicyId');
        $identifier = $dom->createElementNS(self::NS_XADES, 'xades:Identifier', self::POLICY_OID);
        $identifier->setAttribute('Qualifier', 'OIDAsURN');
        $sigPolicyId->appendChild($identifier);
        $sigPolicyId->appendChild($dom->createElementNS(self::NS_XADES, 'xades:Description', self::POLICY_DESCRIPTION));
        $policyId->appendChild($sigPolicyId);

        $sigPolicyHash = $dom->createElementNS(self::NS_XADES, 'xades:SigPolicyHash');
        $policyDigestMethod = $dom->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $policyDigestMethod->setAttribute('Algorithm', self::DIGEST_SHA256);
        $sigPolicyHash->appendChild($policyDigestMethod);
        $sigPolicyHash->appendChild($dom->createElementNS(self::NS_DS, 'ds:DigestValue', self::POLICY_HASH));
        $policyId->appendChild($sigPolicyHash);

        $qualifiers = $dom->createElementNS(self::NS_XADES, 'xades:SigPolicyQualifiers');
        $qualifier = $dom->createElementNS(self::NS_XADES, 'xades:SigPolicyQualifier');
        $qualifier->appendChild($dom->createElementNS(self::NS_XADES, 'xades:SPURI', self::POLICY_URL));
        $qualifiers->appendChild($qualifier);
        $policyId->appendChild($qualifiers);

        $policyIdentifier->appendChild($policyId);
        $signedSigProps->appendChild($policyIdentifier);

        $signerRole = $dom->createElementNS(self::NS_XADES, 'xades:SignerRoleV2');
        $claimedRoles = $dom->createElementNS(self::NS_XADES, 'xades:ClaimedRoles');
        $claimedRoles->appendChild($dom->createElementNS(self::NS_XADES, 'xades:ClaimedRole', 'Fournisseur'));
        $signerRole->appendChild($claimedRoles);
        $signedSigProps->appendChild($signerRole);

        $signedProperties->appendChild($signedSigProps);

        $signedDataObjProps = $dom->createElementNS(self::NS_XADES, 'xades:SignedDataObjectProperties');
        $dataObjFormat = $dom->createElementNS(self::NS_XADES, 'xades:DataObjectFormat');
        $dataObjFormat->setAttribute('ObjectReference', '#r-id-frs');
        $dataObjFormat->appendChild($dom->createElementNS(self::NS_XADES, 'xades:MimeType', 'application/octet-stream'));
        $signedDataObjProps->appendChild($dataObjFormat);
        $signedProperties->appendChild($signedDataObjProps);

        return $signedProperties;
    }

    private function assembleSignatureNode(
        DOMDocument $dom,
        string $canonicalSignedInfo,
        string $signatureValueB64,
        string $certDerBase64,
        string $signingTime,
        string $certDigestSha1,
        string $issuerSerialV2Base64,
    ): DOMElement {
        // On réimporte SignedInfo (déjà canonicalisé) tel quel dans le document final
        $signedInfoFragment = new DOMDocument();
        $signedInfoFragment->loadXML($canonicalSignedInfo);
        $importedSignedInfo = $dom->importNode($signedInfoFragment->documentElement, true);

        $signature = $dom->createElementNS(self::NS_DS, 'ds:Signature');
        $signature->setAttribute('Id', 'SigFrs');
        $signature->appendChild($importedSignedInfo);

        $signatureValue = $dom->createElementNS(self::NS_DS, 'ds:SignatureValue', $signatureValueB64);
        $signatureValue->setAttribute('Id', 'value-SigFrs');
        $signature->appendChild($signatureValue);

        $keyInfo = $dom->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $x509Data = $dom->createElementNS(self::NS_DS, 'ds:X509Data');
        $x509Data->appendChild($dom->createElementNS(self::NS_DS, 'ds:X509Certificate', $certDerBase64));
        $keyInfo->appendChild($x509Data);
        $signature->appendChild($keyInfo);

        $object = $dom->createElementNS(self::NS_DS, 'ds:Object');
        $qualifyingProperties = $dom->createElementNS(self::NS_XADES, 'xades:QualifyingProperties');
        $qualifyingProperties->setAttribute('Target', '#SigFrs');

        $signedPropertiesDom = new DOMDocument();
        $tmpDoc = new DOMDocument();
        $signedPropertiesEl = $this->buildSignedPropertiesXml($tmpDoc, $signingTime, $certDigestSha1, $issuerSerialV2Base64);
        $tmpDoc->appendChild($signedPropertiesEl);
        $importedSignedProperties = $dom->importNode($signedPropertiesEl, true);

        $qualifyingProperties->appendChild($importedSignedProperties);
        $object->appendChild($qualifyingProperties);
        $signature->appendChild($object);

        return $signature;
    }

    // ===========================
    // Helpers certificat / ASN.1
    // ===========================

    private function pemToDerBase64(string $pem): string
    {
        $pem = preg_replace('/-----(BEGIN|END) CERTIFICATE-----/', '', $pem);
        $pem = str_replace(["\r", "\n"], '', $pem);
        return $pem; // Le PEM base64 est déjà le DER encodé en base64
    }

    /**
     * Construit la structure ASN.1 IssuerSerialV2 (RFC 5035) :
     * SEQUENCE { SEQUENCE { [4] Name(issuer) }, INTEGER serialNumber }
     *
     * ⚠️ Implémentation manuelle (pas de librairie ASN.1 disponible nativement en PHP).
     * À vérifier avec le validateur ETSI (section 7 du guide TTN) si le certificat
     * final génère une erreur sur ce bloc précis.
     */
    private function buildIssuerSerialV2(string $certPem, array $certData): string
    {
        $der = base64_decode($this->pemToDerBase64($certPem));

        $issuerDer = $this->extractIssuerDerFromCertificate($der);
        $serialHex = $certData['serialNumber'] ?? '0';

        // GeneralName [4] EXPLICIT (constructed, context-specific tag 4) wrapping le issuer DN
        $generalName = $this->derTag(0xA4, $issuerDer); // [4] constructed

        // GeneralNames ::= SEQUENCE OF GeneralName
        $generalNames = $this->derSequence($generalName);

        // CertificateSerialNumber ::= INTEGER
        $serialBytes = $this->hexSerialToBinary($serialHex);
        $serialInteger = $this->derTag(0x02, $serialBytes); // INTEGER

        // IssuerSerial ::= SEQUENCE { issuer GeneralNames, serialNumber INTEGER }
        $issuerSerial = $this->derSequence($generalNames . $serialInteger);

        return base64_encode($issuerSerial);
    }

    private function extractIssuerDerFromCertificate(string $der): string
    {
        // Certificate ::= SEQUENCE { tbsCertificate, signatureAlgorithm, signatureValue }
        [$tbs] = $this->derReadSequenceChildren($der, 1);

        // TBSCertificate ::= SEQUENCE { [0] version OPT, serialNumber, signature AlgId, issuer Name, ... }
        $offset = 0;
        $this->derExpectTag($tbs, 0x30, $offset); // outer SEQUENCE header déjà consommé par derReadSequenceChildren
        $pos = 0;
        $body = $this->derBody($tbs, $pos);

        $cursor = 0;
        // version [0] EXPLICIT (optionnel, tag 0xA0)
        if ($cursor < strlen($body) && ord($body[$cursor]) === 0xA0) {
            $cursor += $this->derSkipTlv($body, $cursor);
        }
        // serialNumber INTEGER
        $cursor += $this->derSkipTlv($body, $cursor);
        // signature AlgorithmIdentifier SEQUENCE
        $cursor += $this->derSkipTlv($body, $cursor);
        // issuer Name SEQUENCE <-- c'est ce qu'on veut
        $issuerStart = $cursor;
        $issuerLen = $this->derSkipTlv($body, $cursor);

        return substr($body, $issuerStart, $issuerLen);
    }

    // --- primitives DER minimales ---

    private function derTag(int $tag, string $content): string
    {
        return chr($tag) . $this->derLength(strlen($content)) . $content;
    }

    private function derSequence(string $content): string
    {
        return $this->derTag(0x30, $content);
    }

    private function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xFF) . $bytes;
            $length >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function derReadLength(string $data, int &$offset): int
    {
        $first = ord($data[$offset]);
        $offset++;
        if ($first < 0x80) {
            return $first;
        }
        $numBytes = $first & 0x7F;
        $length = 0;
        for ($i = 0; $i < $numBytes; $i++) {
            $length = ($length << 8) | ord($data[$offset]);
            $offset++;
        }
        return $length;
    }

    private function derSkipTlv(string $data, int $offset): int
    {
        $start = $offset;
        $offset++; // tag
        $length = $this->derReadLength($data, $offset);
        return ($offset + $length) - $start;
    }

    private function derBody(string $data, int &$offset): string
    {
        $offset++; // tag
        $length = $this->derReadLength($data, $offset);
        return substr($data, $offset, $length);
    }

    private function derExpectTag(string $data, int $expectedTag, int $offset): void
    {
        if (ord($data[$offset]) !== $expectedTag) {
            throw new RuntimeException("ASN.1 : tag attendu {$expectedTag}, trouvé " . ord($data[$offset]));
        }
    }

    private function derReadSequenceChildren(string $der, int $count): array
    {
        $offset = 0;
        $body = $this->derBody($der, $offset);

        $children = [];
        $pos = 0;
        for ($i = 0; $i < $count; $i++) {
            $len = $this->derSkipTlv($body, $pos);
            $children[] = substr($body, $pos, $len);
            $pos += $len;
        }
        return $children;
    }

    private function hexSerialToBinary(string $serialHex): string
    {
        $hex = ltrim($serialHex, '0x');
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        $binary = hex2bin($hex) ?: "\x00";

        // Si le premier bit est à 1, ajouter un octet 0x00 (INTEGER DER doit rester positif)
        if (ord($binary[0]) & 0x80) {
            $binary = "\x00" . $binary;
        }

        return $binary;
    }
}