<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\Finder\Finder;

class ConvertImagesToGifs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:convert-images-to-gifs
                            {path? : Folder to scan, defaults to current working directory}
                            {--prefix= : Prefix to print before each converted filename}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert all PNG files to GIFs next to the originals, preserving transparency';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = realpath($this->argument('path') ?? getcwd());
        $prefix = $this->option('prefix') ?? '';
        $converted = [];

        $this->info("Scanning: $path");

        $finder = new Finder();
        $finder->files()->in($path)->name('*.png');

        foreach ($finder as $file) {
            $pngPath = $file->getRealPath();
            $gifPath = preg_replace('/\.png$/i', '.gif', $pngPath);
            $converted[] = str_replace($path, '', $gifPath);

            if (file_exists($gifPath)) {
                $this->line("Skipping (exists): $gifPath");
                continue;
            }

            $this->line("Converting: $pngPath");

            $pngImage = Image::read($pngPath);
            $pngImage->toGif()->save($gifPath);
        }

        $this->info("\nConversion complete.");
        if (!empty($converted)) {
            $this->info("\nGenerated GIF files:");
            foreach ($converted as $file) {
                $this->line($prefix . $file);
            }
        } else {
            $this->warn("No files were converted.");
        }
        return Command::SUCCESS;
    }
}
