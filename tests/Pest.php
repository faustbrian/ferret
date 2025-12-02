<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

/**
 * Get the path to the test fixtures directory.
 */
function fixturesPath(string $path = ''): string
{
    return __DIR__.'/Fixtures'.($path !== '' ? '/'.mb_ltrim($path, '/') : '');
}

/**
 * Create a temporary directory for testing.
 */
function createTempDir(): string
{
    $tempDir = sys_get_temp_dir().'/ferret-test-'.uniqid('', true);
    mkdir($tempDir, 0o755, true);

    return $tempDir;
}

/**
 * Remove a directory and its contents recursively.
 */
function removeDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }

    rmdir($dir);
}
