<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Tests;

use PHPUnit\Framework\TestCase;
use WorldSpot\IDPClient\Auth\Providers\ExternalAuthProvider;

/**
 * Provider diagnostics go to the shared Monolog file (not the PHP error log)
 * and honor the application's MONOLOG_LEVEL threshold.
 */
class AuthLoggingTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        // Logger instances are cached per context, so each test uses a fresh one.
        $context = 'idpclient-test-' . bin2hex(random_bytes(4));
        $this->logPath = sys_get_temp_dir() . "/{$context}/errorlog";
        putenv("LOG_CONTEXT={$context}");
        putenv('MONOLOG_FILE_TEMPLATE=' . sys_get_temp_dir() . '/%context%/errorlog');
        putenv('MONOLOG_LEVEL=info');
    }

    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }
        if (is_dir(dirname($this->logPath))) {
            rmdir(dirname($this->logPath));
        }
        putenv('LOG_CONTEXT');
        putenv('MONOLOG_FILE_TEMPLATE');
        putenv('MONOLOG_LEVEL');
    }

    public function testDebuglogIsFilteredAndWarningsAreKept(): void
    {
        $provider = (new \ReflectionClass(ExternalAuthProvider::class))->newInstanceWithoutConstructor();

        $provider->debuglog('routine-session-detail');
        $provider->debuglog('token refresh failed', '', \Avisitor\Monolog\Levels::WARNING);

        $contents = (string)file_get_contents($this->logPath);
        $this->assertStringNotContainsString('routine-session-detail', $contents);
        $this->assertStringContainsString('token refresh failed', $contents);
        $this->assertStringContainsString('"level_name":"WARNING"', $contents);
    }
}
