<?php

namespace Pderas\LaravelCodeScans\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class CodeScanCommand extends Command
{
    protected $signature = 'code:scan {--ci : Run only CI-safe checks}';

    protected $description = 'Run PHPStan/Larastan static analysis with custom rules and styled report.';

    public function handle()
    {
        $ci = $this->option('ci');
        $this->info('Running code quality checks...');

        $errorFormat = $ci ? 'json' : 'fabled';
        $this->info('Running Larastan (static analysis) with format: ' . $errorFormat);

        $phpstan_cmd = [
            'vendor/bin/phpstan', 'analyse',
            '--no-progress',
            '--memory-limit=512M',
            '--level=' . config('laravel-code-scans.phpstan_level'),
            '--error-format=fabled',
            '--configuration=vendor/pderas/laravel-code-scans/phpstan.neon',
            'app', 'config', 'routes'
        ];

        $process = new Process($phpstan_cmd);
        $process->setTty(Process::isTtySupported());
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });
        $exit = $process->getExitCode() ?? 0;
        if ($exit === 0) {
            $this->info('All code quality checks passed!');
        }
        
        return $exit;
    }
}
