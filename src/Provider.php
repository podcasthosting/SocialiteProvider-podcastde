<?php

declare(strict_types=1);

namespace Podcasthosting\Podcastde;

use Illuminate\Support\Arr;
use Laravel\Socialite\Two\Token;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;
use UnexpectedValueException;

class Provider extends AbstractProvider
{
    public const IDENTIFIER = 'PODCASTDE';

    public const BASE_URL = 'https://www.podcast.de';

    public const API_URL = 'https://www.podcast.de/api';

    /** @var list<string> */
    protected $scopes = [Scope::UserReadOnly->value];

    protected $scopeSeparator = ' ';

    protected $usesPKCE = true;

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(self::BASE_URL . '/oauth/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return self::BASE_URL . '/oauth/token';
    }

    /**
     * @return array<mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(
            self::API_URL . '/oauth/me',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ],
            ],
        );

        $user = json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);

        return is_array($user) ? $user : [];
    }

    /**
     * podcast.de serialises the User model directly (no data/attributes
     * envelope). The "name" column is hidden, so the display name is composed
     * from first and last name like the backend's accessor does.
     *
     * @param  array<mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User())->setRaw($user)->map([
            'id'       => $user['id'] ?? null,
            'nickname' => $user['username'] ?? null,
            'name'     => $this->displayName($user),
            'email'    => $user['email'] ?? null,
            'avatar'   => $user['avatar_url'] ?? null,
        ]);
    }

    /**
     * Same rule as the backend: "first last", trimmed, falling back to the username.
     *
     * @param  array<mixed>  $user
     */
    private function displayName(array $user): ?string
    {
        $first = $user['first_name'] ?? null;
        $last = $user['last_name'] ?? null;

        $name = trim((is_string($first) ? $first : '') . ' ' . (is_string($last) ? $last : ''));

        if ($name !== '') {
            return $name;
        }

        $username = $user['username'] ?? null;

        return is_string($username) && $username !== '' ? $username : null;
    }

    public function refreshToken($refreshToken): Token
    {
        $response = $this->getRefreshTokenResponse($refreshToken);
        $response = is_array($response) ? $response : [];

        $accessToken = Arr::get($response, 'access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new UnexpectedValueException('The token response of podcast.de does not contain an access token.');
        }

        // RFC 6749 section 6: without a new refresh token the client keeps the old one.
        $newRefreshToken = Arr::get($response, 'refresh_token');
        $expiresIn = Arr::get($response, 'expires_in');

        return new Token(
            $accessToken,
            is_string($newRefreshToken) && $newRefreshToken !== '' ? $newRefreshToken : (string) $refreshToken,
            is_numeric($expiresIn) ? (int) $expiresIn : 0,
            $this->parseApprovedScopes($response),
        );
    }

    /**
     * Passport does not return the granted scopes in the token response, but
     * embeds them as "scopes" claim in the access token (JWT).
     *
     * @param  array<mixed>  $body
     * @return list<string>
     */
    protected function parseApprovedScopes($body): array
    {
        $scopes = array_values(array_filter(
            parent::parseApprovedScopes($body),
            static fn (mixed $scope): bool => is_string($scope) && $scope !== '',
        ));

        if ($scopes !== []) {
            return $scopes;
        }

        $accessToken = $body['access_token'] ?? null;
        $segments = is_string($accessToken) ? explode('.', $accessToken) : [];

        if (count($segments) !== 3) {
            return [];
        }

        $payload = json_decode(
            (string) base64_decode(strtr($segments[1], '-_', '+/'), true),
            true,
        );

        $claimed = is_array($payload) ? ($payload['scopes'] ?? []) : [];

        return is_array($claimed) ? array_values(array_filter($claimed, 'is_string')) : [];
    }
}
