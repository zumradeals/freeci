<?php

namespace Tests\Feature;

use App\Integrations\FileScan\ClamAvScanner;
use App\Integrations\FileScan\FileScanner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeScanner;
use Tests\TestCase;

class ScannerReadinessTest extends TestCase
{
    public function test_version_success_does_not_make_a_broken_daemon_operational(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('POSIX executable fixture');
        }
        Cache::forget('freeci.clamav.operational');
        $binary = tempnam(sys_get_temp_dir(), 'scanner-test-');
        try {
            file_put_contents($binary, "#!/bin/sh\nif [ \"\$1\" = \"--version\" ]; then exit 0; fi\nexit 2\n");
            chmod($binary, 0700);
            $this->assertFalse((new ClamAvScanner($binary))->isOperational());
        } finally {
            unlink($binary);
            Cache::forget('freeci.clamav.operational');
        }
    }

    public function test_files_check_fails_when_the_scanner_is_unavailable(): void
    {
        Storage::fake('private_files');
        FakeScanner::$operational = false;
        $this->app->bind(FileScanner::class, FakeScanner::class);
        try {
            $this->artisan('freeci:files:check')->assertFailed();
        } finally {
            FakeScanner::$operational = true;
        }
    }
}
