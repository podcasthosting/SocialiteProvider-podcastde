# Laravel Socialite Provider for podcast.de

OAuth2 driver for logging in via [podcast.de](https://www.podcast.de), built on top of
[SocialiteProviders/Manager](https://socialiteproviders.com/).

## Requirements

- PHP `^8.3`
- `socialiteproviders/manager` `^4.4`

## Installation

```bash
composer require podcasthosting/socialiteprovider-podcastde
```

## Configuration

### 1. `.env`

```dotenv
PODCASTDE_CLIENT_ID=your-client-id
PODCASTDE_CLIENT_SECRET=your-client-secret
PODCASTDE_REDIRECT_URI=https://your-app.test/auth/podcastde/callback
```

### 2. `config/services.php`

```php
'podcastde' => [
    'client_id'     => env('PODCASTDE_CLIENT_ID'),
    'client_secret' => env('PODCASTDE_CLIENT_SECRET'),
    'redirect'      => env('PODCASTDE_REDIRECT_URI'),
],
```

### 3. Register the event listener

In `app/Providers/EventServiceProvider.php`:

```php
protected $listen = [
    \SocialiteProviders\Manager\SocialiteWasCalled::class => [
        \Podcasthosting\Podcastde\PodcastdeExtendSocialite::class,
    ],
];
```

## Usage

```php
use Laravel\Socialite\Facades\Socialite;

Route::get('/auth/podcastde', fn () => Socialite::driver('podcastde')->redirect());

Route::get('/auth/podcastde/callback', function () {
    $user = Socialite::driver('podcastde')->user();

    // $user->getId(), $user->getNickname(), $user->getName(),
    // $user->getEmail(), $user->getAvatar(), $user->token, ...
});
```

## Scopes

The provider requests the `user-read-only` scope by default. The scope names are available
as `Podcasthosting\Podcastde\Scope` enum.

The granted scopes are taken from the `scopes` claim of the access token (Laravel Passport
does not return them in the token response) and are available via `$user->approvedScopes`:

```php
use Podcasthosting\Podcastde\Scope;

$user = Socialite::driver('podcastde')->user();

if (in_array(Scope::UserReadOnly->value, $user->approvedScopes, true)) {
    // access token may read the user's profile
}
```

## User data

User details are fetched from `https://www.podcast.de/api/oauth/me`, which returns the serialised
user model. The Socialite user is mapped as follows:

| Socialite  | podcast.de                                                       |
|------------|------------------------------------------------------------------|
| `id`       | `id`                                                             |
| `nickname` | `username`                                                       |
| `name`     | `first_name last_name`, falling back to `username`               |
| `email`    | `email`                                                          |
| `avatar`   | `avatar_url` (`null` if the account has no profile picture)      |

Missing fields map to `null`. All attributes returned by the API are available via `getRaw()`.

## Refreshing tokens

```php
$token = Socialite::driver('podcastde')->refreshToken($user->refreshToken);

// $token->token, $token->refreshToken, $token->expiresIn, $token->approvedScopes
```

If podcast.de does not rotate the refresh token, the one you passed in is kept.

## PKCE

PKCE (RFC 7636) is **enabled by default** with `S256` as code challenge method. The provider stores
the `code_verifier` in the Laravel session during `redirect()` and submits it automatically in the
token exchange request. No additional configuration is required.

If you need to disable PKCE for any reason, extend the provider and set `protected $usesPKCE = false;`.

## Testing

The test suite uses [Pest](https://pestphp.com) and [Orchestra Testbench](https://github.com/orchestral/testbench):

```bash
composer test      # Pest
composer analyse   # PHPStan (level max)
composer lint      # php-cs-fixer dry run, `composer format` applies fixes
composer coverage  # code coverage (min. 100 %, needs PCOV or Xdebug)
composer mutate    # mutation testing (min. 100 % score)
composer check     # lint, analyse, test
```

- `tests/Unit` – the provider in isolation (authorization URL, scopes, PKCE, token exchange, user mapping, approved scopes)
- `tests/Feature` – the full login flow through `Socialite::driver('podcastde')` inside a Laravel application
- `tests/ArchTest.php` – architecture rules (strict types, no debug calls, class contracts)

## License

MIT
