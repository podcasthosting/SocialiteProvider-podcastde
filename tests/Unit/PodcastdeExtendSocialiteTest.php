<?php

declare(strict_types=1);

use Podcasthosting\Podcastde\PodcastdeExtendSocialite;
use Podcasthosting\Podcastde\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

it('registers the provider under the "podcastde" driver name', function () {
    $event = Mockery::mock(SocialiteWasCalled::class);
    $event->shouldReceive('extendSocialite')->once()->with('podcastde', Provider::class);

    (new PodcastdeExtendSocialite())->handle($event);
});

it('exposes the identifier used by socialiteproviders/manager', function () {
    expect(Provider::IDENTIFIER)->toBe('PODCASTDE');
});
