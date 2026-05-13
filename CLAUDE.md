Tech-Stack & Standards

    PHP Version: >=8.3 (Nutze readonly classes, Enums, Constructor Promotion).

    Standard: PSR-12 (Coding Style) & PSR-17/18 (HTTP Client/Factory).

    Composer: Autoloading via src/ (Namespace: YourVendor\YourSdk\).

    Type Safety: Striktes Typing (declare(strict_types=1);) in jeder Datei.

Architektur-Muster

    Client: Die zentrale Einstiegsklasse, die den HTTP-Stack verwaltet.

    Resources/Endpoints: Logische Gruppierung von API-Calls (z.B. $sdk->orders()->get()).

    Data Transfer Objects (DTOs): Jede API-Antwort muss in ein Objekt transformiert werden. Keine rohen Arrays an den User zurückgeben.

    Exceptions: Eigene Exception-Hierarchie (z.B. ApiException, ValidationException).

Coding Guidelines

    DTOs: Verwende readonly Klassen für API-Antworten.

    Naming: Methoden-Namen sollten der API-Aktion entsprechen (list(), create(), delete()).

    Docblocks: Nur wenn Typen nicht via PHP-Typehints abgebildet werden können (z.B. Generics in Arrays: /** @var Order[] */).

    Validation: Input-Validierung erfolgt idealerweise bereits im SDK, bevor der Request gesendet wird.

Workflows (Befehle)

    Tests: ./vendor/bin/phpunit

    Static Analysis: ./vendor/bin/phpstan analyse

    Code Style: ./vendor/bin/php-cs-fixer fix

    Build: composer install