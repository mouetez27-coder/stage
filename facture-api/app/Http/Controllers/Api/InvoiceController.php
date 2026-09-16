<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\Pdf\InvoicePdfService;
use App\Services\Xml\TeifXmlService;
use App\Services\Xml\XmlValidatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Services\Xml\TeifSignatureService;
use App\Services\Xml\TeifSignatureVerifierService;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private TeifXmlService $teifXmlService,
        private InvoicePdfService $invoicePdfService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            Invoice::with('items')->latest()->get()
        );
    }

    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::with('items', 'company')->findOrFail($id);

        return response()->json($invoice);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->store(
            $request->validated()
        );

        return response()->json([
            'message' => 'Facture enregistrée avec succès.',
            'invoice' => $invoice->load('items'),
        ], 201);
    }

    public function generateXml(Invoice $invoice): JsonResponse
    {
        $invoice = $this->teifXmlService->generateAndStore($invoice);

        return response()->json([
            'message' => 'XML généré avec succès.',
            'xml_path' => $invoice->xml_path,
        ]);
    }

    public function generatePdf(Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoicePdfService->generateAndStore($invoice);

        return response()->json([
            'message' => 'PDF généré avec succès.',
            'pdf_path' => $invoice->pdf_path,
        ]);
    }

    public function downloadPdf(Invoice $invoice)
    {
        if (!$invoice->pdf_path || !Storage::exists($invoice->pdf_path)) {
            return response()->json(['message' => 'PDF non généré.'], 404);
        }

        return Storage::download($invoice->pdf_path, "{$invoice->invoice_number}.pdf");
    }

    public function downloadXml(Invoice $invoice)
    {
        if (!$invoice->xml_path || !Storage::exists($invoice->xml_path)) {
            return response()->json(['message' => 'XML non généré.'], 404);
        }

        return Storage::download($invoice->xml_path, "{$invoice->invoice_number}.xml");
    }

    public function sendEmail(Invoice $invoice): JsonResponse
    {
        if (empty($invoice->client_email)) {
            return response()->json([
                'message' => "Le client n'a pas d'adresse email enregistrée.",
            ], 422);
        }

        if (!$invoice->xml_path) {
            $this->teifXmlService->generateAndStore($invoice);
        }

        if (!$invoice->pdf_path) {
            $this->invoicePdfService->generateAndStore($invoice);
        }

        Mail::to($invoice->client_email)->send(new InvoiceMail($invoice->fresh()));

        $invoice->update([
            'transmission_channel' => 'email',
            'sent_at' => now(),
        ]);

        return response()->json([
            'message' => 'Facture envoyée par email avec succès.',
        ]);
    }

       
    public function validateXml(Invoice $invoice, XmlValidatorService $validator): JsonResponse
    {
        if (!$invoice->xml_path || !\Illuminate\Support\Facades\Storage::exists($invoice->xml_path)) {
            return response()->json(['message' => 'XML non généré.'], 404);
        }

        $xmlContent = \Illuminate\Support\Facades\Storage::get($invoice->xml_path);
        $errors = $validator->validate($xmlContent);

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors,
        ]);
    }
    
    public function signXml(Invoice $invoice, TeifSignatureService $signer): JsonResponse
    {
        try {
            // Si le XML brut n'existe pas encore, le générer automatiquement
            if (!$invoice->xml_path || !Storage::exists($invoice->xml_path)) {
                $invoice = $this->teifXmlService->generateAndStore($invoice);
            }

            $xml = Storage::get($invoice->xml_path);
            $signedXml = $signer->sign($xml);

            $signedFilename = "teif/{$invoice->invoice_number}-signed.xml";
            Storage::put($signedFilename, $signedXml);

            $certInfo = $signer->getSignerCertificateInfo();

            $invoice->update([
                'signed_at'       => now(),
                'signed_xml_path' => $signedFilename,
                'signer_dn'       => $certInfo['subject_dn'] ?? null,
                'status'          => ($invoice->status === 'draft' ? 'validated' : $invoice->status),
            ]);

            return response()->json([
                'message'         => 'Facture signée avec succès.',
                'signed_xml_path' => $signedFilename,
                'signed_at'       => $invoice->signed_at->toIso8601String(),
                'signer'          => $certInfo,
                'invoice'         => $invoice->fresh(['items', 'company']),
            ]);
        } catch (\Throwable $e) {
            Log::error("Erreur lors de la signature de la facture {$invoice->invoice_number} : " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Échec de la signature électronique : ' . $e->getMessage(),
            ], 500);
        }
    }

    public function verifySignature(Invoice $invoice, TeifSignatureVerifierService $verifier): JsonResponse
    {
        $targetPath = $invoice->signed_xml_path;
        if (!$targetPath && $invoice->xml_path && str_ends_with($invoice->xml_path, '-signed.xml')) {
            $targetPath = $invoice->xml_path;
        }

        if (!$targetPath || !Storage::exists($targetPath)) {
            return response()->json([
                'message' => "Aucun document XML signé n'a été trouvé pour cette facture. Veuillez la signer d'abord.",
                'is_signed' => false,
            ], 404);
        }

        $xml = Storage::get($targetPath);
        $report = $verifier->verify($xml);

        return response()->json([
            'invoice_number'  => $invoice->invoice_number,
            'signed_xml_path' => $targetPath,
            'report'          => $report,
        ]);
    }

    public function downloadSignedXml(Invoice $invoice)
    {
        $targetPath = $invoice->signed_xml_path;
        if (!$targetPath && $invoice->xml_path && str_ends_with($invoice->xml_path, '-signed.xml')) {
            $targetPath = $invoice->xml_path;
        }

        if (!$targetPath || !Storage::exists($targetPath)) {
            return response()->json(['message' => 'Fichier XML signé introuvable.'], 404);
        }

        return Storage::download($targetPath, "{$invoice->invoice_number}-signed.xml");
    }
}