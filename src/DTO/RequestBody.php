<?php

namespace MLukman\DoctrineHelperBundle\DTO;

use ArrayAccess;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping\Entity;
use LogicException;
use MLukman\DoctrineHelperBundle\Service\DataStore;
use ReflectionClass;
use ReflectionType;

/**
 * This class is the base class for objects that hold the bodies of incoming requests.
 * Extend this class by specifying typed properties.
 * For the purpose of populating the property values into corresponding entities, ensure
 * the property names have matching properties or setters in the entity classes.
 */
abstract class RequestBody
{
    /**
     * Populate properties of entity with same names as $this properties.
     *
     * @param RequestBodyTargetInterface $target
     * @param mixed $context
     * @return RequestBodyTargetInterface
     */
    public function populate(RequestBodyTargetInterface $target, mixed $context = null, ?DataStore $datastore = null): RequestBodyTargetInterface
    {
        $thisReflection = new ReflectionClass($this);
        if ($thisReflection->hasMethod('prepareTargetPropertyValue')) {
            throw new LogicException(\sprintf("Class %s has outdated customization. It needs to override 'convertProperty' method instead of the deprecated 'prepareTargetPropertyValue'.", \get_class($this)));
        }
        foreach ($thisReflection->getProperties() as $request_property) {
            $property_name = $request_property->getName();
            $requestProperty = new PropertyInfo($this, $property_name);
            if (!$requestProperty->isInitialized() || !$requestProperty->isReadable() ||
                !(new ReflectionClass($target))->hasProperty($property_name) ||
                (($targetProperty = new PropertyInfo($target, $property_name)) && !$targetProperty->isWritable())) {
                continue;
            }
            $converted = false;
            foreach ($targetProperty->getTypes() as $type_name => $target_property_type) {
                if (!$converted) {
                    $target_property_value = $targetProperty->getValue();
                    $converted = $this->convertProperty($target, $property_name, $requestProperty->getValue(), $type_name, $target_property_type, $target_property_value, $context, $datastore);
                }
            }
            if (!$converted) {
                $target_property_value = $requestProperty->getValue();
            }
            if (!\is_null($target_property_value) || $targetProperty->allowsNull()) {
                $targetProperty->setValue($target_property_value);
                $this->postSetPropertyValue($target, $property_name, $target_property_value, $context);
            }
        }

        return $target;
    }

    protected function convertProperty(
        RequestBodyTargetInterface $target,
        string $property_name,
        mixed $requestPropertyValue,
        string $targetPropertyType,
        ReflectionType $targetPropertyReflectionType,
        mixed &$targetPropertyValue,
        mixed $context = null,
        ?DataStore $datastore = null
    ): bool {
        if ($targetPropertyType == 'array' && \is_string($requestPropertyValue)) { // target expects array but source is string
            return !empty($targetPropertyValue = \array_map(fn($v) => trim($v), \explode("\n", $requestPropertyValue)));
        }
        $targetPropertyReflectionClass = \class_exists($targetPropertyType) ? new ReflectionClass($targetPropertyType) : null;
        if ($datastore && !empty($targetPropertyReflectionClass?->getAttributes(Entity::class)) && ($existing = $datastore->queryOne($targetPropertyType, $requestPropertyValue))) { // query entity if existing
            return !empty($targetPropertyValue = $existing);
        }
        if ($targetPropertyReflectionClass?->implementsInterface(RequestBodyTargetInterface::class)) { // target expects object that implements RequestBodyTargetInterface
            if (\is_scalar($requestPropertyValue)) { // if request property is scalar
                return !empty($targetPropertyValue = $this->createRequestBodyTargetInterfaceFromScalarProperty($target, $property_name, $requestPropertyValue, $targetPropertyType, $context, $datastore) ?: $targetPropertyValue);
            } elseif ($requestPropertyValue instanceof RequestBody && ($targetPropertyValue instanceof RequestBodyTargetInterface || \is_null($targetPropertyValue))) { // if request property is RequestBody
                return !empty($targetPropertyValue = $this->populateChild($target, $property_name, $requestPropertyValue, $targetPropertyValue, null, $context, $datastore));
            }
        }
        if (\is_iterable($requestPropertyValue) && ($targetPropertyType == 'array' || $targetPropertyValue instanceof ArrayAccess || $targetPropertyReflectionClass?->implementsInterface(ArrayAccess::class))) { // target expects array or is instance of ArrayAccess (e.g. Doctrine Collection)
            if (\is_null($targetPropertyValue) && $targetPropertyReflectionClass?->implementsInterface(ArrayAccess::class) && $targetPropertyReflectionClass?->isInstantiable()) {
                $targetPropertyValue = $targetPropertyReflectionClass?->newInstance();
            } else {
                $targetPropertyValue = [];
            }
            foreach ($requestPropertyValue as $request_propitem_key => $request_propitem_value) {
                if ($request_propitem_value instanceof RequestBody) {
                    $request_propitem_value = $this->populateChild($target, $property_name, $request_propitem_value, ($target_key_value = $targetPropertyValue[$request_propitem_key] ?? null) instanceof RequestBodyTargetInterface ? $target_key_value : null, $request_propitem_key, $context, $datastore);
                }
                if (!\is_null($request_propitem_value)) {
                    $targetPropertyValue[$request_propitem_key] = $request_propitem_value;
                } elseif (isset($targetPropertyValue[$request_propitem_key])) {
                    unset($targetPropertyValue[$request_propitem_key]);
                }
            }
            return true;
        }
        if (\is_bool($requestPropertyValue) && in_array($targetPropertyType, [DateTimeInterface::class, DateTime::class])) { // source is boolean but target expects datetime then true = current datetime, false = null
            if ($requestPropertyValue && !$targetPropertyValue) {
                return !empty($targetPropertyValue = new DateTime());
            }
            if (!$requestPropertyValue && $targetPropertyValue && $targetPropertyReflectionType->allowsNull()) {
                $targetPropertyValue = null;
                return true;
            }
        }
        return false;
    }

