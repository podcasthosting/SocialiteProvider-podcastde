<?php

declare(strict_types=1);

namespace Podcasthosting\Podcastde;

use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

final class Provider extends AbstractProvider
{
    public const IDENTIFIER = 'PODCASTDE';

    private const BASE_URL = 'https://www.podcast.de';

    /** @var list<string> */
    protected $scopes = ['read-only-user'];

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(self::BASE_URL . '/oauth/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return self::BASE_URL . '/oauth/token';
    }

    /**
     * @return array<int|string, mixed>
     *
     * @throws \JsonException
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(
            self::BASE_URL . '/api/user',
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
            ],
        );

        $decoded = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \JsonException('Expected JSON object from podcast.de user endpoint.');
        }

        return $decoded;
    }

    /**
     * @param  array<int|string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        $data = $user['data'] ?? null;
        $attributes = is_array($data) ? ($data['attributes'] ?? null) : null;

        if (!is_array($attributes)) {
            throw new \RuntimeException('podcast.de user response is missing data.attributes.');
        }

        return (new User())->setRaw($attributes)->map([
            'id'       => $attributes['id'] ?? null,
            'nickname' => $attributes['nickname'] ?? null,
            'name'     => $attributes['name'] ?? null,
            'email'    => $attributes['email'] ?? null,
            'avatar'   => $attributes['avatar'] ?? null,
        ]);
    }
}
