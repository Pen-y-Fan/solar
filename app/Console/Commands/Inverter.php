<?php

namespace App\Console\Commands;

use App\Imports\InverterImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class Inverter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:inverter';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import inverter data from xls files, upload files to storage/app/uploads/';

    /**
     * Execute the console command.
     *
     * @throws \Throwable
     */
    public function handle(InverterImport $inverterImport)
    {
        $this->info('Finding inverter data.');
        $directory = env('APP_ENV') === 'testing' ? 'tests' : 'uploads';

        // find the file in the uploads directory
        $files = Storage::files($directory);

        if (count($files) === 0) {
            $this->fail('No files found, upload files to storage/app/uploads/');
        }

        if (count($files) === 1) {
            $file = $files[0];
        } else {
            $file = $this->choice(
                'Choose the input file',
                $files,
                0
            );
        }

        Excel::import(
            $inverterImport,
            $file,
            null,
            \Maatwebsite\Excel\Excel::XLS
        );
        $this->info('Successfully imported inverter data!');

        if (env('APP_ENV') !== 'testing') {
            $newLocation = str_replace('uploads', 'uploads/processed', $file);
            Storage::move($file, $newLocation);
            $this->info('File processed and moved to: ' . $newLocation);
        }
    }
}
