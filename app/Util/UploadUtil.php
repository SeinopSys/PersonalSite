<?php

namespace App\Util;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Intervention\Image\Colors\Rgb\Color;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Laravel\Facades\Image;

class UploadUtil
{
    /**
     * The largest single file that can be uploaded: what PHP accepts (the smaller of upload_max_filesize and
     * post_max_size, the latter only counting when set), or UPLOAD_MAX_FILE_KB when that is lower still. The web
     * server's own limit (nginx client_max_body_size) can't be read from here and has to be at least this much.
     */
    public static function maxFileBytes(): int
    {
        $limits = array_filter([self::iniBytes('upload_max_filesize'), self::iniBytes('post_max_size')], fn (int $b) => $b > 0);
        if (config('app.upload_max_file_kb')) {
            $limits[] = (int) config('app.upload_max_file_kb') * 1024;
        }

        return $limits ? min($limits) : PHP_INT_MAX;
    }

    /** A php.ini size such as "100M" or "512K" in bytes (0 for unset or unlimited). */
    private static function iniBytes(string $name): int
    {
        $value = trim((string) ini_get($name));
        $number = (int) $value;
        $unit = strtolower(substr($value, -1));

        return match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
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

    /**
     * Writes the thumbnail of a video (its first second, or first frame if it is shorter) the same way image
     * thumbnails are made, and returns the video's dimensions. Null when no frame could be taken: ffmpeg is not
     * installed, or the file is not a video it can read. The upload is fine without a thumbnail.
     *
     * @return array{0: int, 1: int}|null width, height
     */
    public static function createVideoPreview(string $video_path, string $output): ?array
    {
        $frame = tempnam(sys_get_temp_dir(), 'vframe');
        unlink($frame);
        $frame .= '.png';

        try {
            foreach (['1', '0'] as $seek) {
                try {
                    $result = Process::timeout(60)->run([config('app.ffmpeg_binary'), '-v', 'error', '-y', '-ss', $seek, '-i', $video_path, '-frames:v', '1', $frame]);
                } catch (\Throwable $e) {
                    Log::warning('Could not run ffmpeg for a video preview: '.$e->getMessage());

                    return null;
                }
                if ($result->successful() && is_file($frame) && filesize($frame) > 0) {
                    $image = Image::read($frame);
                    $width = $image->width();
                    $height = $image->height();
                    self::createPreviewImage($frame, min($width, $height, 300), $output);

                    return [$width, $height];
                }
            }
            Log::warning("No frame could be taken from $video_path for a preview: ".($result->errorOutput() ?? ''));

            return null;
        } finally {
            @unlink($frame);
        }
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
