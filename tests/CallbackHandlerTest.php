<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Tests;

use PHPUnit\Framework\TestCase;
use WorldSpot\IDPClient\Auth\CallbackHandler;
use WorldSpot\IDPClient\Auth\TokenStore;

class ExposedCallbackHandler extends CallbackHandler {
    public function exposePersist(string $token): void {
        $this->persistToken($token);
    }
}

class CallbackHandlerTest extends TestCase {
    private function makeHandler(): ExposedCallbackHandler {
        return new ExposedCallbackHandler([
            'app_name' => 'test',
            'app_url' => 'http://localhost',
            'idp_url' => 'http://127.0.0.1:9/',
            'app_id' => 'test',
            'support_email' => 'support@example.com',
        ]);
    }

    protected function setUp(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $_COOKIE = [];
    }

    public function testPersistTokenStoresToken(): void {
        $t = 'aaa.bbb.ccc';
        $this->makeHandler()->exposePersist($t);
        $this->assertSame($t, TokenStore::get());
    }

    public function testPersistTokenClearsOnEmpty(): void {
        $this->makeHandler()->exposePersist('');
        $this->assertNull(TokenStore::get());
    }
}
