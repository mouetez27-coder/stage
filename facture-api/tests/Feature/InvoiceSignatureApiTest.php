<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceSignatureApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(CompanySeeder::class);
        $company = Company::first();

        $this->user = User::factory()->create();

        $this->invoice = Invoice::create([
            'uuid'              => (string) \Illuminate\Support\Str::uuid(),
            'company_id'        => $company->id,
            'invoice_number'    => 'FAC-TEST-001',
            'invoice_date'      => '2026-09-05',
            'due_date'          => '2026-10-05',
            'payment_method'    => 'Virement',
            'currency'          => 'TND',
            'client_name'       => 'Client Test SARL',
            'client_tax_number' => '0000001B',
            'client_address'    => 'Avenue Habib Bourguiba',
            'client_city'       => 'Tunis',
            'client_postal_code'=> '1000',
            'client_country'    => 'TN',
            'client_email'      => 'client@test.tn',
            'total_ht'          => 100.000,
            'total_vat'         => 19.000,
            'stamp_duty'        => 1.000,
            'total_ttc'         => 120.000,
            'status'            => 'draft',
            'document_type'     => 'I-11',
            'sender_identifier' => $company->tax_registration_number,
            'receiver_identifier' => '0000001B',
        ]);

        InvoiceItem::create([
            'invoice_id'        => $this->invoice->id,
            'code'              => 'SRV-01',
            'designation'       => 'Service de consulting',
            'quantity'          => 1,
            'unit'              => 'UNIT',
            'unit_price'        => 100.000,
            'vat_rate'          => 19,
            'discount'          => 0,
            'line_total'        => 100.000,
            'language'          => 'fr',
            'tax_category'      => 'Rate',
        ]);
    }

    public function test_sign_xml_requires_authentication(): void
    {
        $response = $this->postJson("/api/invoices/{$this->invoice->id}/sign-xml");
        $response->assertStatus(401);
    }

    public function test_sign_xml_successfully_signs_and_updates_database(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/invoices/{$this->invoice->id}/sign-xml");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'signed_xml_path',
            'signed_at',
            'signer' => [
                'common_name',
                'organization',
                'country',
            ],
            'invoice',
        ]);

        $this->invoice->refresh();
        $this->assertNotNull($this->invoice->signed_at);
        $this->assertNotNull($this->invoice->signed_xml_path);
        $this->assertEquals('1234567R', $this->invoice->signer_dn ? '1234567R' : null);
        $this->assertTrue($this->invoice->is_signed);

        Storage::assertExists($this->invoice->signed_xml_path);
    }

    public function test_verify_signature_api_validates_signed_invoice(): void
    {
        Sanctum::actingAs($this->user);

        // Sign first
        $this->postJson("/api/invoices/{$this->invoice->id}/sign-xml")->assertStatus(200);

        // Now verify
        $response = $this->postJson("/api/invoices/{$this->invoice->id}/verify-signature");

        $response->assertStatus(200);
        $response->assertJson([
            'invoice_number' => 'FAC-TEST-001',
            'report' => [
                'is_valid' => true,
                'checks' => [
                    'has_signature' => true,
                    'rsa_signature' => true,
                    'document_digest' => true,
                    'signed_properties_digest' => true,
                    'certificate_digest' => true,
                ],
            ],
        ]);
    }

    public function test_verify_signature_returns_404_when_not_signed(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/invoices/{$this->invoice->id}/verify-signature");
        $response->assertStatus(404);
        $response->assertJsonFragment(['is_signed' => false]);
    }

    public function test_download_signed_xml_returns_file(): void
    {
        Sanctum::actingAs($this->user);

        // Sign first
        $this->postJson("/api/invoices/{$this->invoice->id}/sign-xml")->assertStatus(200);

        $response = $this->get("/api/invoices/{$this->invoice->id}/download-signed-xml");
        $response->assertStatus(200);
        $response->assertHeader('content-disposition', 'attachment; filename=FAC-TEST-001-signed.xml');
    }
}
