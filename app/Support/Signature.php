<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Signature / company stamp uploaded by an administrator (Administration > Signature et cachet).
 * Printed at the bottom of every document (PDF and Excel), except drafts.
 * Kept outside public/: storage/app/branding/signature.{png|jpg} + signature.json (signatory line).
 */
class Signature
{
    private const DIR = 'branding';

    public static function exists(): bool
    {
        return self::path() !== null;
    }

    /** Absolute path of the image, or null when no signature was uploaded. */
    public static function path(): ?string
    {
        foreach (['png', 'jpg', 'jpeg'] as $extension) {
            if (Storage::disk('local')->exists(self::DIR . "/signature.{$extension}")) {
                return Storage::disk('local')->path(self::DIR . "/signature.{$extension}");
            }
        }

        return null;
    }

    /** Embedded image for the PDF / preview (no public URL needed). */
    public static function dataUri(): ?string
    {
        $path = self::path();
        if (!$path) {
            return null;
        }
        $mime = str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    /** Line printed above the image, e.g. "Le gerant, Karim Alami". */
    public static function signatory(): string
    {
        $settings = json_decode((string) Storage::disk('local')->get(self::DIR . '/signature.json'), true) ?: [];

        return (string) ($settings['signatory'] ?? '');
    }

    public static function store(UploadedFile $file, ?string $signatory): void
    {
        self::deleteImage();
        $extension = strtolower($file->getClientOriginalExtension()) === 'png' ? 'png' : 'jpg';
        Storage::disk('local')->putFileAs(self::DIR, $file, "signature.{$extension}");
        self::saveSignatory($signatory);
    }

    public static function saveSignatory(?string $signatory): void
    {
        Storage::disk('local')->put(self::DIR . '/signature.json', json_encode(['signatory' => trim((string) $signatory)], JSON_UNESCAPED_UNICODE));
    }

    public static function delete(): void
    {
        self::deleteImage();
        Storage::disk('local')->delete(self::DIR . '/signature.json');
    }

    private static function deleteImage(): void
    {
        foreach (['png', 'jpg', 'jpeg'] as $extension) {
            Storage::disk('local')->delete(self::DIR . "/signature.{$extension}");
        }
    }
}
