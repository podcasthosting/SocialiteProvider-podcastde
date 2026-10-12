<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use SocialiteProviders\Manager\OAuth2\User;

describe('getUserByToken', function () {
    it('fetches the current account from www.podcast.de with the bearer token', function () {
        $history = [];
        $provider = provider();
        $provider->setHttpClient(httpClient([jsonResponse(userResponse())], $history));

        $user = invoke($provider, 'getUserByToken', 'access-token');

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];

        expect($user)->toBe(userResponse())
            ->and($history)->toHaveCount(1)
            ->and($request->getMethod())->toBe('GET')
            ->and((string) $request->getUri())->toBe('https://www.podcast.de/api/oauth/me')
            ->and($request->getHeaderLine('Authorization'))->toBe('Bearer access-token')
            ->and($request->getHeaderLine('Accept'))->toBe('application/json');
    });

    it('does not use /api/user, which only supports update and delete', function () {
        $history = [];
        $provider = provider();
        $provider->setHttpClient(httpClient([jsonResponse(userResponse())], $history));

        invoke($provider, 'getUserByToken', 'access-token');

        expect((string) $history[0]['request']->getUri())->not->toEndWith('/api/user');
    });

    it('rejects a body that is not json', function () {
        $provider = provider();
        $provider->setHttpClient(httpClient([new Response(200, [], '<html>Maintenance</html>')]));

        invoke($provider, 'getUserByToken', 'access-token');
    })->throws(JsonException::class);

    it('treats a json scalar as an empty user', function (string $body) {
        $provider = provider();
        $provider->setHttpClient(httpClient([new Response(200, [], $body)]));

        expect(invoke($provider, 'getUserByToken', 'access-token'))->toBe([]);
    })->with(['null', '"string"', '42', 'true']);

    it('propagates http errors of the api', function (int $status, string $exception) {
        $provider = provider();
        $provider->setHttpClient(httpClient([jsonResponse(['message' => 'nope'], $status)]));

        expect(fn () => invoke($provider, 'getUserByToken', 'access-token'))->toThrow($exception);
    })->with([
        'unauthenticated'     => [401, ClientException::class],
        'throttled'           => [429, ClientException::class],
        'server error'        => [500, ServerException::class],
        'service unavailable' => [503, ServerException::class],
    ]);
});

describe('mapUserToObject', function () {
    it('maps the serialised User model onto the Socialite user', function () {
        $user = invoke(provider(), 'mapUserToObject', userResponse());

        expect($user)->toBeInstanceOf(User::class)
            ->getId()->toBe(42)
            ->getNickname()->toBe('fabio')
            ->getName()->toBe('Fabio Bacigalupo')
            ->getEmail()->toBe('fabio@example.com')
            ->getAvatar()->toBe('https://www.podcast.de/user/42/avatar');
    });

    it('uses the podcast.de username as nickname', function () {
        $user = invoke(provider(), 'mapUserToObject', userResponse(['username' => 'podcastfan', 'nickname' => 'ignored']));

        expect($user->getNickname())->toBe('podcastfan');
    });

    it('composes the display name like the backend does', function (array $overrides, ?string $expected) {
        $user = invoke(provider(), 'mapUserToObject', userResponse($overrides));

        expect($user->getName())->toBe($expected);
    })->with([
        'first and last name'       => [[], 'Fabio Bacigalupo'],
        'first name only'           => [['last_name' => null], 'Fabio'],
        'last name only'            => [['first_name' => ''], 'Bacigalupo'],
        'surrounding whitespace'    => [['first_name' => '  Fabio', 'last_name' => null], 'Fabio'],
        'whitespace only'           => [['first_name' => '   ', 'last_name' => ' '], 'fabio'],
        'no name, username'         => [['first_name' => null, 'last_name' => null], 'fabio'],
        'non-string names'          => [['first_name' => 7, 'last_name' => ['x']], 'fabio'],
        'nothing at all'            => [['first_name' => null, 'last_name' => null, 'username' => null], null],
        'empty username'            => [['first_name' => null, 'last_name' => null, 'username' => ''], null],
        'non-string username'       => [['first_name' => null, 'last_name' => null, 'username' => 42], null],
        'hidden name column absent' => [['name' => 'Ignored Column'], 'Fabio Bacigalupo'],
    ]);

    it('ignores a name field of the response because the backend hides that column', function () {
        $user = invoke(provider(), 'mapUserToObject', userResponse(['first_name' => null, 'last_name' => null, 'name' => 'Column']));

        expect($user->getName())->toBe('fabio');
    });

    it('keeps all attributes as raw data', function () {
        $response = userResponse(['about' => 'Podcaster', 'gender' => 'm']);

        $user = invoke(provider(), 'mapUserToObject', $response);

        expect($user->getRaw())->toBe($response)
            ->and($user['about'])->toBe('Podcaster')
            ->and($user['city'])->toBe('Berlin');
    });

    it('maps missing attributes to null', function (string $attribute, string $getter) {
        $response = userResponse(['first_name' => null, 'last_name' => null]);
        unset($response[$attribute]);

        expect(invoke(provider(), 'mapUserToObject', $response)->{$getter}())->toBeNull();
    })->with([
        'id'       => ['id', 'getId'],
        'nickname' => ['username', 'getNickname'],
        'name'     => ['username', 'getName'],
        'email'    => ['email', 'getEmail'],
        'avatar'   => ['avatar_url', 'getAvatar'],
    ]);

    it('maps an account without profile picture to null', function () {
        $user = invoke(provider(), 'mapUserToObject', userResponse(['avatar_url' => null]));

        expect($user->getAvatar())->toBeNull();
    });

    it('survives unexpected payloads without crashing', function (array $payload) {
        $user = invoke(provider(), 'mapUserToObject', $payload);

        expect($user)
            ->getId()->toBeNull()
            ->getEmail()->toBeNull()
            ->getName()->toBeNull()
            ->getRaw()->toBe($payload);
    })->with([
        'empty'             => [[]],
        'json:api envelope' => [['data' => ['attributes' => ['id' => 42, 'email' => 'fabio@example.com']]]],
        'session envelope'  => [['user' => ['id' => 42]]],
        'error body'        => [['message' => 'Unauthenticated.']],
    ]);
});
