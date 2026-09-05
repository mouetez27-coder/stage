<?php

namespace App\Services\Xml;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\Storage;
use DOMDocument;
use DOMElement;
use RuntimeException;

class TeifXmlService
{
    /**
     * ⚠️ À CONFIRMER avec la documentation officielle TTN (liste des codes I-160 à I-1619).
     * D'après les patterns habituels TEIF, I-1602 correspond généralement à la TVA,
     * mais ce n'est pas garanti sans la doc officielle. Un seul endroit à corriger si besoin.
     */
    private const TAX_TYPE_VAT = 'I-1602';

    /**
     * Alphabet de contrôle du matricule fiscal tunisien (algorithme modulo 23).
     * Exclut volontairement les lettres I, O, U (confusion avec 1 et 0).
     * Source : XSD officiel TEIF v1.9.0, xs:assert sur SenderIdentifierType / PartnerIdentifierType.
     */
    private const CONTROL_LETTERS = ['A','B','C','D','E','F','G','H','J','K','L','M','N','P','Q','R','S','T','V','W','X','Y','Z'];

    /**
     * Table de correspondance unité interne -> unité TEIF (énumération fermée MeasurementUnitType).
     * "PCE" n'existe pas dans le XSD officiel ; on le mappe vers "UNIT" (unité générique).
     */
    private const UNIT_MAP = [
        'PCE' => 'UNIT',
        'UNITE' => 'UNIT',
        'UNIT' => 'UNIT',
        'KG' => 'KGM',
        'L' => 'LTR',
        'H' => 'HUR',
        'M' => 'MTR',
        'JOUR' => 'DAY',
    ];

