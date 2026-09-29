<?php

namespace App\Services;

use Google\Client;
use Throwable;

/**
 * Verifies Google ID tokens issued by the Android Credential Manager.
 *
 * When `config('services.google.mock')` is true the service short-circuits the
 * network entirely and deterministically derives an identity from the supplied
 * token, so development and the test suite work without real Google credentials.
 * Repeated calls with the same token always resolve to the same identity.
 */
class GoogleAuthService
{
    /**
     * Verify a Google ID token and return the normalized identity.
     *
     * @return array{google_id: string, email: string, name: string, avatar_url: string|null}|null
     *                                                                                             Null when the token cannot be verified.
     */
    public function verify(string $idToken): ?array
    {
        $idToken = trim($idToken);

        if ($idToken === '') {
            return null;
        }

        return $this->isMockEnabled()
            ? $this->mockIdentity($idToken)
            : $this->verifyWithGoogle($idToken);
    }

    /**
     * Verify the token against Google's public keys.
     *
     * @return array{google_id: string, email: string, name: string, avatar_url: string|null}|null
     */
    protected function verifyWithGoogle(string $idToken): ?array
    {
        try {
            $client = new Client(['client_id' => config('services.google.client_id')]);
            $client->setClientId((string) config('services.google.client_id'));
            $client->setClientSecret((string) config('services.google.client_secret'));

            $payload = $client->verifyIdToken($idToken);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload) || empty($payload['sub']) || empty($payload['email'])) {
            return null;
        }

        return [
            'google_id' => (string) $payload['sub'],
            'email' => (string) $payload['email'],
            'name' => (string) ($payload['name'] ?? $payload['email']),
            'avatar_url' => isset($payload['picture']) ? (string) $payload['picture'] : null,
        ];
    }

    /**
     * Deterministically derive a fake identity from the raw token.
     *
     * @return array{google_id: string, email: string, name: string, avatar_url: string|null}
     */
    protected function mockIdentity(string $idToken): array
    {
        $hash = hash('sha256', $idToken);

        return [
            'google_id' => 'mock-'.substr($hash, 0, 24),
            'email' => 'mock-'.substr($hash, 0, 16).'@example.com',
            'name' => 'Google User '.substr($hash, 0, 6),
            'avatar_url' => 'https://lh3.googleusercontent.com/a/mock-'.substr($hash, 0, 12),
        ];
    }

    /**
     * Whether mock mode is active (no real network calls).
     */
    protected function isMockEnabled(): bool
    {
        return (bool) config('services.google.mock', true);
    }
}
