<?php

namespace MLukman\DoctrineHelperBundle\DTO;

use ArrayAccess;
use LogicException;
use ReflectionClass;
use ReflectionProperty;
use ReflectionUnionType;
use Symfony\Component\PropertyAccess\PropertyAccess;

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
    public function populate(RequestBodyTargetInterface $target,
                             mixed $context = null): RequestBodyTargetInterface
    {
        // Prepare helpers
        $request_reflection = new ReflectionClass($this);
        $target_reflection = new ReflectionClass($target);
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        foreach ($request_reflection->getProperties() as $request_property) {
            /** @var ReflectionProperty $request_property */
            $property_name = $request_property->getName();

            // Ensure basic requirements to process property is fulfilled
            if (!$request_property->isInitialized($this) ||
                !$propertyAccessor->isReadable($this, $property_name) ||
                !$target_reflection->hasProperty($property_name) ||
                !$propertyAccessor->isWritable($target, $property_name)) {
                continue;
            }

            // Get both request & target properties' original values
            $request_property_value = $request_property->getValue($this);
            $target_property_value = $target_reflection->getProperty($property_name)->isInitialized($target)
                    ? $propertyAccessor->getValue($target, $property_name) : null;

            // Find out the type(s) of the target property
            $target_property_type = $target_reflection->getProperty($property_name)->getType();
            $target_property_types = (
                !$target_property_type ? [] : (
                $target_property_type instanceof ReflectionUnionType ?
                $target_property_type->getTypes() : [$target_property_type]
                ));
            // Filter only target property type(s) that implement RequestBodyTargetInterface
            $target_property_requestbodytarget_types = \array_filter($target_property_types, fn($type) =>
                ($type_name = $type->getName()) &&
                \class_exists($type_name) &&
                (new ReflectionClass($type_name))->implementsInterface(RequestBodyTargetInterface::class));

            if (!empty($target_property_requestbodytarget_types)) { // if the target property implements RequestBodyTargetInterface
                if (\is_scalar($request_property_value)) { // if request property is scalar
                    $type_index = 0;
                    do { // iterate until found RequestBodyTargetInterface handled by the overriden method
                        $target_property_value = $this->createRequestBodyTargetInterfaceFromScalarProperty($target, $property_name, $request_property_value, $target_property_requestbodytarget_types[$type_index]->getName(), $context);
                        $type_index++;
                    } while (\is_null($target_property_value) && $type_index < \count($target_property_requestbodytarget_types));
                } elseif ($request_property_value instanceof RequestBody &&
                    ($target_property_value instanceof RequestBodyTargetInterface
                    || \is_null($target_property_value))) { // if request property is RequestBody
                    $target_property_value = $this->populateChild($target, $property_name, $request_property_value, $target_property_value, null, $context);
                }
            } elseif (\is_iterable($request_property_value)) { // if request property is iterable and target property allow array access
                $target_property_type_name = $target_property_types[0]->getName();
                if (\is_array($target_property_value) || 'array' === $target_property_type_name) { // if target property is primitive array
                    foreach ($request_property_value as $request_key => $request_key_value) {
                        if ($request_key_value instanceof RequestBody) {
                            $target_key_value = \is_array($target_property_value)
                                    ? ($target_property_value[$request_key] ?? null)
                                    : null;
                            $request_key_value = $this->populateChild(
                                $target,
                                $property_name,
                                $request_key_value,
                                $target_key_value instanceof RequestBodyTargetInterface
                                    ? $target_key_value : null,
                                $request_key,
                                $context
                            );
                        }
                        $target_property_value[$request_key] = $request_key_value;
                    }
                } elseif ($target_property_value instanceof ArrayAccess ||
                    (\class_exists($target_property_type_name) &&
                    ($target_property_type_refl = new ReflectionClass($target_property_type_name))
                    && $target_property_type_refl->implementsInterface(ArrayAccess::class))) {

                    // Instantiate null/undefined $target_property_value assuming the constructor does not require arguments
                    if (!$target_property_value) {
                        $target_property_value = $target_property_type_refl->newInstance();
                    }
                    foreach ($request_property_value as $request_key => $request_key_value) {
                        if ($request_key_value instanceof RequestBody) {
                            $target_key_value = $target_property_value->offsetGet($request_key);
                            $request_key_value = $this->populateChild(
                                $target,
                                $property_name,
                                $request_key_value,
                                $target_key_value instanceof RequestBodyTargetInterface
                                    ? $target_key_value : null,
                                $request_key,
                                $context);
                        }
                        $target_property_value->offsetSet($request_key, $request_key_value);
                    }
                }
            } elseif (\is_string($request_property_value) && 'array' === $target_property_types[0]->getName()) { // source is string but target expects array
                $target_property_value = \explode(PHP_EOL, $request_property_value);
            } else { // otherwise, just set the target property with same value as request property
                $target_property_value = $request_property_value;
            }

            // Finally, set target property
            if (!\is_null($target_property_value) || $target_property_type->allowsNull()) {
                $propertyAccessor->setValue($target, $property_name, $target_property_value);
                $this->postSetPropertyValue($target, $property_name, $target_property_value, $context);
            }
        }

        return $target;
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
        RequestBodyTargetInterface $target, string $targetChildName,
        RequestBody $requestBodyChild,
        ?RequestBodyTargetInterface $targetChild = null, ?string $key = null,
        mixed $context = null): ?RequestBodyTargetInterface
    {
        if ($targetChild) {
            return $requestBodyChild->populate($targetChild, $context);
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
        RequestBodyTargetInterface $target, string $property_name,
        mixed $scalar_value, string $requestBodyTargetClass,
        mixed $context = null): ?RequestBodyTargetInterface
    {
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
        RequestBodyTargetInterface $target, string $property_name,
        mixed $target_property_value, mixed $context = null)
    {

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