    /**
     * Handle the conversion of the given child property which is an instance of RequestBody subclass.
     * Request Body subclass should override this method to handle cases when $targetChild is null.
     *
     * @param RequestBodyTargetInterface $target the parent entity
     * @param string $targetChildName the property name
     * @param RequestBody $requestBodyChild the child property for $this ($this = RequestBody)
     * @param RequestBodyTargetInterface|null $targetChild the child property for $entity (can be null)
     * @param string|null $key the key for child property if it is an array/collection. Subclass may override the value.
     * @param mixed $context Additional context to pass among populateTarget(), populateTargetChild() and postSetTargetValue()
     * @return RequestBodyTargetInterface|null
     */
    protected function populateChild(
        RequestBodyTargetInterface $target,
        string $targetChildName,
        RequestBody $requestBodyChild,
        ?RequestBodyTargetInterface $targetChild = null,
        ?string $key = null,
        mixed $context = null,
        ?DataStore $datastore = null
    ): ?RequestBodyTargetInterface {
        if ($targetChild) {
            return $requestBodyChild->populate($targetChild, $context, $datastore);
        }
        throw new LogicException(\sprintf("Class %s cannot handle conversion of the property '%s' of class %s", \get_class($this), $targetChildName, \get_class($requestBodyChild)));
    }

    /**
     * Handle scenarios where target property expects a RequestBodyTargetInterface but the request property is scalar value,
     * for example to fetch RequestBodyTargetInterface from database based on the ID value in request property.
     *
     * @param RequestBodyTargetInterface $target
     * @param string $property_name
     * @param mixed $scalar_value
     * @param string $requestBodyTargetClass
     * @param mixed $context
     * @return RequestBodyTargetInterface|null
     */
    protected function createRequestBodyTargetInterfaceFromScalarProperty(
        RequestBodyTargetInterface $target,
        string $property_name,
        mixed $scalar_value,
        string $requestBodyTargetClass,
        mixed $context = null,
        ?DataStore $datastore = null
    ): ?RequestBodyTargetInterface {
        return null;
    }

    /**
     * If subclass needs to handle post-processing after a target property is set.
     *
     * @param RequestBodyTargetInterface $target
     * @param string $property_name
     * @param mixed $target_property_value
     * @param mixed $context Additional context to pass among populateTarget(), populateTargetChild() and postSetTargetValue()
     */
    protected function postSetPropertyValue(
        RequestBodyTargetInterface $target,
        string $property_name,
        mixed $target_property_value,
        mixed $context = null
    ) {
        
    }

    /**
     * Catch-all getter for missing/unknown properties that just returns null.
     *
     * @param string $name
     * @return null
     */
    public function __get($name)
    {
        return null;
    }
}
