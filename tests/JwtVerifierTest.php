<?php

declare(strict_types=1);

namespace WorldSpot\IDPClient\Tests;

use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;
use WorldSpot\IDPClient\Auth\JwtVerifier;
use WorldSpot\IDPClient\Auth\TokenManager;

/**
 * Regression-lock for the R11 security fix: idp-client must cryptographically
 * verify a JWT's signature (via the IDP's published JWKS) before trusting any
 * claims, not just base64-decode the payload.
 *
 * Before the fix, TokenManager::decodeToken()/tokenHasRoles() would happily
 * accept a token with a forged/tampered signature since they never checked
 * it — these tests fail against that old behavior and pass against the fix.
 */
class JwtVerifierTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        @unlink($this->cacheFileFor($this->idpUrl));
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

    private function signToken(array $payload, string $privateKeyPem): string
    {
        $payload['exp'] = $payload['exp'] ?? (time() + 3600);
        $payload['iat'] = $payload['iat'] ?? time();
        return JWT::encode($payload, $privateKeyPem, 'RS256', $this->kid);
    }

    public function testTokenSignedWithCorrectKeyIsTrusted(): void
    {
        $token = $this->signToken(['sub' => 'user@example.com', 'roles' => ['admin']], $this->privateKeyPem);

        $payload = JwtVerifier::verify($token, $this->idpUrl);

        $this->assertIsArray($payload, 'A correctly-signed token must verify');
        $this->assertSame('user@example.com', $payload['sub']);
        $this->assertSame(['admin'], $payload['roles']);
    }

    public function testTokenWithTamperedSignatureIsRejected(): void
    {
        $token = $this->signToken(['sub' => 'user@example.com', 'roles' => ['admin']], $this->privateKeyPem);
        [$header, $payloadSeg, $sig] = explode('.', $token);
        // Flip the signature so it no longer matches.
        $tampered = $header . '.' . $payloadSeg . '.' . strrev($sig);

        $result = JwtVerifier::verify($tampered, $this->idpUrl);

        $this->assertNull($result, 'A token with a tampered signature must be rejected');
    }

    public function testTokenSignedWithDifferentKeyIsRejected(): void
    {
        // Forged token: claims are attacker-controlled ("roles": ["admin"])
        // but signed with a keypair the IDP never published.
        $forged = $this->signToken(['sub' => 'attacker@example.com', 'roles' => ['admin']], $this->wrongKeyPem);

        $result = JwtVerifier::verify($forged, $this->idpUrl);

        $this->assertNull($result, 'A token signed with an untrusted key must be rejected, even with well-formed claims');
    }

    public function testTokenManagerDecodeTokenRejectsForgedSignature(): void
    {
        // This is the real-world regression: TokenManager previously only
        // base64-decoded the payload (decodeToken()/tokenHasRoles()) and
        // would trust attacker-controlled claims from ANY signature.
        $forged = $this->signToken(['sub' => 'attacker@example.com', 'roles' => ['admin']], $this->wrongKeyPem);

        $tokenManager = new TokenManager();
        $verified = $tokenManager->decodeToken($forged, $this->idpUrl);

        $this->assertNull($verified, 'TokenManager must reject tokens with an untrusted signature');
    }

    public function testTokenManagerDecodeTokenAcceptsGenuineToken(): void
    {
        $token = $this->signToken(['sub' => 'user@example.com', 'roles' => ['editor']], $this->privateKeyPem);

        $tokenManager = new TokenManager();
        $payload = $tokenManager->decodeToken($token, $this->idpUrl);

        $this->assertIsArray($payload);
        $this->assertSame('user@example.com', $payload['sub']);
    }
}
