<?php

namespace App\Services\Xml;

use DOMDocument;
use RuntimeException;

class XmlValidatorService
{
    private string $xsdPath;

    public function __construct()
    {
        $this->xsdPath = resource_path('schemas/teif/TEIF.xsd');
    }

    public function validate(string $xmlContent): array
    {
        if (!file_exists($this->xsdPath)) {
            throw new RuntimeException("Le fichier XSD est introuvable à : {$this->xsdPath}");
        }

        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom = new DOMDocument();
        $dom->loadXML($xmlContent);

        $isValid = $dom->schemaValidate($this->xsdPath);

        $errors = [];

        if (!$isValid) {
            foreach (libxml_get_errors() as $error) {
                $errors[] = [
                    'line' => $error->line,
                    'message' => trim($error->message),
                    'level' => match ($error->level) {
                        LIBXML_ERR_WARNING => 'warning',
                        LIBXML_ERR_ERROR => 'error',
                        LIBXML_ERR_FATAL => 'fatal',
                        default => 'unknown',
                    },
                ];
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return $errors;
    }
}