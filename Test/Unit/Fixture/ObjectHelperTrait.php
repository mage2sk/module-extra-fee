<?php
declare(strict_types=1);

namespace Panth\ExtraFee\Test\Unit\Fixture;

/**
 * Builds framework models without their constructors and injects private
 * collaborators, so tests stay free of the object manager and the database.
 */
trait ObjectHelperTrait
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    protected function newWithoutConstructor(string $class, array $data = []): object
    {
        $object = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        if ($data !== [] && method_exists($object, 'setData')) {
            $object->setData($data);
        }
        return $object;
    }

    protected function inject(object $object, string $declaringClass, string $property, $value): void
    {
        $reflection = new \ReflectionProperty($declaringClass, $property);
        $reflection->setValue($object, $value);
    }

    /**
     * Collection stub that iterates over the given items.
     */
    protected function collectionOf(string $class, array $items, ?int $size = null): object
    {
        $collection = $this->createStub($class);
        $collection->method('getIterator')->willReturnCallback(static fn() => new \ArrayIterator($items));
        if (method_exists($class, 'getSize')) {
            $collection->method('getSize')->willReturn($size ?? count($items));
        }
        if (method_exists($class, 'getItems')) {
            $collection->method('getItems')->willReturn($items);
        }
        return $collection;
    }

    protected function factoryReturning(string $factoryClass, object $product): object
    {
        $factory = $this->createStub($factoryClass);
        $factory->method('create')->willReturn($product);
        return $factory;
    }
}
