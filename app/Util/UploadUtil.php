<?php

namespace App\Util;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Colors\Rgb\Color;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Laravel\Facades\Image;

class UploadUtil
{
    private const CONNECTION_SOURCE_ICON_DIR = 'connection-source-icons';

    private const CONNECTION_SOURCE_ICON_SIZE = 128;

    /** Crops the uploaded file to a centered square, resizes it down, and stores it on the public disk. */
    public static function saveConnectionSourceIcon(UploadedFile $file): string
    {
        $image = Image::read($file);
        $image->cover(self::CONNECTION_SOURCE_ICON_SIZE, self::CONNECTION_SOURCE_ICON_SIZE);

        $path = self::CONNECTION_SOURCE_ICON_DIR . '/' . Str::uuid() . '.png';
        Storage::disk('public')->put($path, (string)$image->encode(new PngEncoder()));

        return $path;
    }

    public static function deleteConnectionSourceIcon(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public static function reencodeAsPng(string $full_file_path)
    {
        $image = Image::read($full_file_path);
        $image->encode(new PngEncoder())->save($full_file_path);
    }

    public static function createPreviewImage(string $full_file_path, int $dimensions, string $output)
    {
        $image = Image::read($full_file_path);
        $image->pad($dimensions, $dimensions, new Color(0, 0, 0, 0))->encode(new PngEncoder())->save($output);
    }

    public static function createJpegCopy(string $full_file_path, string $output)
    {
        $image = Image::read($full_file_path);
        $image->encode(new JpegEncoder())->save($output);
    }

    public static function getUploadDirectory(): string
    {
        return storage_path('app/public' . self::getRelativeUploadDirectory());
    }

    public static function getRelativeUploadDirectory(bool $startingSlash = true): string
    {
        return ($startingSlash ? '/' : '') . 'uploads';
    }

    public static function getFileSize(string $path): int
    {
        if (!is_file($path)) return 0;

        $size = filesize($path);
        return $size === false ? 0 : $size;
    }
}
