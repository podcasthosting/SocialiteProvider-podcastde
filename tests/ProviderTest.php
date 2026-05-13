<?php

declare(strict_types=1);

namespace Podcasthosting\Podcastde\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Podcasthosting\Podcastde\Provider;
use SocialiteProviders\Manager\OAuth2\User;

class ProviderTest extends TestCase
{
    /**
     * @param  list<array<int|string, mixed>>  $history
     */
    private function createProvider(?Client $httpClient = null, array &$history = []): Provider
    {
        $request = Request::create('/', 'GET');
        $provider = new Provider($request, 'client-id', 'client-secret', 'https://example.com/callback');

        if ($httpClient !== null) {
            $provider->setHttpClient($httpClient);
        }

        return $provider;
    }

    /**
     * @param  list<Response>  $responses
     * @param  list<array<int|string, mixed>>  $history
     */
    private function createMockHttpClient(array $responses, array &$history = []): Client
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));

        return new Client(['handler' => $handlerStack]);
    }

    #[Test]
    public function identifier_is_podcastde(): void
    {
        $this->assertSame('PODCASTDE', Provider::IDENTIFIER);
    }

    #[Test]
    public function auth_url_points_to_podcastde_with_expected_query(): void
    {
        $provider = $this->createProvider();
        $provider->stateless();

        $redirectUrl = $provider->redirect()->getTargetUrl();

        $this->assertStringStartsWith('https://www.podcast.de/oauth/authorize', $redirectUrl);
        $this->assertStringContainsString('client_id=client-id', $redirectUrl);
        $this->assertStringContainsString('redirect_uri=' . urlencode('https://example.com/callback'), $redirectUrl);
        $this->assertStringContainsString('scope=read-only-user', $redirectUrl);
        $this->assertStringContainsString('response_type=code', $redirectUrl);
    }

    #[Test]
    public function get_user_by_token_returns_decoded_response(): void
    {
        $apiResponse = [
            'data' => [
                'attributes' => [
                    'id' => '123',
                    'nickname' => 'testuser',
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'avatar' => 'https://example.com/avatar.jpg',
                ],
            ],
        ];

        $httpClient = $this->createMockHttpClient([
            new Response(200, [], json_encode($apiResponse, JSON_THROW_ON_ERROR)),
        ]);

        $provider = $this->createProvider($httpClient);

        $reflection = new \ReflectionMethod($provider, 'getUserByToken');
        $result = $reflection->invoke($provider, 'test-token');

        $this->assertSame($apiResponse, $result);
    }

    #[Test]
    public function get_user_by_token_sends_bearer_and_accept_headers(): void
    {
        $history = [];
        $httpClient = $this->createMockHttpClient(
            [new Response(200, [], json_encode(['data' => ['attributes' => ['id' => '1']]], JSON_THROW_ON_ERROR))],
            $history,
        );

        $provider = $this->createProvider($httpClient, $history);

        $reflection = new \ReflectionMethod($provider, 'getUserByToken');
        $reflection->invoke($provider, 'the-token');

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('https://www.podcast.de/api/user', (string) $request->getUri());
        $this->assertSame('Bearer the-token', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    #[Test]
    public function get_user_by_token_throws_on_invalid_json(): void
    {
        $httpClient = $this->createMockHttpClient([
            new Response(200, [], '<html>not json</html>'),
        ]);

        $provider = $this->createProvider($httpClient);

        $this->expectException(\JsonException::class);

        $reflection = new \ReflectionMethod($provider, 'getUserByToken');
        $reflection->invoke($provider, 'test-token');
    }

    #[Test]
    public function map_user_to_object_returns_user_with_correct_fields(): void
    {
        $provider = $this->createProvider();

        $apiResponse = [
            'data' => [
                'attributes' => [
                    'id' => '42',
                    'nickname' => 'podcaster42',
                    'name' => 'Fabio B.',
                    'email' => 'fabio@example.com',
                    'avatar' => 'https://example.com/avatar.png',
                ],
            ],
        ];

        $reflection = new \ReflectionMethod($provider, 'mapUserToObject');
        $user = $reflection->invoke($provider, $apiResponse);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('42', $user->getId());
        $this->assertSame('podcaster42', $user->getNickname());
        $this->assertSame('Fabio B.', $user->getName());
        $this->assertSame('fabio@example.com', $user->getEmail());
        $this->assertSame('https://example.com/avatar.png', $user->getAvatar());
    }

    #[Test]
    public function map_user_to_object_sets_raw_attributes(): void
    {
        $provider = $this->createProvider();

        $attributes = [
            'id' => '1',
            'nickname' => 'nick',
            'name' => 'Name',
            'email' => 'e@mail.com',
            'avatar' => 'https://example.com/a.jpg',
        ];

        $apiResponse = ['data' => ['attributes' => $attributes]];

        $reflection = new \ReflectionMethod($provider, 'mapUserToObject');
        $user = $reflection->invoke($provider, $apiResponse);

        $this->assertSame($attributes, $user->getRaw());
    }

    #[Test]
    public function map_user_to_object_handles_missing_optional_fields(): void
    {
        $provider = $this->createProvider();

        $apiResponse = ['data' => ['attributes' => ['id' => '7']]];

        $reflection = new \ReflectionMethod($provider, 'mapUserToObject');
        $user = $reflection->invoke($provider, $apiResponse);

        $this->assertSame('7', $user->getId());
        $this->assertNull($user->getNickname());
        $this->assertNull($user->getName());
        $this->assertNull($user->getEmail());
        $this->assertNull($user->getAvatar());
    }

    #[Test]
    public function token_fields_inherit_authorization_code_grant_from_parent(): void
    {
        $provider = $this->createProvider();
        $provider->stateless();

        $reflection = new \ReflectionMethod($provider, 'getTokenFields');
        $fields = $reflection->invoke($provider, 'test-code');

        $this->assertSame('authorization_code', $fields['grant_type']);
        $this->assertSame('client-id', $fields['client_id']);
        $this->assertSame('client-secret', $fields['client_secret']);
        $this->assertSame('test-code', $fields['code']);
        $this->assertSame('https://example.com/callback', $fields['redirect_uri']);
    }
}
