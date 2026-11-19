<?php

namespace App\Console\Commands;

use App\Models\Upload;
use App\Util\UploadUtil;
use Illuminate\Console\Command;

class GenerateVideoPreviews extends Command
{
    protected $signature = 'upload:video-previews {--force : Also replace thumbnails that already exist}';

    protected $description = 'Generate thumbnails (and read the dimensions) for uploaded videos that have none, e.g. those uploaded before this existed';

    public function handle(): int
    {
        $dir = UploadUtil::getUploadDirectory();
        $made = 0;
        $failed = 0;

        Upload::with('folder')->whereIn('extension', ['webm', 'mp4'])->each(function (Upload $upload) use ($dir, &$made, &$failed) {
            // Folders can switch thumbnails off; that is respected here as it is on upload
            if ($upload->folder?->disable_thumbnails) {
                return;
            }
            $paths = $upload->getFilePaths($dir, $upload->getFilenames());
            if (!$this->option('force') && is_file($paths['preview'])) {
                return;
            }
            if (!is_file($paths['full'])) {
                $this->warn("Missing file for upload {$upload->id}");
                $failed++;

                return;
            }

            $size = UploadUtil::createVideoPreview($paths['full'], $paths['preview']);
            if ($size === null) {
                $this->warn("No frame could be taken from upload {$upload->id}");
                $failed++;

                return;
            }
            [$upload->width, $upload->height] = $size;
            $upload->calculateFileSizes($paths);
            $upload->save();
            $made++;
        });

        $this->info("Generated $made thumbnail(s), $failed failed.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
