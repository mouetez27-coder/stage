<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chemins des certificats et clés de signature électronique
    |--------------------------------------------------------------------------
    |
    | Définition des chemins vers le certificat X.509 (PEM) et la clé privée (PEM).
    | Conforme aux spécifications techniques TTN El Fatoora v3.0.
    |
    */
    'signature_cert_path' => env('TEIF_SIGNATURE_CERT_PATH', 'certs/test-cert.pem'),
    'signature_key_path'  => env('TEIF_SIGNATURE_KEY_PATH', 'certs/test-key.pem'),
    'signature_passphrase' => env('TEIF_SIGNATURE_PASSPHRASE', null),

    /*
    |--------------------------------------------------------------------------
    | Politique de signature officielle TTN (Tunisie TradeNet)
    |--------------------------------------------------------------------------
    |
    | Valeurs imposées par la section 5.5.1 des spécifications techniques TTN.
    |
    */
    'policy' => [
        'oid'         => env('TEIF_POLICY_OID', 'urn:2.16.788.1.2.1.3'),
        'description' => env('TEIF_POLICY_DESCRIPTION', 'Politique de Signature Electronique de Tunisie TradeNet'),
        'hash'        => env('TEIF_POLICY_HASH', 'ZKLu5TojntPu+bUfZyjaEDvkYsAh7eyyV+Hf8nUSQEE='),
        'url'         => env('TEIF_POLICY_URL', 'https://www.tradenet.com.tn/Politique_Signature_Electronique_Tunisie_TradeNet.pdf'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Paramètres cryptographiques XAdES-B
    |--------------------------------------------------------------------------
    |
    | Constantes normalisées ETSI TS 101 903 / XMLDSig.
    |
    */
    'crypto' => [
        'signature_id'       => 'SigFrs',
        'claimed_role'       => 'Fournisseur',
        'canonicalization'   => 'http://www.w3.org/2001/10/xml-exc-c14n#',
        'signature_method'   => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
        'digest_method'      => 'http://www.w3.org/2001/04/xmlenc#sha256',
        'cert_digest_method' => 'http://www.w3.org/2000/09/xmldsig#sha1',
    ],
];
