<?php

namespace Tests\Feature\Security;

use App\Models\invoices;
use App\Models\invoices_attachments;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\InvoiceAttachmentStorage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceAttachmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $invoiceNumber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->invoiceNumber = 'TEST-SEC-' . Str::upper(Str::random(8));
        // Never write test uploads into the real public/Attachments folder.
        config(['filesystems.disks.public_attach.root' => storage_path('framework/testing/attachments')]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/attachments'));
        parent::tearDown();
    }

    private function makeInvoice(): invoices
    {
        return invoices::forceCreate([
            'invoice_number' => $this->invoiceNumber,
            'invoice_date' => now()->toDateString(),
            'product' => 'Test',
            'section_id' => null,
            'Amount_Commission' => 0,
            'Discount' => 0,
            'Value_VAT' => 0,
            'Rate_VAT' => '0%',
            'Total' => 0,
            'status' => 'unpaid',
            'Value_Status' => 2,
        ]);
    }

    public function test_legacy_module_no_longer_accepts_uploads_or_deletions(): void
    {
        $invoice = $this->makeInvoice();
        $attachment = invoices_attachments::forceCreate([
            'invoice_id' => $invoice->id,
            'invoice_number' => $this->invoiceNumber,
            'file_name' => 'doc.pdf',
            'Created_by' => 'test',
        ]);
        $sentinel = storage_path('framework/testing-sentinel.txt');
        File::put($sentinel, 'keep me');

        try {
            $this->actingAs(User::factory()->create());

            $this->post('/InvoiceAttachments', [
                'invoice_id' => $invoice->id,
                'file_name' => UploadedFile::fake()->image('shell.php'),
            ])->assertRedirect(route('erp.client-invoices.index'))->assertSessionHas('error');

            $this->post('/delete_file', [
                'id_file' => $attachment->id,
                'invoice_number' => '../../storage/framework',
                'file_name' => 'testing-sentinel.txt',
            ])->assertRedirect(route('erp.client-invoices.index'));

            $this->assertDatabaseCount('invoices_attachments', 1);
            $this->assertFileExists($sentinel);
        } finally {
            File::delete($sentinel);
        }
    }

    public function test_attachment_storage_never_uses_names_from_the_client(): void
    {
        $name = InvoiceAttachmentStorage::store(UploadedFile::fake()->image('../../scan.png'), $this->invoiceNumber);

        $this->assertMatchesRegularExpression('/^scan-[A-Za-z0-9]{6}\.png$/', $name);
        $this->assertFileExists(Storage::disk('public_attach')->path($this->invoiceNumber . '/' . $name));

        $this->expectException(ValidationException::class);
        InvoiceAttachmentStorage::store(UploadedFile::fake()->image('scan.png'), '../evil');
    }

    public function test_attachment_paths_from_the_database_are_checked_too(): void
    {
        $attachment = new invoices_attachments();
        $attachment->invoice_number = $this->invoiceNumber;
        $attachment->file_name = '../../.env';

        $this->expectException(ValidationException::class);
        InvoiceAttachmentStorage::path($attachment);
    }

    public function test_view_file_only_serves_registered_attachments(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/View_file/' . $this->invoiceNumber . '/not-registered.pdf')
            ->assertNotFound();
    }

    public function test_arbitrary_blade_views_are_not_routable(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/index')
            ->assertNotFound();
    }
}
