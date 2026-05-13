<?php

declare(strict_types=1);

namespace Podcasthosting\Podcastde\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Podcasthosting\Podcastde\PodcastdeExtendSocialite;
use Podcasthosting\Podcastde\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class PodcastdeExtendSocialiteTest extends TestCase
{
    #[Test]
    public function handle_extends_socialite_with_provider_class(): void
    {
        $socialiteWasCalled = $this->createMock(SocialiteWasCalled::class);
        $socialiteWasCalled
            ->expects($this->once())
            ->method('extendSocialite')
            ->with('podcastde', Provider::class);

        $listener = new PodcastdeExtendSocialite();
        $listener->handle($socialiteWasCalled);
    }
}
