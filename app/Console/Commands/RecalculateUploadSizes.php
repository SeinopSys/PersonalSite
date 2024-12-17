<?php

namespace App\Console\Commands;

use App\Models\Upload;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

class RecalculateUploadSizes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upload:recalculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate sizes of uploaded files';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $uploads = Upload::all();
        $this->withProgressBar($uploads, function (Upload $upload, ProgressBar $bar) {
            $upload->calculateFileSizes();
            $upload->save();
            $bar->advance();
        });

        return Command::SUCCESS;
    }
}
