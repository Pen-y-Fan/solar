<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class TestCoverage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:coverage
                            {--format=html : The format of the coverage report (html, text, clover)}
                            {--open : Open the coverage report in the browser}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run PHPUnit tests with code coverage';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $format = $this->option('format');
        $open = $this->option('open');

        $this->info('Running tests with code coverage...');

        try {
            // Set Xdebug mode to coverage
            $env = ['XDEBUG_MODE' => 'coverage'];

            // Build the PHPUnit command based on the format option
            $command = ['./vendor/bin/phpunit'];

            switch ($format) {
                case 'html':
                    $command[] = '--coverage-html';
                    $command[] = 'coverage/html';
                    $outputPath = 'coverage/html/index.html';
                    break;
                case 'text':
                    $command[] = '--coverage-text';
                    $outputPath = null;
                    break;
                case 'clover':
                    $command[] = '--coverage-clover';
                    $command[] = 'coverage/clover.xml';
                    $outputPath = 'coverage/clover.xml';
                    break;
                default:
                    $this->error("Invalid format: $format. Supported formats are: html, text, clover");
                    return;
            }

            // Create the coverage directory if it doesn't exist
            if (!is_dir('coverage') && $format !== 'text') {
                mkdir('coverage', 0755, true);
            }

            // Run the PHPUnit command
            $process = new Process($command);
            $process->setEnv($env);
            $process->setTimeout(null);
            $process->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            if (!$process->isSuccessful()) {
                $this->error('Tests failed!');
                return;
            }

            $this->info('Tests completed successfully!');

            // Open the coverage report in the browser if requested
            if ($open && $format === 'html' && file_exists($outputPath)) {
                if (PHP_OS_FAMILY === 'Darwin') {
                    exec('open ' . escapeshellarg($outputPath));
                } elseif (PHP_OS_FAMILY === 'Windows') {
                    exec('start ' . escapeshellarg($outputPath));
                } elseif (PHP_OS_FAMILY === 'Linux') {
                    exec('xdg-open ' . escapeshellarg($outputPath));
                }
                $this->info("Coverage report opened in browser: $outputPath");
            } elseif ($format !== 'text' && file_exists($outputPath)) {
                $this->info("Coverage report generated: $outputPath");
            }
        } catch (\Throwable $th) {
            Log::error('Error running tests with code coverage', ['error message' => $th->getMessage()]);
            $this->error('Error running tests with code coverage:');
            $this->error($th->getMessage());
        }
    }
}
