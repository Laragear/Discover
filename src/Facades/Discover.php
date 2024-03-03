<?php

namespace Laragear\Discover\Facades;

use Illuminate\Support\Facades\Facade;
use Laragear\Discover\Discoverer;

/**
 * @method static \Laragear\Discover\Discoverer at(string $path, string $namespace = null)
 * @method static \Laragear\Discover\Discoverer in(string $namespace)
 * @method static \Laragear\Discover\Discoverer recursively()
 * @method static \Laragear\Discover\Discoverer instancesOf(string ...$classes)
 * @method static \Laragear\Discover\Discoverer withMethod(string ...$methods)
 * @method static \Laragear\Discover\Discoverer withProperty(string ...$properties)
 * @method static \Laragear\Discover\Discoverer withTrait(string ...$traits)
 * @method static \Laragear\Discover\Discoverer withAttribute(string ...$attributes)
 * @method static \Illuminate\Support\Collection<string, \ReflectionClass> classes()
 * @method static \Illuminate\Support\Collection<string, \ReflectionClass> allClasses()
 *
 * @see \Laragear\Discover\Discoverer
 */
class Discover extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return Discoverer::class;
    }
}
