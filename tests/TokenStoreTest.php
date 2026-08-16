<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Tests;

use PHPUnit\Framework\TestCase;
use WorldSpot\IDPClient\Auth\TokenStore;

class TokenStoreTest extends TestCase
{
    private function fakeJwt(array $payload): string
    {
        $b = static fn($s) => str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($s));
        return $b(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b(json_encode($payload)) . '.sig';
    }

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $_COOKIE = [];
    }

    public function testDecode(): void
    {
        $t = $this->fakeJwt(['sub' => 'a@b.com', 'exp' => time() + 3600]);
        $this->assertSame('a@b.com', TokenStore::decode($t)['sub']);
        $this->assertNull(TokenStore::decode('garbage'));
        $this->assertNull(TokenStore::decode(''));
    }

    public function testNeedsRefresh(): void
    {
        $this->assertFalse(TokenStore::needsRefresh($this->fakeJwt(['exp' => time() + 3600])));
        $this->assertTrue(TokenStore::needsRefresh($this->fakeJwt(['exp' => time() - 10])));
        $this->assertTrue(TokenStore::needsRefresh($this->fakeJwt(['sub' => 'x'])));
        $this->assertTrue(TokenStore::needsRefresh(null));
    }

    public function testStoreGetSession(): void
    {
        $t = $this->fakeJwt(['exp' => time() + 3600]);
        TokenStore::store($t);
        $this->assertSame($t, TokenStore::get());
    }

    public function testGetFallsBackToCookie(): void
    {
        $t = $this->fakeJwt(['exp' => time() + 3600]);
        unset($_SESSION['jwt_token']);
        $_COOKIE[TokenStore::COOKIE_NAME] = $t;
        $this->assertSame($t, TokenStore::get());
    }

    public function testClear(): void
    {
        $t = $this->fakeJwt(['exp' => time() + 3600]);
        TokenStore::store($t);
        TokenStore::clear();
        // Simulate the browser dropping the expired Set-Cookie.
        unset($_COOKIE[TokenStore::COOKIE_NAME]);
        $this->assertNull(TokenStore::get());
    }

    public function testRefreshGracefulOnFailure(): void
    {
        $t = $this->fakeJwt(['exp' => time() - 10, 'appId' => 'x']);
        $this->assertNull(TokenStore::refresh($t, 'x', 'http://127.0.0.1:9/'));
    }
}
