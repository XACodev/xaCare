<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

final class EncryptedFileStorage
{
    public static function store(string $disk, string $path, string $rawContents): void
    {
        Storage::disk($disk)->put($path, Crypt::encryptString($rawContents));
    }

    public static function retrieve(string $disk, string $path): string
    {
        return Crypt::decryptString(Storage::disk($disk)->get($path));
    }
}
