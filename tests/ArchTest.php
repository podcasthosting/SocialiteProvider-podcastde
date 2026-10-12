<?php

declare(strict_types=1);

use SocialiteProviders\Manager\OAuth2\AbstractProvider;

arch('every file declares strict types')
    ->expect('Podcasthosting\Podcastde')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->preset()->php();

arch('no security pitfalls')
    ->preset()->security();

arch('the provider is a socialiteproviders OAuth2 provider')
    ->expect('Podcasthosting\Podcastde\Provider')
    ->toExtend(AbstractProvider::class);

arch('scopes are a backed string enum')
    ->expect('Podcasthosting\Podcastde\Scope')
    ->toBeStringBackedEnum();

arch('the listener cannot be extended')
    ->expect('Podcasthosting\Podcastde\PodcastdeExtendSocialite')
    ->toBeFinal()
    ->toHaveMethod('handle');

arch('production code does not depend on tests')
    ->expect('Podcasthosting\Podcastde')
    ->not->toUse('Podcasthosting\Podcastde\Tests');
