<?php

declare(strict_types=1);

namespace Podcasthosting\Podcastde;

enum Scope: string
{
    case UserReadOnly = 'user-read-only';

    /**
     * Titles and URLs of the podcasts the user has claimed on podcast.de
     * (GET https://www.podcast.de/api/oauth/podcasts). Optional: the user may
     * deselect it on the consent screen, so check $user->approvedScopes.
     */
    case PodcastsReadOnly = 'podcasts-read-only';
}
