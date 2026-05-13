# Laravel Socialite Provider for podcast.de

OAuth2 driver for [podcast.de](https://www.podcast.de), built on top of
[SocialiteProviders/Manager](https://socialiteproviders.com/).

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

### PKCE (optional)

For public clients (SPA, mobile) you can enable PKCE (S256):

```php
Socialite::driver('podcastde')->enablePKCE()->redirect();
```
   
## Development

```bash
composer install
./vendor/bin/phpunit
./vendor/bin/phpstan analyse
./vendor/bin/php-cs-fixer fix
```

## License

MIT
