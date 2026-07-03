<?php

namespace App\Support;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Support\FileNamer\FileNamer;

/**
 * Slugifies media file names so stored object keys and public URLs never
 * contain spaces, unicode or other characters that are awkward in S3 keys
 * (e.g. names derived from `addMediaFromUrl()` or user uploads). The file
 * extension is added separately by the media library, so we only return the
 * sanitised base name here.
 */
class SafeFileNamer extends FileNamer
{
    public function originalFileName(string $fileName): string
    {
        return $this->safeBaseName($fileName);
    }

    public function conversionFileName(string $fileName, Conversion $conversion): string
    {
        return "{$this->safeBaseName($fileName)}-{$conversion->getName()}";
    }

    public function responsiveFileName(string $fileName): string
    {
        return $this->safeBaseName($fileName);
    }

    protected function safeBaseName(string $fileName): string
    {
        $slug = Str::slug(pathinfo($fileName, PATHINFO_FILENAME));

        return $slug !== '' ? $slug : 'file';
    }
}
