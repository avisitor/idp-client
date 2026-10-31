<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Tests;

use PHPUnit\Framework\TestCase;
use WorldSpot\IDPClient\Auth\Providers\LocalAuthProvider;
use WorldSpot\IDPClient\Auth\TokenStore;

class StubLocalAuthProvider extends LocalAuthProvider {
    protected function getConnection(): \PDO {
        throw new \RuntimeException('No database in tests');
    }

    protected function loadUserData(string $username): array {
        return [];
    }
}

class LocalAuthProviderTest extends TestCase {
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

    public function testLogoutClearsDurableToken(): void {
        $token = 'aaa.bbb.ccc';
        TokenStore::store($token);
        // Seed the durable cookie as it arrives on a real request: without
        // clearing it, TokenStore::get() would still return this token.
        $_COOKIE[TokenStore::COOKIE_NAME] = $token;

        $provider = new StubLocalAuthProvider([
            'app_base_url' => 'https://app.test',
            'app_auth_path' => 'auth',
        ]);

        $result = $provider->logout(null);

        $this->assertTrue($result['success']);
        $this->assertNull(TokenStore::get());
    }
}
