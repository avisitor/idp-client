<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Tests;

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use WorldSpot\IDPClient\Auth\TokenStore;
use WorldSpot\IDPClient\IDPManager;

class IDPManagerTest extends TestCase {
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
        $_SESSION = ['email' => 'a@b.com'];
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

    public function testGetValidTokenAcceptsOwnAppToken(): void {
        $token = $this->validToken('app-x');
        TokenStore::store($token);

        $manager = new IDPManager();

        $this->assertSame($token, $manager->getValidToken());
    }

    public function testGetValidTokenRejectsForeignAppToken(): void {
        TokenStore::store($this->validToken('app-y'));

        $manager = new IDPManager();

        $this->assertNull($manager->getValidToken());
    }
}
