<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Tests;

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use WorldSpot\IDPClient\Auth\SessionRecovery;
use WorldSpot\IDPClient\Auth\TokenStore;

/**
 * SessionRecovery re-establishes $_SESSION from the durable token when the
 * native PHP session has been garbage-collected. It may only trust claims
 * from a token that passed JwtVerifier (signature + exp), including after a
 * refresh — never from unverified decoding.
 */
class SessionRecoveryTest extends TestCase
{
    private string $idpUrl;
    private string $privateKeyPem;
    private string $wrongKeyPem;
    private string $kid = 'test-kid-1';

    protected function setUp(): void
    {
        $this->idpUrl = 'https://fake-idp.test';

        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $pem = '';
        openssl_pkey_export($res, $pem);
        $this->privateKeyPem = $pem;

        $res2 = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $pem2 = '';
        openssl_pkey_export($res2, $pem2);
        $this->wrongKeyPem = $pem2;

        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->privateKeyPem));
        $jwk = $this->jwkFromRsaDetails($details, $this->kid);

        $cacheFile = $this->cacheFileFor($this->idpUrl);
        @file_put_contents($cacheFile, json_encode(['keys' => [$jwk]]));
        // Force the cache to look fresh so the test never hits the network.
        @touch($cacheFile);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        @unlink($this->cacheFileFor($this->idpUrl));
        $_SESSION = [];
        $_COOKIE = [];
    }

    private function cacheFileFor(string $idpUrl): string
    {
        return sys_get_temp_dir() . '/idp-client-jwks-' . md5($idpUrl) . '.json';
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function jwkFromRsaDetails(array $details, string $kid): array
    {
        $rsa = $details['rsa'];
        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => $this->b64url($rsa['n']),
            'e' => $this->b64url($rsa['e']),
        ];
    }

    private function signToken(array $payload, ?string $privateKeyPem = null): string
    {
        $payload['exp'] = $payload['exp'] ?? (time() + 3600);
        $payload['iat'] = $payload['iat'] ?? time();
        return JWT::encode($payload, $privateKeyPem ?? $this->privateKeyPem, 'RS256', $this->kid);
    }

    private function validPayload(): array
    {
        return ['sub' => 'a@b.com', 'appId' => 'app-x', 'roles' => ['editor']];
    }

    public function testRecoversValidToken(): void
    {
        $_COOKIE[TokenStore::COOKIE_NAME] = $this->signToken($this->validPayload());

        $recovery = new SessionRecovery();

        $this->assertTrue($recovery->attempt('app-x', $this->idpUrl));

        $this->assertTrue($_SESSION['authenticated']);
        $this->assertSame('a@b.com', $_SESSION['email']);
        $this->assertSame('a@b.com', $_SESSION['username']);
        $this->assertSame(['editor'], $_SESSION['roles']);
        $this->assertFalse($_SESSION['is_admin']);
    }

    public function testRejectsTokenForOtherApp(): void
    {
        $_COOKIE[TokenStore::COOKIE_NAME] = $this->signToken($this->validPayload());

        $recovery = new SessionRecovery();

        $this->assertFalse($recovery->attempt('app-y', $this->idpUrl));
        $this->assertArrayNotHasKey('authenticated', $_SESSION);
    }

    public function testRejectsGarbageToken(): void
    {
        $_COOKIE[TokenStore::COOKIE_NAME] = 'garbage';

        $recovery = new SessionRecovery(null, static fn (string $t): ?string => null);

        $this->assertFalse($recovery->attempt('app-x', $this->idpUrl));
        $this->assertArrayNotHasKey('authenticated', $_SESSION);
    }

    public function testRejectsForgedSignature(): void
    {
        // Claims are attacker-controlled ("roles": ["admin"]) but the token is
        // signed with a keypair the IDP never published.
        $forged = $this->signToken(
            ['sub' => 'attacker@example.com', 'appId' => 'app-x', 'roles' => ['admin']],
            $this->wrongKeyPem
        );
        $_COOKIE[TokenStore::COOKIE_NAME] = $forged;

        $recovery = new SessionRecovery(null, static fn (string $t): ?string => null);

        $this->assertFalse($recovery->attempt('app-x', $this->idpUrl));
        $this->assertArrayNotHasKey('authenticated', $_SESSION);
    }

    public function testRefreshesExpiredToken(): void
    {
        $expired = $this->signToken(['exp' => time() - 3600] + $this->validPayload());
        $_COOKIE[TokenStore::COOKIE_NAME] = $expired;

        $fresh = $this->signToken($this->validPayload());
        $recovery = new SessionRecovery(
            null,
            static fn (string $t): ?string => $t === $expired ? $fresh : null
        );

        $this->assertTrue($recovery->attempt('app-x', $this->idpUrl));

        $this->assertSame($fresh, TokenStore::get());
        $this->assertTrue($_SESSION['authenticated']);
        $this->assertSame('a@b.com', $_SESSION['email']);
    }

    public function testRefreshFailureReturnsFalse(): void
    {
        $_COOKIE[TokenStore::COOKIE_NAME] = $this->signToken(['exp' => time() - 3600] + $this->validPayload());

        $recovery = new SessionRecovery(null, static fn (string $t): ?string => null);

        $this->assertFalse($recovery->attempt('app-x', $this->idpUrl));
        $this->assertArrayNotHasKey('authenticated', $_SESSION);
    }

    public function testHydratesAdminFlag(): void
    {
        $_COOKIE[TokenStore::COOKIE_NAME] = $this->signToken(
            ['sub' => 'a@b.com', 'appId' => 'app-x', 'roles' => ['admin']]
        );

        $recovery = new SessionRecovery();

        $this->assertTrue($recovery->attempt('app-x', $this->idpUrl));
        $this->assertTrue($_SESSION['is_admin']);
    }

    public function testNoTokenReturnsFalse(): void
    {
        $recovery = new SessionRecovery();

        $this->assertFalse($recovery->attempt('app-x', $this->idpUrl));
        $this->assertArrayNotHasKey('authenticated', $_SESSION);
    }
}
