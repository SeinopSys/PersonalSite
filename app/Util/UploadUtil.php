<?php

namespace App\Util;

use Intervention\Image\Colors\Rgb\Color;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Laravel\Facades\Image;

class UploadUtil
{
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
}
