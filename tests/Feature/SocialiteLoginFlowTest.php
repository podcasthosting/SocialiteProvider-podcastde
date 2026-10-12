<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Podcasthosting\Podcastde\Provider;
use Podcasthosting\Podcastde\Scope;
use SocialiteProviders\Manager\OAuth2\User;

/**
 * Simulates the browser coming back from www.podcast.de: same session as
 * the redirect, with code and state in the query string.
 */
function returnFromPodcastde(array $query): void
{
    $session = app('request')->session();

    $callback = Request::create('/callback', 'GET', $query);
    $callback->setLaravelSession($session);

    app()->instance('request', $callback);
    Socialite::forgetDrivers();
}

beforeEach(function () {
    $request = Request::create('/login/podcastde');
    $request->setLaravelSession(app('session')->driver('array'));
    app()->instance('request', $request);
});

describe('driver resolution', function () {
    it('resolves the podcastde driver through socialiteproviders/manager', function () {
        expect(Socialite::driver('podcastde'))->toBeInstanceOf(Provider::class);
    });

    it('reads client credentials and redirect from config/services.php', function () {
        $query = queryOf(Socialite::driver('podcastde')->stateless()->redirect()->getTargetUrl());

        expect($query)
            ->client_id->toBe('client-id')
            ->redirect_uri->toBe('https://example.com/callback');
    });

    it('picks up changed config values', function () {
        config(['services.podcastde.client_id' => 'other-client', 'services.podcastde.redirect' => '/relative/callback']);
        Socialite::forgetDrivers();

        $query = queryOf(Socialite::driver('podcastde')->stateless()->redirect()->getTargetUrl());

        expect($query['client_id'])->toBe('other-client')
            ->and($query['redirect_uri'])->toBe(url('/relative/callback'));
    });
});

describe('complete login', function () {
    it('logs a user in end to end', function () {
        $history = [];
        $redirect = Socialite::driver('podcastde')->redirect()->getTargetUrl();
        $state = queryOf($redirect)['state'];
        $verifier = app('request')->session()->get('code_verifier');

        returnFromPodcastde(['code' => 'auth-code', 'state' => $state]);

        $user = Socialite::driver('podcastde')
            ->setHttpClient(httpClient([jsonResponse(tokenResponse()), jsonResponse(userResponse())], $history))
            ->user();

        parse_str((string) $history[0]['request']->getBody(), $tokenRequest);

        expect($user)->toBeInstanceOf(User::class)
            ->getId()->toBe(42)
            ->getNickname()->toBe('fabio')
            ->getEmail()->toBe('fabio@example.com')
            ->token->toBe(tokenResponse()['access_token'])
            ->refreshToken->toBe('refresh-token')
            ->expiresIn->toBe(31536000)
            ->approvedScopes->toBe(['user-read-only'])
            ->and($user->accessTokenResponseBody)->toBe(tokenResponse())
            ->and($history)->toHaveCount(2)
            ->and($tokenRequest)->toMatchArray([
                'grant_type'    => 'authorization_code',
                'code'          => 'auth-code',
                'code_verifier' => $verifier,
                'redirect_uri'  => 'https://example.com/callback',
            ])
            ->and($history[1]['request']->getHeaderLine('Authorization'))->toBe('Bearer ' . tokenResponse()['access_token']);
    });

    it('reports the granted scopes as approved', function () {
        $state = queryOf(Socialite::driver('podcastde')->redirect()->getTargetUrl())['state'];
        returnFromPodcastde(['code' => 'auth-code', 'state' => $state]);

        $user = Socialite::driver('podcastde')
            ->setHttpClient(httpClient([
                jsonResponse(tokenResponse(['user-read-only', 'system'])),
                jsonResponse(userResponse()),
            ]))
            ->user();

        expect($user->approvedScopes)->toBe(['user-read-only', 'system'])->toContain(Scope::UserReadOnly->value);
    });

    it('reports a scope as missing when the server did not grant it', function () {
        $state = queryOf(Socialite::driver('podcastde')->scopes(['system'])->redirect()->getTargetUrl())['state'];
        returnFromPodcastde(['code' => 'auth-code', 'state' => $state]);

        $user = Socialite::driver('podcastde')
            ->setHttpClient(httpClient([jsonResponse(tokenResponse(['user-read-only'])), jsonResponse(userResponse())]))
            ->user();

        expect($user->approvedScopes)->toBe(['user-read-only'])->not->toContain('system');
    });

    it('caches the user for the lifetime of the provider instance', function () {
        $history = [];
        returnFromPodcastde(['code' => 'auth-code']);
        $provider = Socialite::driver('podcastde')->stateless();
        $provider->setHttpClient(httpClient([jsonResponse(tokenResponse()), jsonResponse(userResponse())], $history));

        expect($provider->user())->toBe($provider->user())
            ->and($history)->toHaveCount(2);
    });
});

