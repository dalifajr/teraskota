<?php

namespace Tests\Feature;

use Tests\TestCase;

class PackageInfinityFreeTest extends TestCase
{
    public function test_package_infinityfree_command_runs_successfully(): void
    {
        $this->artisan('app:package-infinityfree', ['--force' => true])
            ->assertExitCode(0);

        $distDir = base_path('dist/infinityfree');
        $this->assertDirectoryExists($distDir);
        $this->assertFileExists($distDir . '/teraskota_infinityfree_htdocs.zip');
        $this->assertFileExists($distDir . '/core.zip');
        $this->assertFileExists($distDir . '/htdocs.zip');
        $this->assertFileExists($distDir . '/.env');

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($distDir . '/teraskota_infinityfree_htdocs.zip'));
        $envInZip = $zip->getFromName('core/.env');
        $this->assertNotFalse($envInZip);
        $this->assertStringContainsString('if0_42907576_teraskota', $envInZip);
        $this->assertStringContainsString('Endpoint1140', $envInZip);
        $zip->close();
    }
}
