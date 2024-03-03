<?php

namespace Laragear\Discover;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use IteratorAggregate;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Traversable;
use function array_intersect;
use function class_uses_recursive;
use function get_class_methods;
use function get_class_vars;
use function in_array;
use function trim;
use function ucfirst;
use const DIRECTORY_SEPARATOR;

/**
 * @mixin \Illuminate\Support\Collection<string, \ReflectionClass>
 *
 * @implements \IteratorAggregate<string, \ReflectionClass>
 */
class Discoverer implements IteratorAggregate
{
    /**
     * If the discovering should be recursive.
     */
    protected bool $recursive = false;

    /**
     * The directory from the root and path to look for classes.
     */
    protected string $directory = '';

    /**
     * Filters for the files discovered.
     *
     * @var array<\Closure(\ReflectionClass):bool>
     */
    protected array $filters = [];

    /**
     * Create a new Discoverer instance.
     */
    public function __construct(
        protected Finder $finder,
        protected string $root,
        protected string $path = '',
        protected string $namespace = '',
    ) {
        $this->root = Str::finish($this->root, DIRECTORY_SEPARATOR);
        $this->path = trim($this->path, DIRECTORY_SEPARATOR);
        $this->namespace = trim($this->namespace, '\\');
    }

    /**
     * Makes the discovering of classes recursive.
     *
     * @return $this
     */
    public function recursively(): static
    {
        $this->recursive = true;

        return $this;
    }

    /**
     * Sets the root path and the root namespace to find classes.
     *
     * @return $this
     */
    public function at(string $path, string $namespace = null): static
    {
        $this->path = trim($path, DIRECTORY_SEPARATOR);

        if (!$namespace) {
            $namespace = Str::of($path)->trim(DIRECTORY_SEPARATOR)->ucfirst()->replace(DIRECTORY_SEPARATOR, '\\');
        }

        $this->namespace = trim($namespace, '\\');

        return $this;
    }

    /**
     * Sets the namespace to look into.
     *
     * @return $this
     */
    public function in(string $namespace): static
    {
        $this->directory = Str::of($namespace)
            ->trim('\\/')
            ->replace('\\', DIRECTORY_SEPARATOR)
            ->ucfirst();

        return $this;
    }

    /**
     * Filter classes by instances of another class.
     *
     * @param  class-string  ...$classes
     * @return $this
     */
    public function instancesOf(string ...$classes): static
    {
        $this->filters['classes'] = static function (ReflectionClass $class) use ($classes): bool {
            $name = $class->getName();

            foreach ($classes as $comparable) {
                if ($class->isSubclassOf($comparable) || $comparable === $name) {
                    return true;
                }
            }

            return false;
        };

        return $this;
    }

    /**
     * Filters the classes found by those containing at least one of the given methods.
     */
    public function withMethod(string ...$methods): static
    {
        $this->filters['methods'] = function (ReflectionClass $class) use ($methods): bool {
            return !empty(array_intersect(get_class_methods($class->getName()), $methods));
        };

        return $this;
    }

    /**
     * Filters the classes found by those containing at least one of the given properties.
     */
    public function withProperty(string ...$properties): static
    {
        $this->filters['properties'] = static function (ReflectionClass $class) use ($properties): bool {
            return !empty(array_intersect(array_keys(get_class_vars($class->getName())), $properties));
        };

        return $this;
    }

    /**
     * Filters the classes found by those containing at least one of the given traits.
     */
    public function withTrait(string ...$traits): static
    {
        $this->filters['traits'] = static function (ReflectionClass $class) use ($traits): bool {
            return !empty(array_intersect($traits, class_uses_recursive($class->getName())));
        };

        return $this;
    }

    /**
     * Filters the classes found by those containing at least one of the class-level attributes.
     */
    public function withAttribute(string ...$attributes): static
    {
        $this->filters['attributes'] = static function (ReflectionClass $class) use ($attributes): bool {
            foreach ($class->getAttributes() as $attribute) {
                if (in_array($attribute->getName(), $attributes, true)) {
                    return true;
                }
            }

            return false;
        };

        return $this;
    }

    /**
     * Return all the found classes as a Collection of ReflectionClass instances.
     *
     * @return \Illuminate\Support\Collection<string, \ReflectionClass>
     */
    public function classes(): Collection
    {
        $classes = new Collection();

        foreach ($this->files()->getIterator() as $file) {
            // Try to get the class from the file. If we can't then it's not a class file.
            try {
                $reflection = new ReflectionClass($this->fqnFromFilePath($file));
            } catch (ReflectionException) {
                continue;
            }

            // If the class cannot be instantiated (like abstract, traits or interfaces), continue.
            if (!$reflection->isInstantiable()) {
                continue;
            }

            // Preemptively pass this class. Now it's left for the filters to keep allowing it.
            $passes = true;

            foreach ($this->filters as $callback) {
                // If the callback returns false, then didn't pass.
                if (!$passes = $callback($reflection)) {
                    break;
                }
            }

            if ($passes) {
                $classes->put($reflection->name, $reflection);
            }
        }

        return $classes;
    }

    /**
     * Return all the recursively found classes as a Collection of ReflectionClass instances.
     *
     * @return \Illuminate\Support\Collection<string, \ReflectionClass>
     */
    public function allClasses(): Collection
    {
        return $this->recursively()->classes();
    }

    /**
     * Builds the file Finder.
     */
    protected function files(): Finder
    {
        if (!$this->recursive) {
            $this->finder->depth(0);
        }

        return $this->finder->files()->name('*.php')->in($this->directory());
    }

    /**
     * Builds the final directory path.
     */
    protected function directory(): string
    {
        $base = $this->root.$this->path;

        if ($this->directory) {
            $base .= DIRECTORY_SEPARATOR.$this->directory;
        }

        return $base;
    }

    /**
     * Parses the fully qualified class name from the file name.
     */
    protected function fqnFromFilePath(SplFileInfo $file): string
    {
        return Str::of($file->getRealPath())
            ->after($this->root)
            ->trim(DIRECTORY_SEPARATOR)
            ->beforeLast('.php')
            ->ucfirst()
            ->replace(
                [DIRECTORY_SEPARATOR, ucfirst($this->path)],
                ['\\', $this->namespace],
            );
    }

    /**
     * Retrieve an external iterator.
     *
     * @return \Illuminate\Support\Collection<string, \ReflectionClass>
     */
    public function getIterator(): Traversable
    {
        return $this->classes();
    }

    /**
     * Handle dynamic calls to the retrieved classes.
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->classes()->{$name}(...$arguments);
    }
}
