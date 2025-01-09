<?php

namespace MLukman\DoctrineHelperBundle\DTO;

use ReflectionProperty;
use ReflectionUnionType;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class PropertyInfo
{
    protected mixed $value = null;
    protected bool $initialized;
    protected ReflectionProperty $reflection;
    protected array $types;
    protected static PropertyAccessor $accessor;

    public function __construct(protected object $object, protected string $name, ?ReflectionProperty $reflection = null)
    {
        if (!isset(static::$accessor)) {
            static::$accessor = PropertyAccess::createPropertyAccessor();
        }

        $this->reflection = $reflection ?: new ReflectionProperty($object, $name);

        if (!($type = $this->reflection->getType())) {
            $this->types = [];
        } elseif ($type instanceof ReflectionUnionType) {
            $this->types = array_combine(array_map(fn($t) => $t->getName(), $type->getTypes()), $type->getTypes());
        } else {
            $this->types = [$type->getName() => $type];
        }

        if (($this->initialized = $this->reflection->isInitialized($object))) {
            $this->value = $this->reflection->getValue($object);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function isReadable(): bool
    {
        return static::$accessor->isReadable($this->object, $this->name);
    }

    public function isWritable(): bool
    {
        return static::$accessor->isWritable($this->object, $this->name);
    }

    public function setValue(mixed $value): bool
    {
        if (!$this->isWritable()) {
            return false;
        }
        static::$accessor->setValue($this->object, $this->name, $value);
        return true;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function allowsNull(): bool
    {
        return $this->reflection->getType()->allowsNull();
    }
}
