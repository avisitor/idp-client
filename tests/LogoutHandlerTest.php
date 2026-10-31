<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Tests;

use PHPUnit\Framework\TestCase;
use WorldSpot\IDPClient\Auth\LogoutHandler;
use WorldSpot\IDPClient\Auth\TokenStore;

class ExposedLogoutHandler extends LogoutHandler {
    public function exposeClearAuthentication(): void {
        $this->clearAuthentication();
    }
}

class LogoutHandlerTest extends TestCase {
    private function makeHandler(): ExposedLogoutHandler {
        return new ExposedLogoutHandler([
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

    protected function tearDown(): void {
        $_SESSION = [];
        $_COOKIE = [];
    }

    public function testClearAuthenticationClearsDurableToken(): void {
        $token = 'aaa.bbb.ccc';
        TokenStore::store($token);
        // Seed the durable cookie as it arrives on a real request: without
        // clearing it, TokenStore::get() would still return this token.
        $_COOKIE[TokenStore::COOKIE_NAME] = $token;

        $this->makeHandler()->exposeClearAuthentication();

        $this->assertNull(TokenStore::get());
    }
}
