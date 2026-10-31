<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Tests;

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use WorldSpot\IDPClient\Auth\Providers\ExternalAuthProvider;
use WorldSpot\IDPClient\Auth\TokenStore;

class ExternalAuthProviderTest extends TestCase {
    private string $idpUrl;
    private string $privateKeyPem;
    private string $kid = 'test-kid-1';

    protected function setUp(): void {
        $this->idpUrl = 'https://fake-idp.test';
        putenv('IDP_URL=' . $this->idpUrl);
        putenv('IDP_APP_ID=app-x');
        $_ENV['IDP_URL'] = $this->idpUrl;
        $_ENV['IDP_APP_ID'] = 'app-x';

        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $pem = '';
        openssl_pkey_export($res, $pem);
        $this->privateKeyPem = $pem;

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

    protected function tearDown(): void {
        @unlink($this->cacheFileFor($this->idpUrl));
        putenv('IDP_URL');
        putenv('IDP_APP_ID');
        unset($_ENV['IDP_URL'], $_ENV['IDP_APP_ID']);
        $_SESSION = [];
        $_COOKIE = [];
    }

    private function cacheFileFor(string $idpUrl): string {
        return sys_get_temp_dir() . '/idp-client-jwks-' . md5($idpUrl) . '.json';
    }

    private function b64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function jwkFromRsaDetails(array $details, string $kid): array {
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

    private function validToken(string $appId): string {
        $payload = [
            'sub' => 'a@b.com',
            'appId' => $appId,
            'roles' => [],
            'exp' => time() + 3600,
            'iat' => time(),
        ];
        return JWT::encode($payload, $this->privateKeyPem, 'RS256', $this->kid);
    }

    public function testIsAuthenticatedRecoversFromDurableCookie(): void {
        $_COOKIE[TokenStore::COOKIE_NAME] = $this->validToken('app-x');

        $provider = new ExternalAuthProvider([]);

        $this->assertTrue($provider->isAuthenticated());
        $this->assertSame('a@b.com', $_SESSION['email']);
    }

    public function testIsAuthenticatedRejectsForeignAppToken(): void {
        $_COOKIE[TokenStore::COOKIE_NAME] = $this->validToken('app-y');

        $provider = new ExternalAuthProvider([]);

        $this->assertFalse($provider->isAuthenticated());
        $this->assertSame([], $_SESSION);
    }

    public function testIsAuthenticatedShortCircuitsWhenAlreadyAuthenticated(): void {
        $_SESSION = ['email' => 'a@b.com', 'authenticated' => true];
        $expectedSession = $_SESSION;
        $_COOKIE[TokenStore::COOKIE_NAME] = 'garbage';

        $provider = new ExternalAuthProvider([]);

        $this->assertTrue($provider->isAuthenticated());
        $this->assertSame($expectedSession, $_SESSION);
    }

    public function testLogoutClearsDurableToken(): void {
        $token = $this->validToken('app-x');
        TokenStore::store($token);
        // Seed the durable cookie as it arrives on a real request: without
        // clearing it, TokenStore::get() would still return this token.
        $_COOKIE[TokenStore::COOKIE_NAME] = $token;

        $provider = new ExternalAuthProvider([
            'app_base_url' => 'https://app.test',
            'app_auth_path' => 'auth',
        ]);

        $result = $provider->logout(null);

        $this->assertTrue($result['success']);
        $this->assertNull(TokenStore::get());
    }

    public function testLogoutCatchPathClearsDurableToken(): void {
        $token = $this->validToken('app-x');
        TokenStore::store($token);
        // Seed the durable cookie as it arrives on a real request: without
        // clearing it, TokenStore::get() would still return this token.
        $_COOKIE[TokenStore::COOKIE_NAME] = $token;

        // Missing app_base_url forces logout() into its catch path.
        $provider = new ExternalAuthProvider([]);

        $result = $provider->logout(null);

        $this->assertTrue($result['success']);
        $this->assertNull(TokenStore::get());
    }
}
