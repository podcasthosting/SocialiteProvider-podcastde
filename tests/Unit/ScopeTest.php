<?php

declare(strict_types=1);

use Podcasthosting\Podcastde\Scope;

it('mirrors the scope names registered with Passport on www.podcast.de', function (Scope $scope, string $passportName) {
    expect($scope->value)->toBe($passportName);
})->with([
    'user'     => [Scope::UserReadOnly, 'user-read-only'],
    'podcasts' => [Scope::PodcastsReadOnly, 'podcasts-read-only'],
]);

it('only offers read-only scopes', function () {
    expect(array_column(Scope::cases(), 'value'))
        ->toHaveCount(2)
        ->each->toEndWith('-read-only');
});

it('resolves from the raw scope string', function () {
    expect(Scope::from('user-read-only'))->toBe(Scope::UserReadOnly)
        ->and(Scope::from('podcasts-read-only'))->toBe(Scope::PodcastsReadOnly)
        ->and(Scope::tryFrom('read-only-user'))->toBeNull();
});
