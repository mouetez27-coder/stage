<?php

namespace App\Services\Xml;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * Service de vérification cryptographique de signatures XAdES-B
 * conforme aux spécifications TTN (Tunisie TradeNet) El Fatoora.
 */
class TeifSignatureVerifierService
{
    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';
    private const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';

    /**
     * Vérifie intégralement la signature d'un document XML TEIF.
     *
     * @param string $xmlContent Le contenu du fichier XML signé.
     * @return array Rapport structuré contenant les résultats de validation et métadonnées.
     */
    public function verify(string $xmlContent): array
    {
        $errors = [];
        $checks = [
            'has_signature'             => false,
            'rsa_signature'             => false,
            'document_digest'           => false,
            'signed_properties_digest'  => false,
            'certificate_digest'        => false,
            'certificate_validity'      => false,
        ];

        $signerInfo = [
            'common_name'   => null,
            'organization'  => null,
            'country'       => null,
            'subject_dn'    => null,
        ];

        $certInfo = [
            'subject'       => null,
            'issuer'        => null,
            'serial_number' => null,
            'valid_from'    => null,
            'valid_to'      => null,
            'is_expired'    => false,
        ];

        $metadata = [
            'signing_time'   => null,
            'claimed_role'   => null,
            'policy_oid'     => null,
            'policy_url'     => null,
        ];

        if (trim($xmlContent) === '') {
            return $this->buildReport(false, $checks, $signerInfo, $certInfo, $metadata, ['Le contenu XML est vide.']);
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        $previousLibXmlErrors = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xmlContent);
        libxml_use_internal_errors($previousLibXmlErrors);

        if (!$loaded) {
            return $this->buildReport(false, $checks, $signerInfo, $certInfo, $metadata, ['Le document XML est mal formé.']);
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ds', self::NS_DS);
        $xpath->registerNamespace('xades', self::NS_XADES);

        // 1. Détection du bloc <ds:Signature>
        $sigNodeList = $xpath->query('//ds:Signature[@Id="SigFrs"]');
        if ($sigNodeList->length === 0) {
            $sigNodeList = $xpath->query('//ds:Signature');
        }

        if ($sigNodeList->length === 0) {
            $errors[] = "Aucun bloc <ds:Signature> n'a été trouvé dans le document XML.";
            return $this->buildReport(false, $checks, $signerInfo, $certInfo, $metadata, $errors);
        }

        $checks['has_signature'] = true;
        /** @var DOMElement $sigNode */
        $sigNode = $sigNodeList->item(0);

        // 2. Extraction du certificat X.509
        $certNode = $xpath->query('.//ds:KeyInfo/ds:X509Data/ds:X509Certificate', $sigNode)->item(0);
        if (!$certNode) {
            $errors[] = "Le certificat <ds:X509Certificate> est introuvable dans KeyInfo.";
            return $this->buildReport(false, $checks, $signerInfo, $certInfo, $metadata, $errors);
        }

        $certB64 = preg_replace('/\s+/', '', $certNode->nodeValue);
        $certPem = "-----BEGIN CERTIFICATE-----\n" . chunk_split($certB64, 64, "\n") . "-----END CERTIFICATE-----\n";
        $certData = openssl_x509_parse($certPem, true);

        if (!$certData) {
            $errors[] = "Impossible d'analyser le certificat X.509 embarqué.";
            return $this->buildReport(false, $checks, $signerInfo, $certInfo, $metadata, $errors);
        }

        // Remplissage infos signataire et certificat
        $signerInfo['common_name']  = $certData['subject']['CN'] ?? null;
        $signerInfo['organization'] = $certData['subject']['O'] ?? null;
        $signerInfo['country']      = $certData['subject']['C'] ?? null;
        $signerInfo['subject_dn']   = $this->formatDn($certData['name'] ?? ($certData['subject'] ?? null));

        $validFrom = $certData['validFrom_time_t'] ?? null;
        $validTo   = $certData['validTo_time_t'] ?? null;
        $now       = time();

        $certInfo['subject']       = $signerInfo['subject_dn'];
        $certInfo['issuer']        = $this->formatDn($certData['issuer'] ?? null);
        $certInfo['serial_number'] = $certData['serialNumber'] ?? '0';
        $certInfo['valid_from']    = $validFrom ? gmdate('Y-m-d\TH:i:s\Z', $validFrom) : null;
        $certInfo['valid_to']      = $validTo ? gmdate('Y-m-d\TH:i:s\Z', $validTo) : null;

        if ($validTo && $now > $validTo) {
            $certInfo['is_expired'] = true;
            $errors[] = "Le certificat de signature a expiré le {$certInfo['valid_to']}.";
        } elseif ($validFrom && $now < $validFrom) {
            $errors[] = "Le certificat n'est pas encore actif (débute le {$certInfo['valid_from']}).";
        } else {
            $checks['certificate_validity'] = true;
        }

        // 3. Extraction et vérification de SignatureValue & SignedInfo
        $sigValueNode = $xpath->query('.//ds:SignatureValue', $sigNode)->item(0);
        $signedInfoNode = $xpath->query('.//ds:SignedInfo', $sigNode)->item(0);

        if (!$sigValueNode || !$signedInfoNode) {
            $errors[] = "Le bloc <ds:SignedInfo> ou <ds:SignatureValue> est manquant.";
            return $this->buildReport(false, $checks, $signerInfo, $certInfo, $metadata, $errors);
        }

        $sigValueB64 = preg_replace('/\s+/', '', $sigValueNode->nodeValue);
        $signatureBytes = base64_decode($sigValueB64);

        $publicKey = openssl_pkey_get_public($certPem);
        if (!$publicKey) {
            $errors[] = "Impossible d'extraire la clé publique du certificat : " . openssl_error_string();
            return $this->buildReport(false, $checks, $signerInfo, $certInfo, $metadata, $errors);
        }

        $canonicalSignedInfo = $signedInfoNode->C14N(true, false);
        $verifyResult = openssl_verify($canonicalSignedInfo, $signatureBytes, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verifyResult === 1) {
            $checks['rsa_signature'] = true;
        } else {
            $errors[] = "La signature cryptographique RSA-SHA256 de SignedInfo est invalide.";
        }

        // 4. Vérification Reference 1 : r-id-frs (intégrité du document TEIF)
        $ref1DigestNode = $xpath->query('.//ds:SignedInfo/ds:Reference[@Id="r-id-frs"]/ds:DigestValue', $sigNode)->item(0);
        if ($ref1DigestNode) {
            $expectedDocDigest = trim($ref1DigestNode->nodeValue);

            // Reconstitue le document canonique sans Signature ni RefTtnVal
            $domDocCopy = clone $dom;
            $xpathCopy = new DOMXPath($domDocCopy);
            $xpathCopy->registerNamespace('ds', self::NS_DS);

            foreach ($xpathCopy->query('//ds:Signature') as $sEl) {
                $sEl->parentNode->removeChild($sEl);
            }
            foreach ($xpathCopy->query('//RefTtnVal') as $rEl) {
                $rEl->parentNode->removeChild($rEl);
            }

            $canonicalDoc = $domDocCopy->C14N(true, false);
            $actualDocDigest = base64_encode(hash('sha256', $canonicalDoc, true));

            if ($actualDocDigest === $expectedDocDigest) {
                $checks['document_digest'] = true;
            } else {
                $errors[] = "Altération détectée : le digest du document TEIF (r-id-frs) ne correspond pas. Le document a été modifié après signature.";
            }
        } else {
            $errors[] = "La référence de document 'r-id-frs' est absente de SignedInfo.";
        }

        // 5. Vérification Reference 2 : #xades-SigFrs (SignedProperties)
        $ref2DigestNode = $xpath->query('.//ds:SignedInfo/ds:Reference[@URI="#xades-SigFrs"]/ds:DigestValue', $sigNode)->item(0);
        $signedPropsNode = $xpath->query('.//*[@Id="xades-SigFrs"]', $sigNode)->item(0);

        if ($ref2DigestNode && $signedPropsNode) {
            $expectedPropsDigest = trim($ref2DigestNode->nodeValue);
            $canonicalProps = $signedPropsNode->C14N(true, false);
            $actualPropsDigest = base64_encode(hash('sha256', $canonicalProps, true));

            if ($actualPropsDigest === $expectedPropsDigest) {
                $checks['signed_properties_digest'] = true;
            } else {
                $errors[] = "Le digest des propriétés signées (#xades-SigFrs) ne correspond pas.";
            }
        } else {
            $errors[] = "Le bloc <xades:SignedProperties Id='xades-SigFrs'> ou sa référence est manquant.";
        }

        // 6. Vérification xades:CertDigest
        $certDigestNode = $xpath->query('.//xades:CertDigest/ds:DigestValue', $sigNode)->item(0);
        if ($certDigestNode) {
            $expectedCertDigest = trim($certDigestNode->nodeValue);
            $derBinary = base64_decode($certB64);
            $actualCertDigest = base64_encode(hash('sha1', $derBinary, true));

            if ($actualCertDigest === $expectedCertDigest) {
                $checks['certificate_digest'] = true;
            } else {
                $errors[] = "Le digest SHA-1 du certificat (CertDigest) ne correspond pas au certificat embarqué.";
            }
        } else {
            $errors[] = "Le bloc <xades:CertDigest> est manquant.";
        }

        // 7. Extraction métadonnées XAdES
        $signingTimeNode = $xpath->query('.//xades:SigningTime', $sigNode)->item(0);
        if ($signingTimeNode) {
            $metadata['signing_time'] = trim($signingTimeNode->nodeValue);
        }

        $claimedRoleNode = $xpath->query('.//xades:ClaimedRole', $sigNode)->item(0);
        if ($claimedRoleNode) {
            $metadata['claimed_role'] = trim($claimedRoleNode->nodeValue);
        }

        $policyOidNode = $xpath->query('.//xades:SigPolicyId/xades:Identifier', $sigNode)->item(0);
        if ($policyOidNode) {
            $metadata['policy_oid'] = trim($policyOidNode->nodeValue);
        }

        $policyUrlNode = $xpath->query('.//xades:SPURI', $sigNode)->item(0);
        if ($policyUrlNode) {
            $metadata['policy_url'] = trim($policyUrlNode->nodeValue);
        }

        $isValid = empty($errors)
            && $checks['rsa_signature']
            && $checks['document_digest']
            && $checks['signed_properties_digest']
            && $checks['certificate_digest'];

        return $this->buildReport($isValid, $checks, $signerInfo, $certInfo, $metadata, $errors);
    }

    private function buildReport(
        bool $isValid,
        array $checks,
        array $signerInfo,
        array $certInfo,
        array $metadata,
        array $errors
    ): array {
        return [
            'is_valid'      => $isValid,
            'signed_at'     => $metadata['signing_time'] ?? null,
            'signer'        => $signerInfo,
            'certificate'   => $certInfo,
            'metadata'      => $metadata,
            'checks'        => $checks,
            'errors'        => $errors,
        ];
    }

    private function formatDn(mixed $dn): ?string
    {
        if (is_string($dn)) {
            return $dn;
        }
        if (is_array($dn)) {
            $parts = [];
            foreach ($dn as $k => $v) {
                if (is_array($v)) {
                    $v = implode(', ', $v);
                }
                $parts[] = "{$k}={$v}";
            }
            return '/' . implode('/', $parts);
        }
        return null;
    }
}
