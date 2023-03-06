<?php

namespace MLukman\DoctrineHelperBundle\DTO;

use ArrayAccess;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * This class is the base class for objects that hold the bodies of incoming requests.
 * Extend this class by specifying typed properties.
 * For the purpose of populating the property values into corresponding entities, ensure
 * the property names have matching properties or setters in the entity classes.
 */
abstract class RequestBody
{

    // populate properties of entity with same names as $this properties
    public function populate(
        RequestBodyTargetInterface $target, mixed $context = null): void
    {
        // prepare helpers
        $request_reflection = new ReflectionClass($this);
        $target_reflection = new ReflectionClass($target);
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        foreach ($request_reflection->getProperties() as $request_property) {
            /** @var ReflectionProperty $request_property */
            $property_name = $request_property->getName();

            // basic requirements to process each property
            if (!$request_property->isInitialized($this) ||
                !$propertyAccessor->isReadable($this, $property_name) ||
                !$propertyAccessor->isWritable($target, $property_name)) {
                continue;
            }

            $target_property_type = $target_reflection->hasProperty($property_name)
                    ? $target_reflection->getProperty($property_name)->getType()
                    : null;
            $request_property_value = $request_property->getValue($this);
            $target_property_value = $propertyAccessor->getValue($target, $property_name);
            if (!empty($target_property_type) &&
                class_exists($target_property_type->getName()) &&
                (new ReflectionClass($target_property_type->getName()))->implementsInterface(RequestBodyTargetInterface::class)) {
                // if the target property implements RequestBodyTargetInterface
                if (is_scalar($request_property_value)) {
                    $convertedChild = $this->createRequestBodyTargetInterfaceFromScalarProperty($target, $property_name, $request_property_value, $target_property_type->getName(), $context);
                    $propertyAccessor->setValue($target, $property_name, $convertedChild);
                    $this->postSetPropertyValue($target, $property_name, $convertedChild);
                } elseif ($request_property_value instanceof RequestBody) {
                    // if request property is RequestBody and target property is RequestBodyTargetInterface
                    $populatedChild = $this->populateChild($target, $property_name, $request_property_value, $target_property_value, null, $context);
                    $propertyAccessor->setValue($target, $property_name, $populatedChild);
                    $this->postSetPropertyValue($target, $property_name, $populatedChild);
                }
            } elseif (is_iterable($request_property_value) && $target_property_value instanceof ArrayAccess) {
                // if request property is iterable and target property allow array access
                foreach ($request_property_value as $key => $val) {
                    if ($val instanceof RequestBody) {
                        $val = $this->populateChild($target, $property_name, $val, null, $key, $context);
                    }
                    if ($val) {
                        $target_property_value->set($key, $val);
                    }
                }
                $this->postSetPropertyValue($target, $property_name, $target_property_value, $context);
            } else {
                // otherwise, just set the target property with same value as request property
                if (is_string($request_property_value)) {
                    $request_property_value = trim($request_property_value);
                }
                $propertyAccessor->setValue($target, $property_name, $request_property_value);
                $this->postSetPropertyValue($target, $property_name, $request_property_value, $context);
            }
        }
    }

    /**
     * Make $childProperty populate $entityChild. Used by populateTarget()
     * Subclass should override this method to handle cases when $targetChild is null
     * @param $target the parent entity
     * @param $targetChildName the property name
     * @param $requestBodyChild the child property for $this ($this = RequestBody)
     * @param $targetChild the child property for $entity (can be null)
     * @param $key the key for child property if it is an array/collection. Subclass may override the value.
     * @param $context Additional context to pass among populateTarget(), populateTargetChild() and postSetTargetValue()
     * @return the populated $entityChild
     */
    protected function populateChild(
        RequestBodyTargetInterface $target, string $targetChildName,
        RequestBody $requestBodyChild,
        ?RequestBodyTargetInterface $targetChild = null, &$key = null,
        mixed $context = null): ?RequestBodyTargetInterface
    {
        if ($targetChild) {
            $requestBodyChild->populate($targetChild, $context);
        }
        return $targetChild;
    }

    /**
     * If subclass needs to handle post-processing
     * @param RequestBodyTargetInterface $target
     * @param string $property_name
     * @param mixed $target_property_value
     * @param $context Additional context to pass among populateTarget(), populateTargetChild() and postSetTargetValue()
     */
    protected function postSetPropertyValue(
        RequestBodyTargetInterface $target, string $property_name,
        mixed $target_property_value, mixed $context = null)
    {
        
    }

    protected function createRequestBodyTargetInterfaceFromScalarProperty(
        RequestBodyTargetInterface $target, string $property_name,
        mixed $scalar_value, string $requestBodyTargetClass,
        mixed $context = null): ?RequestBodyTargetInterface
    {
        return null;
    }

    /**
     * Catch-all getter for missing/unknown properties that just returns null
     * @param string $name
     * @return null
     */
    public function __get($name)
    {
        return null;
    }
}