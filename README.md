# Discover
[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/discover.svg)](https://packagist.org/packages/laragear/discover)
[![Latest stable test run](https://github.com/Laragear/Discover/workflows/Tests/badge.svg)](https://github.com/Laragear/Discover/actions)
[![Codecov coverage](https://codecov.io/gh/Laragear/Discover/branch/1.x/graph/badge.svg?token=LKnve3PkRl)](https://codecov.io/gh/Laragear/Discover)
[![Maintainability](https://api.codeclimate.com/v1/badges/8428413a7e0fd9feb57f/maintainability)](https://codeclimate.com/github/Laragear/Discover/maintainability)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_Discover&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_Discover)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/11.x/octane#introduction)

Discover and filter PHP Classes within a directory.

```php
use Laragear\Discover\Facades\Discover;

foreach (Discover::in('Rules') as $rule) {
    // ...
};
```

## Become a sponsor

[![](.github/assets/support.png)](https://github.com/sponsors/DarkGhostHunter)

Your support allows me to keep this package free, up-to-date and maintainable. Alternatively, you can **[spread the word!](http://twitter.com/share?text=I%20am%20using%20this%20cool%20PHP%20package&url=https://github.com%2FLaragear%2FDiscover&hashtags=PHP,Laravel)**

## Requisites

* PHP 8.1 or later
* Laravel 10 or later (optional)

# Installation

You can install the package via Composer:

```shell
composer require laragear/discover
```

## Usage

The `Discover` finds classes under a given project path. It contains fluent methods to filter the classes to discover, like method and property names, interfaces, traits, and attributes.

Let's make a simple example: list all classes that include the method `handle()`, inside the `App\Scoreboards` or deeper.

```php
use Laragear\Discover\Facades\Discover;

$classes = Discover::withMethod('handle')->in('Scoreboards')->allClasses();
```

The Discover class will automatically resolve your project path, and use your application path (`app`) and namespace (`App`) as the base to find the matching classes.

> [!IMPORTANT]
>
> The discovered classes must be [PSR-4 autoloaded](https://getcomposer.org/doc/04-schema.md#psr-4).

#### Application path and namespace

Most Laravel projects use the `app` and `App` as the application path and namespace, respectively. The library will use these, or any other set for your application.

To discover classes _elsewhere_, you will need to use the `from()` method to change both. If you don't set the namespace, it will be inferred from the path.

```php
use Laragear\Discover\Facades\Discover;

// Discover starting at the "my-project/score" using "Score" as the base namespace. 
$classes = Discover::at('score')->classes();

// Discover starting at the "my-project/match" using the "Matches" as the base namespace.
$classes = Discover::at('/match', 'Matches')->classes();
```

### Namespace

Use the `in()` method to set the base namespace to find classes. For example, if we want the classes in the `App\Scoreboards` namespace, we only need to set `Scoreboards`boards.

```php
use Laragear\Discover\Facades\Discover;

$classes = Discover::in('Scoreboards')->classes();
```

### Filters

You may use the included filters to find classes that are instances of another class, or contains a given member or attribute.

```php
use Laragear\Discover\Facades\Discover;
use App\Score\Contracts\Score;
use App\Score\Concerns\FiresEvents;
use App\Attributes\Subscribable;

// Filter all classes instances of at least one of the given classes/interfaces. 
Discover::in('Scoreboards')->instancesOf(ScoreContract::class)->classes();

// Filter all classes with at least one of the given public methods.
Discover::in('Scoreboards')->withMethod('show')->classes();

// Filter all classes with at least one of the given public properties.
Discover::in('Scoreboards')->withProperty('user')->classes();

// Filter all classes with at least one of the given traits.
Discover::in('Scoreboards')->withTrait(FiresEvents::class)->classes();

// Filter all classes with at least one of the given attributes.
Discover::in('Scoreboards')->withAttribute(Subscribable::class)->classes();
```

#### Additional filters

Since the classes are returned as `ReflectionClass` instances inside a [Collection](https://laravel.com/docs/11.x/collections), you can further filter the list. For example, filter all the class names that don't end with `Score`.

```php
use Laragear\Discover\Facades\Discover;
use Illuminate\Support\Str;

$classes = Discover::in('Scoreboards')
    ->classes()
    ->filter(fn ($class) => Str::endsWith($class->getName(), 'Score'));
```

### Retrieving classes

Once you're done building, you can retrieve the found classes as a Collection using `classes()`.

```php
use Laragear\Discover\Facades\Discover;

// Find all classes in `App\Scoreboards`.
$classes = Discover::in('Scoreboards')->classes();
```

The Discover class only looks for classes in the namespace set. To make the search recursive, you can use `allClasses()`, or set `recursive()` before the retrieval.

```php
use Laragear\Discover\Facades\Discover;

// Find all classes in `App\Scoreboards` and deeper.
$classes = Discover::in('Scoreboards')->recursively()->classes();

// Same as...
$classes = Discover::in('Scoreboards')->allClasses();
```

In any case, the `Discoverer` itself is iterable, so you can immediately use it inside a `foreach` loop.

```php
use Laragear\Discover\Facades\Discover;

foreach (Discover::recursive()->in('Scoreboards') as $class) {
    // ...
}
```

You may also pass down any method Collection method or High Order method directly, which may save you a few keystrokes.

```php
use Laragear\Discover\Facades\Discover;

$classes = Discover::in('Scoreboards')->map->isFinal();
```

## Outside Laravel

It's possible to use the Discoverer outside Laravel projects, as it only requires the `illuminate/support` library. You may need to set your project root path manually.

```php
use Laragear\Discover\Discoverer;

$discoverer = new Discoverer(__DIR__ . '/..');

foreach ($discoverer->at('software', 'Application')->in('Events') as $event) {
    // ...
}
```

## Laravel Octane compatibility

- There are no singletons using a stale application instance.
- There are no singletons using a stale config instance.
- There are no singletons using a stale request instance.
- There are no static properties written.

There should be no problems using this package with Laravel Octane.

## Security

If you discover any security related issues, please email darkghosthunter@gmail.com instead of using the issue tracker.

# Licence

This specific package version is licensed under the terms of the [MIT License](LICENSE.md), at time of publishing.

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2024 Laravel LLC.
