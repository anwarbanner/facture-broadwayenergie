<?php

namespace App\Support;

use App\Models\invoices_attachments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Safe read/write of legacy invoice attachments (public/Attachments/{invoice_number}/{file}).
 *
 * Paths are never built from raw user input: the directory must be a single safe
 * segment and the stored file name is regenerated with an extension guessed from
 * the file content, so an upload named "shell.php" can never land as a .php file.
 */
class InvoiceAttachmentStorage
{
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public const VALIDATION_RULE = 'file|mimes:pdf,jpeg,png,jpg|max:10240';

    public static function store(UploadedFile $file, string $invoiceNumber): string
    {
        $directory = self::safeSegment($invoiceNumber, 'invoice_number');

        $extension = strtolower((string) $file->guessExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file_name' => 'Format de fichier non autorise (pdf, jpg, jpeg, png).',
            ]);
        }

        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'piece-jointe';
        $fileName = Str::limit($baseName, 80, '') . '-' . Str::random(6) . '.' . $extension;

        $file->move(Storage::disk('public_attach')->path($directory), $fileName);

        return $fileName;
    }

    public static function path(invoices_attachments $attachment): string
    {
        $directory = self::safeSegment((string) $attachment->invoice_number, 'invoice_number');
        $fileName = self::safeSegment((string) $attachment->file_name, 'file_name');

        return Storage::disk('public_attach')->path($directory . '/' . $fileName);
    }

    public static function delete(invoices_attachments $attachment): void
    {
        $path = self::path($attachment);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function safeSegment(string $value, string $field): string
    {
        if ($value === '' || $value === '.' || $value === '..' || $value !== basename($value)
            || str_contains($value, '\\') || str_contains($value, "\0")) {
            throw ValidationException::withMessages([
                $field => 'Nom de fichier ou numero de facture invalide.',
            ]);
        }

        return $value;
    }
}
