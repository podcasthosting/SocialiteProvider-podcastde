<?php

declare(strict_types=1);

namespace Podcasthosting\Podcastde;

use SocialiteProviders\Manager\SocialiteWasCalled;

final class PodcastdeExtendSocialite
{
    public function handle(SocialiteWasCalled $socialiteWasCalled): void
    {
        $socialiteWasCalled->extendSocialite('podcastde', Provider::class);
    }
}