describe('state protection (CSRF)', function () {
    it('rejects a callback with a foreign state', function () {
        Socialite::driver('podcastde')->redirect();
        returnFromPodcastde(['code' => 'auth-code', 'state' => 'forged']);

        Socialite::driver('podcastde')->setHttpClient(httpClient([]))->user();
    })->throws(InvalidStateException::class);

    it('rejects a callback without any state', function () {
        Socialite::driver('podcastde')->redirect();
        returnFromPodcastde(['code' => 'auth-code']);

        Socialite::driver('podcastde')->setHttpClient(httpClient([]))->user();
    })->throws(InvalidStateException::class);

    it('rejects a callback when no login was started', function () {
        returnFromPodcastde(['code' => 'auth-code', 'state' => 'whatever']);

        Socialite::driver('podcastde')->setHttpClient(httpClient([]))->user();
    })->throws(InvalidStateException::class);

    it('does not accept the same state twice', function () {
        $state = queryOf(Socialite::driver('podcastde')->redirect()->getTargetUrl())['state'];
        returnFromPodcastde(['code' => 'auth-code', 'state' => $state]);
        Socialite::driver('podcastde')
            ->setHttpClient(httpClient([jsonResponse(tokenResponse()), jsonResponse(userResponse())]))
            ->user();

        returnFromPodcastde(['code' => 'auth-code', 'state' => $state]);
        Socialite::driver('podcastde')->setHttpClient(httpClient([]))->user();
    })->throws(InvalidStateException::class);

    it('skips the state check when used stateless', function () {
        returnFromPodcastde(['code' => 'auth-code']);

        $user = Socialite::driver('podcastde')
            ->stateless()
            ->setHttpClient(httpClient([jsonResponse(tokenResponse()), jsonResponse(userResponse())]))
            ->user();

        expect($user->getId())->toBe(42);
    });
});

describe('failures', function () {
    it('does not call the user api when the token exchange fails', function () {
        $history = [];
        returnFromPodcastde(['code' => 'expired']);

        try {
            Socialite::driver('podcastde')
                ->stateless()
                ->setHttpClient(httpClient([jsonResponse(['error' => 'invalid_grant'], 400)], $history))
                ->user();
        } finally {
            expect($history)->toHaveCount(1);
        }
    })->throws(ClientException::class);

    it('fails when the token lacks the scope required by the user api', function () {
        returnFromPodcastde(['code' => 'auth-code']);

        Socialite::driver('podcastde')
            ->stateless()
            ->setHttpClient(httpClient([
                jsonResponse(tokenResponse(['system'])),
                jsonResponse(['message' => 'Invalid scope(s) provided.'], 403),
            ]))
            ->user();
    })->throws(ClientException::class, 'Invalid scope(s) provided.');
});

describe('existing tokens', function () {
    it('loads a user from a stored access token', function () {
        $history = [];

        $user = Socialite::driver('podcastde')
            ->setHttpClient(httpClient([jsonResponse(userResponse())], $history))
            ->userFromToken('stored-token');

        expect($user)
            ->getId()->toBe(42)
            ->token->toBe('stored-token')
            ->and($history[0]['request']->getHeaderLine('Authorization'))->toBe('Bearer stored-token');
    });

    it('refreshes an access token and keeps track of the granted scopes', function () {
        $token = Socialite::driver('podcastde')
            ->setHttpClient(httpClient([jsonResponse(tokenResponse(['user-read-only', 'system'], ['refresh_token' => 'rotated']))]))
            ->refreshToken('refresh-token');

        expect($token)
            ->refreshToken->toBe('rotated')
            ->approvedScopes->toBe(['user-read-only', 'system']);
    });
});
