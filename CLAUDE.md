Tech-Stack & Standards

    PHP Version: >=8.3 (Nutze readonly classes, Enums, Constructor Promotion).

    Standard: PSR-12 (Coding Style).

    Composer: Paket podcasthosting/socialiteprovider-podcastde, Autoloading via src/ (Namespace: Podcasthosting\Podcastde\).

    Abhängigkeiten: socialiteproviders/manager ^4.4 (bringt laravel/socialite mit).

    Type Safety: Striktes Typing (declare(strict_types=1);) in jeder Datei – wird per Arch-Test erzwungen.

    Schwesterpaket: /Users/fabio/Herd/SocialiteProvider-podcaster (podcaster, app.podcaster.de) – gleiche Struktur, Tooling und Testaufbau. Änderungen an Tooling oder Testhelfern dort nachziehen.

Architektur

    Laravel-Socialite-Provider für den OAuth2-Login bei podcast.de (Laravel Passport), kein eigenständiges SDK.

    Provider (src/Provider.php): Erweitert SocialiteProviders\Manager\OAuth2\AbstractProvider. Überschreibt nur die Hooks von Socialite (getAuthUrl, getTokenUrl, getUserByToken, mapUserToObject, parseApprovedScopes, refreshToken). Keine eigenen HTTP-Clients – immer getHttpClient() nutzen, damit Tests den Client per setHttpClient() mocken können.

    Scope (src/Scope.php): String-Backed Enum mit den Scope-Namen. Neue Scopes hier ergänzen und optional eine fluente Methode (withXyz()) im Provider anbieten.

    PodcastdeExtendSocialite (src/PodcastdeExtendSocialite.php): Listener auf SocialiteWasCalled, registriert den Treiber "podcastde".

    Rückgabewerte folgen Socialite: SocialiteProviders\Manager\OAuth2\User bzw. Laravel\Socialite\Two\Token. Rohdaten der API stehen über getRaw() zur Verfügung.

Backend (www.podcast.de, lokal unter /Users/fabio/Herd/www.podcast.de)

    Autorisierung: https://www.podcast.de/oauth/authorize, Token: https://www.podcast.de/oauth/token (Passport 13).

    Nutzerdaten: GET https://www.podcast.de/api/oauth/me (App\Http\Controllers\Auth\UserController::oauth, Middleware auth:api). Liefert das serialisierte User-Model ohne Envelope: id, username, email, first_name, last_name, avatar_url (per setAttribute erzwungen) sowie alle nicht versteckten Spalten (city, country, url, about, gender, …). Die Spalte name ist $hidden – der Anzeigename wird im Provider wie im Backend-Accessor aus first_name + last_name gebildet, Fallback username. Kein nickname-Feld; nickname = username.

    Nicht verwenden: /api/user (nur update/destroy), /api/auth/me (Session-Login, Envelope {"user": …}).

    Scopes müssen exakt Passport::tokensCan() in app/Providers/AuthServiceProvider.php entsprechen: user-read-only (Default-Scope, Passport::defaultScopes) und system (interner Scope zur Nutzerverwaltung, nicht für Drittanbieter-Logins – deshalb nicht im Enum). Das alte read-only-user ist ungültig und bricht Passport mit invalid_scope ab.

    Passport trennt Scopes mit Leerzeichen ($scopeSeparator = ' ' – nicht Socialites Standard-Komma).

    Die gewährten Scopes stehen nicht in der Token-Antwort, sondern im "scopes"-Claim des JWT-Access-Tokens – daraus liest parseApprovedScopes() sie aus ($user->approvedScopes). Der Consent-Screen (resources/views/auth/oauth/authorize.blade.php) zeigt Scopes nur an, abwählbar sind sie nicht.

    PKCE (S256) ist immer aktiv ($usesPKCE = true).

Coding Guidelines

    Naming: Öffentliche Methoden fluent und sprechend, Rückgabe static.

    Docblocks: Nur wenn Typen nicht via PHP-Typehints abgebildet werden können (z.B. /** @return string[] */) oder wenn Backend-Verhalten erklärt werden muss.

    Robustheit: Antworten der API defensiv behandeln (fehlende Felder → null, unlesbare Tokens → leere Scope-Liste), statt mit TypeErrors abzubrechen.

    Änderungen am Verhalten immer im README dokumentieren.

Tests (Pest 3 + Orchestra Testbench)

    tests/Unit: Provider isoliert (Redirect, Scopes, PKCE, Token-Austausch, User-Mapping, Approved Scopes). Provider über den Helper provider() erzeugen.

    tests/Feature: Kompletter Login-Flow über Socialite::driver('podcastde') in einer Laravel-App (tests/TestCase.php). Der Listener wird in defineEnvironment() registriert, weil der Manager SocialiteWasCalled schon beim Booten feuert.

    tests/ArchTest.php: Architekturregeln (strict types, php- und security-Preset, Klassen-Verträge).

    Helper in tests/Pest.php: httpClient() (Guzzle-Mock mit Request-History), jsonResponse(), jwt(), tokenResponse(), userResponse() (Format von /api/oauth/me), queryOf(), invoke() (für protected Methoden), arraySession().

    Keine echten HTTP-Requests – immer httpClient() mit gemockten Antworten nutzen. Testdaten am realen Backend-Format ausrichten.

    Jede neue Funktion und jeder Bugfix braucht Tests; Datasets für Varianten und Randfälle nutzen.

Workflows (Befehle)

    Alles prüfen: composer check (Code Style, PHPStan, Tests – vor jeder Übergabe ausführen)

    Tests: composer test (bzw. ./vendor/bin/pest)

    Static Analysis: composer analyse (PHPStan Level max auf src/, Config: phpstan.neon.dist). Tests sind ausgenommen, weil Pest-Expectations dynamisch sind. Fehler an der Ursache beheben – keine @phpstan-ignore, keine Baseline, keine Casts nur zum Ruhigstellen.

    Code Style: composer format bzw. composer lint für Dry-Run (php-cs-fixer, Config: .php-cs-fixer.dist.php – PSR-12, strict types, ausgerichtete =>-Pfeile)

    Coverage: composer coverage (muss 100 % bleiben)

    Mutationstests: composer mutate (Score muss 100 % bleiben). Überlebende Mutanten durch gezielte Tests töten oder – wenn die Mutation fachlich gleichwertig ist – den Code vereinfachen (z.B. Default-Argumente weglassen).

    PCOV: In Herds PHP 8.4 installiert (~/Library/Application Support/Herd/config/php/84/extensions/pcov.so, eingetragen in der php.ini daneben). Selbst gebaut gegen die PHP-8.4.25-Header, weil Homebrews phpize PHP 8.5 ist. Nach einem PHP-Minor-Update in Herd ggf. neu bauen.

    Build: composer install
