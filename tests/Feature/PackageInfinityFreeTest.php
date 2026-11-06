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
        $this->assertFileExists($distDir . '/core.zip');
        $this->assertFileExists($distDir . '/htdocs.zip');
        $this->assertFileExists($distDir . '/.env.infinityfree.example');
    }
}