    public function generate(Invoice $invoice): string
    {
        $invoice->loadMissing('items', 'company');

        // Toujours resynchroniser sur le matricule ACTUEL de la société,
        // pour ne jamais écrire une valeur périmée dans le XML.
        $currentSenderId = $invoice->company->tax_registration_number;
        $this->validateIdentifier($currentSenderId, 'I-01', 'émetteur');

        if ($invoice->sender_identifier !== $currentSenderId) {
            $invoice->sender_identifier = $currentSenderId;
            $invoice->save();
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $teif = $dom->createElement('TEIF');
        $teif->setAttribute('version', '1.9.0');
        $teif->setAttribute('controlingAgency', 'TTN');
        $dom->appendChild($teif);

        $teif->appendChild($this->buildInvoiceHeader($dom, $invoice));
        $teif->appendChild($this->buildInvoiceBody($dom, $invoice));

        // Passe finale : vérifie les règles "assert" du XSD 1.1 que libxml2
        // ne peut pas contrôler (voir runBusinessRuleChecks ci-dessous).
        $this->runBusinessRuleChecks($dom, $invoice);

        return $dom->saveXML();
    }
    public function generateAndStore(Invoice $invoice): Invoice
    {
        $xml = $this->generate($invoice);

        $filename = "teif/{$invoice->invoice_number}.xml";
        Storage::put($filename, $xml);

        $invoice->update(['xml_path' => $filename]);

        return $invoice;
    }

    // ===========================
    // InvoiceHeader
    // ===========================

    private function buildInvoiceHeader(DOMDocument $dom, Invoice $invoice): DOMElement
    {
        $header = $dom->createElement('InvoiceHeader');

        // MessageSenderIdentifier : uniquement I-01 autorisé (SenderIdentifierType)
        $sender = $dom->createElement('MessageSenderIdentifier', $invoice->sender_identifier);
        $sender->setAttribute('type', 'I-01');
        $header->appendChild($sender);

        // MessageRecieverIdentifier : I-01 à I-06 (PartnerIdentifierType)
        $receiverType = $this->detectIdentifierType($invoice->client_tax_number);
        $this->validateIdentifier($invoice->receiver_identifier, $receiverType, 'récepteur');

        $receiver = $dom->createElement('MessageRecieverIdentifier', $invoice->receiver_identifier);
        $receiver->setAttribute('type', $receiverType);
        $header->appendChild($receiver);

        return $header;
    }

    // ===========================
    // InvoiceBody
    // ===========================

    private function buildInvoiceBody(DOMDocument $dom, Invoice $invoice): DOMElement
    {
        $body = $dom->createElement('InvoiceBody');

        // Ordre strict imposé par le XSD (BodyType/xs:sequence)
        $body->appendChild($this->buildBgm($dom, $invoice));
        $body->appendChild($this->buildDtm($dom, $invoice));
        $body->appendChild($this->buildPartnerSection($dom, $invoice));
        $body->appendChild($this->buildLinSection($dom, $invoice));
        $body->appendChild($this->buildInvoiceMoa($dom, $invoice));
        $body->appendChild($this->buildInvoiceTax($dom, $invoice));

        return $body;
    }

    private function buildBgm(DOMDocument $dom, Invoice $invoice): DOMElement
    {
        $bgm = $dom->createElement('Bgm');

        $docIdentifier = $this->sanitizeDocumentIdentifier($invoice->invoice_number);
        $bgm->appendChild($dom->createElement('DocumentIdentifier', $docIdentifier));

        $docType = $dom->createElement('DocumentType', 'Facture');
        $docType->setAttribute('code', $invoice->document_type ?? 'I-11');
        $bgm->appendChild($docType);

        return $bgm;
    }

    private function buildDtm(DOMDocument $dom, Invoice $invoice): DOMElement
    {
        $dtm = $dom->createElement('Dtm');

        // DateText[@functionCode='I-31'] doit apparaître EXACTEMENT 1 fois, format ddMMyyyy
        $invoiceDate = $dom->createElement('DateText', $invoice->invoice_date->format('dmY'));
        $invoiceDate->setAttribute('functionCode', 'I-31');
        $invoiceDate->setAttribute('format', 'ddMMyyyy');
        $dtm->appendChild($invoiceDate);

        if ($invoice->due_date) {
            $dueDate = $dom->createElement('DateText', $invoice->due_date->format('dmY'));
            $dueDate->setAttribute('functionCode', 'I-36');
            $dueDate->setAttribute('format', 'ddMMyyyy');
            $dtm->appendChild($dueDate);
        }

        return $dtm;
    }

    private function buildPartnerSection(DOMDocument $dom, Invoice $invoice): DOMElement
    {
        $section = $dom->createElement('PartnerSection');

        // Émetteur I-62 : obligatoire, exactement 1 fois, type I-01, adresse obligatoire
        $section->appendChild($this->buildPartnerDetails(
            $dom,
            functionCode: 'I-62',
            identifierType: 'I-01',
            identifier: $invoice->sender_identifier,
            name: $invoice->company->name,
            city: $invoice->company->city,
            postalCode: $invoice->company->postal_code,
            country: $invoice->company->country ?? 'TN',
            lang: $invoice->company->language ?? 'fr',
        ));

        // Client I-64 : adresse obligatoire aussi (PartDetailTestType)
        $receiverType = $this->detectIdentifierType($invoice->client_tax_number);
        $section->appendChild($this->buildPartnerDetails(
            $dom,
            functionCode: 'I-64',
            identifierType: $receiverType,
            identifier: $invoice->receiver_identifier,
            name: $invoice->client_name,
            city: $invoice->client_city,
            postalCode: $invoice->client_postal_code,
            country: $invoice->client_country ?? 'TN',
            lang: 'fr',
        ));

        return $section;
    }

    private function buildPartnerDetails(
        DOMDocument $dom,
        string $functionCode,
        string $identifierType,
        string $identifier,
        string $name,
        string $city,
        ?string $postalCode,
        string $country,
        string $lang,
    ): DOMElement {
        if (empty($postalCode)) {
            throw new RuntimeException(
                "Le code postal est obligatoire pour le partenaire {$functionCode} (TEIF v1.9.0)."
            );
        }

        $details = $dom->createElement('PartnerDetails');
        $details->setAttribute('functionCode', $functionCode);

        $nad = $dom->createElement('Nad');

        $partnerId = $dom->createElement('PartnerIdentifier', $identifier);
        $partnerId->setAttribute('type', $identifierType);
        $nad->appendChild($partnerId);

        $partnerName = $dom->createElement('PartnerName', $this->escape($name));
        $partnerName->setAttribute('nameType', 'Qualification');
        $nad->appendChild($partnerName);

        // PartnerAddress obligatoire pour I-62/I-64 (AddressType)
        $address = $dom->createElement('PartnerAddress');
        $address->setAttribute('lang', $lang);

        $address->appendChild($dom->createElement('AddressDescription', $this->escape($name)));
        $address->appendChild($dom->createElement('CityName', $this->escape($city)));
        $address->appendChild($dom->createElement('PostalCode', $postalCode));

        $countryEl = $dom->createElement('Country', $country);
        $countryEl->setAttribute('codeList', 'ISO_3166-1');
        $address->appendChild($countryEl);

        $nad->appendChild($address);
        $details->appendChild($nad);

        return $details;
    }

    private function buildLinSection(DOMDocument $dom, Invoice $invoice): DOMElement
    {
        $section = $dom->createElement('LinSection');

        foreach ($invoice->items as $index => $item) {
            $section->appendChild($this->buildLin($dom, $invoice, $item, $index + 1));
        }

        return $section;
    }

    private function buildLin(DOMDocument $dom, Invoice $invoice, InvoiceItem $item, int $lineNumber): DOMElement
    {
        $lin = $dom->createElement('Lin');

        // ItemIdentifier : obligatoire, en premier
        $itemId = $item->code ?: "LIGNE-{$lineNumber}";
        $lin->appendChild($dom->createElement('ItemIdentifier', $this->escape(substr($itemId, 0, 35))));

        // LinImd : ItemCode + ItemDescription (pas du texte libre)
        $imd = $dom->createElement('LinImd');
        $imd->setAttribute('lang', $item->language ?? 'fr');
        $imd->appendChild($dom->createElement('ItemCode', $this->escape($itemId)));
        $imd->appendChild($dom->createElement('ItemDescription', $this->escape($item->designation)));
        $lin->appendChild($imd);

        // LinQty : unité mappée vers l'énumération officielle
        $qty = $dom->createElement('LinQty');
        $quantity = $dom->createElement('Quantity', number_format((float) $item->quantity, 3, '.', ''));
        $quantity->setAttribute('measurementUnit', $this->mapUnit($item->unit));
        $qty->appendChild($quantity);
        $lin->appendChild($qty);

        // LinTax : wrapper LinTaxDetails (répétable), avec TaxTypeName obligatoire
        $lin->appendChild($this->buildLinTax($dom, $item));

        // LinAlc : optionnel, seulement si remise
        if ($item->discount > 0) {
            $lin->appendChild($this->buildLinAlc($dom, $item));
        }

        // LinMoa : OBLIGATOIRE, montant de la ligne
        $lin->appendChild($this->buildLinMoa($dom, $item, $invoice->currency));

        return $lin;
    }

    private function buildLinTax(DOMDocument $dom, InvoiceItem $item): DOMElement
    {
        $linTax = $dom->createElement('LinTax');
        $linTaxDetails = $dom->createElement('LinTaxDetails');

        $taxTypeName = $dom->createElement('TaxTypeName', 'TVA');
        $taxTypeName->setAttribute('code', self::TAX_TYPE_VAT);
        $linTaxDetails->appendChild($taxTypeName);

        $category = $item->tax_category ?? 'Rate';
        $linTaxDetails->appendChild($dom->createElement('TaxCategory', $category));

        if ($category === 'Rate') {
            $taxDetails = $dom->createElement('TaxDetails');
            $taxDetails->appendChild($dom->createElement('TaxRate', number_format($item->vat_rate, 2, '.', '')));
            $linTaxDetails->appendChild($taxDetails);
        } else {
            $linTaxDetails->appendChild($this->buildMoa(
                $dom, self::TAX_TYPE_VAT, $item->tax_amount ?? 0, 'TND', 'TaxAmount'
            ));
        }

        $linTax->appendChild($linTaxDetails);
        return $linTax;
    }

    private function buildLinAlc(DOMDocument $dom, InvoiceItem $item): DOMElement
    {
        $linAlc = $dom->createElement('LinAlc');
        $linAlcDetails = $dom->createElement('LinAlcDetails');

        $alc = $dom->createElement('Alc');
        $alc->setAttribute('allowanceCode', 'I-151');
        $linAlcDetails->appendChild($alc);

        $linAlcDetails->appendChild($dom->createElement('AlcCategory', 'Amount'));

        $linAlcDetails->appendChild($this->buildMoa(
            $dom, null, $item->discount, 'TND', 'AlcAmount', includeAmountTypeCode: false
        ));

        $linAlc->appendChild($linAlcDetails);
        return $linAlc;
    }

    private function buildLinMoa(DOMDocument $dom, InvoiceItem $item, string $currency): DOMElement
    {
        $linMoa = $dom->createElement('LinMoa');
        $amountDetails = $dom->createElement('MoaDetails');

        $amountDetails->appendChild($this->buildMoa(
            $dom, 'I-183', $item->line_total, $currency, 'Moa'
        ));

        $linMoa->appendChild($amountDetails);
        return $linMoa;
    }

    private function buildInvoiceMoa(DOMDocument $dom, Invoice $invoice): DOMElement
    {
        $moaSection = $dom->createElement('InvoiceMoa');

        // Chaque montant a son propre wrapper <AmountDetails> (maxOccurs unbounded)
        $moaSection->appendChild($this->wrapAmountDetails(
            $this->buildMoa($dom, 'I-180', $invoice->total_ttc, $invoice->currency, 'Moa', label: 'Montant total TTC')
        ));
        $moaSection->appendChild($this->wrapAmountDetails(
            $this->buildMoa($dom, 'I-181', $invoice->total_ht, $invoice->currency, 'Moa', label: 'Montant total HT')
        ));
        $moaSection->appendChild($this->wrapAmountDetails(
            $this->buildMoa($dom, 'I-176', $invoice->total_vat, $invoice->currency, 'Moa', label: 'Montant total TVA')
        ));

        return $moaSection;
    }

    private function wrapAmountDetails(DOMElement $moaElement): DOMElement
    {
        $dom = $moaElement->ownerDocument;
        $wrapper = $dom->createElement('AmountDetails');
        $wrapper->appendChild($moaElement);
        return $wrapper;
    }

    /**
     * Construit un élément <Moa> conforme à MoaType : amountTypeCode et currencyCodeList
     * sont des attributs de Moa (pas de Amount) ; Amount porte currencyIdentifier.
     */
    private function buildMoa(
        DOMDocument $dom,
        ?string $amountTypeCode,
        float $amount,
        string $currency,
        string $elementName = 'Moa',
        ?string $label = null,
        bool $includeAmountTypeCode = true,
    ): DOMElement {
        $moa = $dom->createElement($elementName);

        if ($includeAmountTypeCode && $amountTypeCode) {
            $moa->setAttribute('amountTypeCode', $amountTypeCode);
            $moa->setAttribute('currencyCodeList', 'ISO_4217');
        }

        $amountEl = $dom->createElement('Amount', number_format($amount, 3, '.', ''));
        $amountEl->setAttribute('currencyIdentifier', $currency);
        $moa->appendChild($amountEl);

        if ($label) {
            $desc = $dom->createElement('AmountDescription', $this->escape($label));
            $desc->setAttribute('lang', 'fr');
            $moa->appendChild($desc);
        }

        return $moa;
    }

    private function buildInvoiceTax(DOMDocument $dom, Invoice $invoice): DOMElement
    {
        $taxSection = $dom->createElement('InvoiceTax');

        $rates = $invoice->items->groupBy('vat_rate');

        foreach ($rates as $rate => $items) {
            $details = $dom->createElement('InvoiceTaxDetails');

            $tax = $dom->createElement('Tax');

            $taxTypeName = $dom->createElement('TaxTypeName', 'TVA');
            $taxTypeName->setAttribute('code', self::TAX_TYPE_VAT);
            $tax->appendChild($taxTypeName);

            $tax->appendChild($dom->createElement('TaxCategory', 'Rate'));

            $taxDetails = $dom->createElement('TaxDetails');
            $taxDetails->appendChild($dom->createElement('TaxRate', number_format((float) $rate, 2, '.', '')));
            $tax->appendChild($taxDetails);

            $details->appendChild($tax);

            // AmountDetailsSection obligatoire (MoaLinType) : montant de TVA pour ce taux
            $tvaAmount = $items->sum(fn ($item) => $item->line_total * ($item->vat_rate / 100));

            $amountSection = $dom->createElement('AmountDetailsSection');
            $moaDetails = $dom->createElement('MoaDetails');
            $moaDetails->appendChild($this->buildMoa($dom, 'I-176', $tvaAmount, $invoice->currency, 'Moa'));
            $amountSection->appendChild($moaDetails);
            $details->appendChild($amountSection);

            $taxSection->appendChild($details);
        }

        return $taxSection;
    }

    // ===========================
    // Helpers
    // ===========================

    private function mapUnit(?string $unit): string
    {
        $unit = strtoupper(trim((string) $unit));
        return self::UNIT_MAP[$unit] ?? 'UNIT';
    }

    private function sanitizeDocumentIdentifier(string $value): string
    {
        // Doit commencer par une lettre/chiffre, puis A-Za-z0-9._- uniquement, max 35 car.
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '', $value);
        $clean = ltrim($clean, '._-');
        return substr($clean, 0, 35) ?: 'DOC';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1, 'UTF-8');
    }

    private function detectIdentifierType(?string $taxNumber): string
    {
        if (empty($taxNumber)) {
            return 'I-05';
        }

        if (preg_match('/^\d{7}[A-Za-z]$/', $taxNumber) || preg_match('/^\d{7}[A-Za-z][A-Za-z][A-Za-z0-9]\d{3}$/', $taxNumber)) {
            return 'I-01';
        }

        if (preg_match('/^\d{8}$/', $taxNumber)) {
            return 'I-02';
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{4,18}[A-Za-z0-9]$/', $taxNumber)) {
            return 'I-03';
        }

        return 'I-04';
    }

    /**
     * Valide un identifiant selon les règles exactes du XSD officiel (par type).
     * Pour I-01, applique l'algorithme modulo 23 réel (alphabet sans I/O/U, index 1-based).
     */
    private function validateIdentifier(?string $value, string $type, string $label): void
    {
        if (empty($value)) {
            throw new RuntimeException("L'identifiant {$label} est vide.");
        }

        switch ($type) {
            case 'I-01':
                $this->validateModulo23($value, $label);
                break;
            case 'I-02':
                if (!preg_match('/^\d{8}$/', $value)) {
                    throw new RuntimeException("L'identifiant {$label} (I-02) doit être exactement 8 chiffres.");
                }
                break;
            case 'I-03':
                if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{4,18}[A-Za-z0-9]$/', $value)) {
                    throw new RuntimeException("L'identifiant {$label} (I-03) doit être alphanumérique, 6 à 20 caractères.");
                }
                break;
            case 'I-04':
            case 'I-05':
            case 'I-06':
                if (!preg_match('/^[A-Za-z0-9]{1,30}$/', $value)) {
                    throw new RuntimeException("L'identifiant {$label} ({$type}) doit être alphanumérique, 1 à 30 caractères.");
                }
                break;
        }
    }

    private function validateModulo23(string $value, string $label): void
    {
        $len = strlen($value);

        if ($len === 8) {
            if (!preg_match('/^\d{7}[A-Za-z]$/', $value)) {
                throw new RuntimeException("Le matricule fiscal {$label} '{$value}' (8 car.) ne respecte pas le format 7 chiffres + 1 lettre.");
            }
        } elseif ($len === 13) {
            if (!preg_match('/^\d{7}[A-Za-z][ABDNP][CMNPE]\d{3}$/', $value)) {
                throw new RuntimeException("Le matricule fiscal {$label} '{$value}' (13 car.) ne respecte pas le format TEIF.");
            }
        } else {
            throw new RuntimeException("Le matricule fiscal {$label} '{$value}' doit faire 8 ou 13 caractères.");
        }

        $digits = substr($value, 0, 7);
        $letter = strtoupper(substr($value, 7, 1));

        $x = 0;
        $weights = [7, 6, 5, 4, 3, 2, 1];
        for ($i = 0; $i < 7; $i++) {
            $x += (int) $digits[$i] * $weights[$i];
        }

        $index = $x % 23; // 0-based ici car notre tableau PHP est 0-indexé (contrairement au XSD)
        $expectedLetter = self::CONTROL_LETTERS[$index];

        if ($letter !== $expectedLetter) {
            throw new RuntimeException(
                "La lettre de contrôle du matricule fiscal {$label} '{$value}' est invalide. Attendu : '{$expectedLetter}', reçu : '{$letter}'."
            );
        }
    }

    // ===========================
    // Vérification des règles "assert" du XSD 1.1
    // (libxml2/PHP ne peut pas les valider automatiquement, donc on les
    // réplique manuellement ici en interrogeant le DOM déjà construit)
    // ===========================

    private function runBusinessRuleChecks(DOMDocument $dom, Invoice $invoice): void
    {
        $xpath = new \DOMXPath($dom);

        // Assert TEIF (racine) :
        // InvoiceBody/PartnerSection/PartnerDetails[@functionCode='I-62']/Nad/PartnerIdentifier[@type='I-01']
        //   == InvoiceHeader/MessageSenderIdentifier[@type='I-01']
        $senderInHeader = $xpath->evaluate('string(//InvoiceHeader/MessageSenderIdentifier[@type="I-01"])');
        $senderInPartner = $xpath->evaluate('string(//PartnerDetails[@functionCode="I-62"]/Nad/PartnerIdentifier[@type="I-01"])');

        if ($senderInHeader === '' || $senderInHeader !== $senderInPartner) {
            throw new RuntimeException(
                "Incohérence : l'identifiant émetteur du header ('{$senderInHeader}') ne correspond pas à celui du partenaire I-62 ('{$senderInPartner}')."
            );
        }

        // Assert BodyType : count(Dtm/DateText[@functionCode='I-31']) = 1, format ddMMyyyy
        $i31Nodes = $xpath->query('//InvoiceBody/Dtm/DateText[@functionCode="I-31"]');
        if ($i31Nodes->length !== 1) {
            throw new RuntimeException(
                "La date I-31 (date de facture) doit apparaître exactement 1 fois. Trouvé : {$i31Nodes->length}."
            );
        }
        $i31Format = $i31Nodes->item(0)->getAttribute('format');
        if ($i31Format !== 'ddMMyyyy') {
            throw new RuntimeException("La date I-31 doit être au format 'ddMMyyyy', trouvé : '{$i31Format}'.");
        }
        $i31Value = $i31Nodes->item(0)->nodeValue;
        if (!preg_match('/^(0[1-9]|[12][0-9]|3[01])(0[1-9]|1[0-2])[0-9]{4}$/', $i31Value)) {
            throw new RuntimeException("La date I-31 '{$i31Value}' ne respecte pas le format jour/mois/année attendu.");
        }

        // Assert PartType : count(PartnerDetails[@functionCode='I-62']) = 1
        //   and son PartnerIdentifier/@type = 'I-01'
        $i62Nodes = $xpath->query('//PartnerSection/PartnerDetails[@functionCode="I-62"]');
        if ($i62Nodes->length !== 1) {
            throw new RuntimeException(
                "Le partenaire I-62 (émetteur) doit apparaître exactement 1 fois. Trouvé : {$i62Nodes->length}."
            );
        }
        $i62IdType = $xpath->evaluate('string(.//PartnerIdentifier/@type)', $i62Nodes->item(0));
        if ($i62IdType !== 'I-01') {
            throw new RuntimeException("Le PartnerIdentifier du partenaire I-62 doit être de type I-01, trouvé : '{$i62IdType}'.");
        }

        // Assert MoaInvoiceType : count(AmountDetails[Moa/@amountTypeCode='I-180' and Moa/Amount/@currencyIdentifier='TND']) = 1
        $i180TndNodes = $xpath->query('//InvoiceMoa/AmountDetails[Moa/@amountTypeCode="I-180" and Moa/Amount/@currencyIdentifier="TND"]');
        if ($invoice->currency === 'TND' && $i180TndNodes->length !== 1) {
            throw new RuntimeException(
                "Le montant total TTC (I-180) en TND doit apparaître exactement 1 fois. Trouvé : {$i180TndNodes->length}."
            );
        }

        // Format des montants I-180/I-181/I-176 : max 3 décimales
        foreach (['I-180', 'I-181', 'I-176'] as $code) {
            $nodes = $xpath->query("//InvoiceMoa/AmountDetails/Moa[@amountTypeCode='{$code}']/Amount");
            foreach ($nodes as $node) {
                if (!preg_match('/^[0-9]{1,15}([,.][0-9]{1,3})?$/', $node->nodeValue)) {
                    throw new RuntimeException(
                        "Le montant {$code} '{$node->nodeValue}' ne respecte pas le format attendu (max 3 décimales)."
                    );
                }
            }
        }

        // Chaque Lin doit avoir un LinMoa (obligatoire selon le XSD)
        $linCount = $xpath->query('//LinSection/Lin')->length;
        $linMoaCount = $xpath->query('//LinSection/Lin/LinMoa')->length;
        if ($linCount !== $linMoaCount) {
            throw new RuntimeException(
                "Chaque ligne de facture doit avoir un LinMoa. Lignes : {$linCount}, LinMoa trouvés : {$linMoaCount}."
            );
        }

        // Cohérence Rate/Amount pour LinTax : si TaxCategory=Rate -> TaxDetails requis, pas TaxAmount ; et inversement
        foreach ($xpath->query('//LinSection/Lin/LinTax/LinTaxDetails') as $node) {
            $category = $xpath->evaluate('string(TaxCategory)', $node);
            $hasDetails = $xpath->evaluate('count(TaxDetails) > 0', $node);
            $hasAmount = $xpath->evaluate('count(TaxAmount) > 0', $node);

            if ($category === 'Rate' && (!$hasDetails || $hasAmount)) {
                throw new RuntimeException("LinTaxDetails avec TaxCategory='Rate' doit avoir TaxDetails et pas TaxAmount.");
            }
            if ($category === 'Amount' && (!$hasAmount || $hasDetails)) {
                throw new RuntimeException("LinTaxDetails avec TaxCategory='Amount' doit avoir TaxAmount et pas TaxDetails.");
            }
        }
    }
}