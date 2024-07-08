<?php

namespace App\Console\Commands;

use App\Imports\InverterImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ReaderType;
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
        Log::info('Finding inverter data.');
        $directory = 'uploads';

        // find the files in the uploads directory
        $files = Storage::files($directory);

        $count = 0;
        foreach ($files as $file) {

            if (! Str::endsWith($file, '.xls')) {
                $this->error('File not processed as it is not an excel .xls file:');
                $this->error($file);
                continue;
            }

            try {
                Excel::import(
                    $inverterImport,
                    $file,
                    null,
                    ReaderType::XLS
                );
            } catch ( \PhpOffice\PhpSpreadsheet\Reader\Exception $exception) {
                $this->error('Failed to import inverter data for file:');
                $this->error($file);
                continue;
            }

            $newLocation = Str::replaceFirst('uploads', 'uploads/processed', $file);
            Storage::move($file, $newLocation);

            $this->info('File processed and moved to:');
            $this->info($newLocation);
            Log::info('File processed and moved to:', ['file' => $newLocation]);

            $count++;
        }
        if ($count === 0) {
            $this->fail('No files processed, upload files to storage/app/uploads/');
        }

        $this->info('Successfully imported inverter data!');
    }
}
