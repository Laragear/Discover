<?php

namespace Tests;

use App\Events\AttributeClass;
use App\Events\Bar\Baz\Cougar;
use App\Events\Bar\Quz;
use App\Events\Bar\TestInterface;
use App\Events\Foo;
use ArrayIterator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laragear\Discover\DiscoverServiceProvider;
use Laragear\Discover\Facades\Discover;
use Mockery;
use Orchestra\Testbench\TestCase;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

use function tap;

use const DIRECTORY_SEPARATOR as DS;

class DiscoverTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [DiscoverServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [Discover::class];
    }

    protected function file(string $path): Mockery\MockInterface
    {
        return tap(Mockery::mock(SplFileInfo::class), static function (Mockery\MockInterface $mock) use ($path): void {
            $mock->expects('getRealPath')->atLeast()->andReturn(
                Str::of($path)->replace(['\\', '/'], DS)->toString()
            );
        });
    }

    protected function mockAllFiles(bool $recursive = false): Mockery\MockInterface
    {
        return $this->mock(Finder::class, function (Mockery\MockInterface $mock) use ($recursive): void {
            $mock->expects('files')->andReturnSelf();
            $mock->expects('name')->with('*.php')->andReturnSelf();
            $mock->expects('in')->with($this->app->path('Events'));
            $mock->expects('getIterator')->andReturn(new ArrayIterator([
                $this->file($this->app->path('Events/Foo.php')),
                $this->file($this->app->path('Events/Bar.php')),
                $this->file($this->app->path('Events/Bar/Quz.php')),
                $this->file($this->app->path('Events/Bar/Baz/Cougar.php')),
            ]));

            if (! $recursive) {
                $mock->expects('depth')->with(0);
            } else {
                $mock->expects('depth')->never();
            }
        });
    }

    public function test_defaults_to_app_namespace_and_path_with_zero_depth(): void
    {
        $this->mock(Finder::class, function (Mockery\MockInterface $mock): void {
            $mock->expects('files')->andReturnSelf();
            $mock->expects('name')->with('*.php')->andReturnSelf();
            $mock->expects('in')->with($this->app->path('Events'));
            $mock->expects('depth')->with(0);
            $mock->expects('getIterator')->andReturn(new ArrayIterator([
                $this->file($this->app->path('Events/Foo.php')),
                $this->file($this->app->path('Events/Bar.php')),
            ]));
        });

        $classes = Discover::in('Events')->classes();

        static::assertCount(2, $classes);
    }

    public function test_doesnt_adds_file_to_reflection_if_not_autoloaded(): void
    {
        $this->mock(Finder::class, function (Mockery\MockInterface $mock): void {
            $mock->expects('files')->andReturnSelf();
            $mock->expects('name')->with('*.php')->andReturnSelf();
            $mock->expects('in')->with($this->app->path('Events'));
            $mock->expects('depth')->with(0);
            $mock->expects('getIterator')->andReturn(new ArrayIterator([
                $this->file($this->app->path('INVALID.php')),
                $this->file($this->app->path('INVALID.php')),
            ]));
        });

        $classes = Discover::in('Events')->classes();

        static::assertEmpty($classes);
    }

    public function test_doesnt_adds_traits_abstracts_or_interfaces(): void
    {
        $this->mock(Finder::class, function (Mockery\MockInterface $mock): void {
            $mock->expects('files')->andReturnSelf();
            $mock->expects('name')->with('*.php')->andReturnSelf();
            $mock->expects('in')->with($this->app->path('Events'));
            $mock->expects('getIterator')->andReturn(new ArrayIterator([
                $this->file($this->app->path('Events/empty.php')),
                $this->file($this->app->path('Events/TestInterface.php')),
                $this->file($this->app->path('Events/Bar/TestInterface.php')),
                $this->file($this->app->path('Events/AbstractClass.php')),
            ]));
        });

        $classes = Discover::in('Events')->allClasses();

        static::assertEmpty($classes);
    }

    public function test_uses_recursively(): void
    {
        $this->mockAllFiles(true);

        $classes = Discover::in('Events')->recursively()->classes();

        static::assertCount(4, $classes);
    }

    public function test_uses_different_root_path_and_root_namespace(): void
    {
        $this->mock(Finder::class, function (Mockery\MockInterface $mock): void {
            $mock->expects('files')->andReturnSelf();
            $mock->expects('name')->with('*.php')->andReturnSelf();
            $mock->expects('in')->with($this->app->basePath('services'.DS.'Events'));
            $mock->expects('getIterator')->andReturn(new ArrayIterator([
                $this->file($this->app->basePath('Services/Events/Foo.php')),
                $this->file($this->app->basePath('Services/Events/Bar.php')),
                $this->file($this->app->basePath('Services/Events/Bar/Quz.php')),
                $this->file($this->app->basePath('Services/Events/Bar/Baz/Cougar.php')),
            ]));
        });

        $classes = Discover::at('services')->in('Events')->allClasses();

        static::assertCount(4, $classes);

        foreach ($classes as $class) {
            static::assertInstanceOf(ReflectionClass::class, $class);
        }
    }

    public function test_filters_by_instance_of_interface(): void
    {
        $this->mockAllFiles();

        $classes = Discover::in('Events')->instancesOf(TestInterface::class)->classes();

        static::assertCount(1, $classes);
        static::assertTrue($classes->has(Foo::class));
    }

    public function test_filters_by_instance_of_class(): void
    {
        $this->mockAllFiles();

        $classes = Discover::in('Events')->instancesOf(Quz::class)->classes();

        static::assertCount(2, $classes);
        static::assertTrue($classes->has(Cougar::class));
        static::assertTrue($classes->has(Quz::class));
    }

    public function test_filters_by_public_method(): void
    {
        $this->mockAllFiles();

        $classes = Discover::in('Events')->withMethod('handle')->classes();

        static::assertCount(1, $classes);
        static::assertTrue($classes->has(Foo::class));
        static::assertFalse($classes->has(Cougar::class));
    }

    public function test_filters_by_public_method_doesnt_take_hidden_methods(): void
    {
        $this->mockAllFiles();

        $classes = Discover::in('Events')->withMethod('protectedFunction', 'privateFunction')->classes();

        static::assertEmpty($classes);
    }

    public function test_filters_by_public_property(): void
    {
        $this->mockAllFiles();

        $classes = Discover::in('Events')->withProperty('publicString')->classes();

        static::assertCount(2, $classes);
        static::assertTrue($classes->has(Quz::class));
        static::assertTrue($classes->has(Cougar::class));
    }

    public function test_filters_by_public_property_doesnt_find_hidden_properties(): void
    {
        $this->mockAllFiles();

        $classes = Discover::in('Events')->withProperty('protectedString', 'privateString')->classes();

        static::assertEmpty($classes);
    }

    public function test_filters_by_all_traits(): void
    {
        $this->mockAllFiles();

        $classes = Discover::in('Events')->withTrait(\App\Events\Bar\Cougar::class)->classes();

        static::assertCount(2, $classes);
        static::assertTrue($classes->has(Quz::class));
        static::assertTrue($classes->has(Cougar::class));
    }

    public function test_filters_by_attribute_names(): void
    {
        $this->mock(Finder::class, function (Mockery\MockInterface $mock): void {
            $mock->expects('files')->andReturnSelf();
            $mock->expects('name')->with('*.php')->andReturnSelf();
            $mock->expects('in')->with($this->app->path('Events'));
            $mock->expects('depth')->with(0);
            $mock->expects('getIterator')->andReturn(new ArrayIterator([
                $this->file($this->app->path('Events/AttributeClass.php')),
                $this->file($this->app->path('Events/Bar.php')),
            ]));
        });

        $classes = Discover::in('Events')->withAttribute('MockClass')->classes();

        static::assertCount(1, $classes);
        static::assertTrue($classes->has(AttributeClass::class));
    }

    public function test_is_iterator(): void
    {
        $this->mockAllFiles();

        foreach (Discover::in('Events')->classes() as $class) {
            static::assertInstanceOf(ReflectionClass::class, $class);
        }
    }

    public function test_iterator_is_collection(): void
    {
        $this->mockAllFiles();

        static::assertInstanceOf(Collection::class, Discover::in('Events')->getIterator());
    }

    public function test_forwards_property_access_to_collection(): void
    {
        $this->mockAllFiles();

        static::assertInstanceOf(Collection::class, Discover::in('Events')->map->name);
    }

    public function test_forwards_call_to_collection(): void
    {
        $this->mockAllFiles();

        static::assertInstanceOf(Collection::class, Discover::in('Events')->map(fn ($c) => $c->name));
    }
}
