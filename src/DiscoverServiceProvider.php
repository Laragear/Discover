<?php

namespace Laragear\Discover;

use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

use function lcfirst;

class DiscoverServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Discoverer::class, static function (ApplicationContract $app): Discoverer {
            return new Discoverer(
                $app->make(Finder::class),
                $app->basePath(),
                Str::after(
                    $app::class === Application::class ? $app->path() : lcfirst($app->getNamespace()), $app->basePath()
                ),
                $app->getNamespace()
            );
        });
    }
}
