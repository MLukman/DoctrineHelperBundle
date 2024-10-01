<?php

namespace MLukman\DoctrineHelperBundle\DTO;

use ArrayAccess;
use DateTime;
use DateTimeInterface;
use LogicException;
use MLukman\DoctrineHelperBundle\Service\DataStore;
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
    public function populate(
        RequestBodyTargetInterface $target,
        mixed $context = null,
        ?DataStore $datastore = null
    ): RequestBodyTargetInterface {
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
            $target_property_value_orig = $target_reflection->getProperty($property_name)->isInitialized($target) ? $propertyAccessor->getValue($target, $property_name) : null;

            // Find out the type(s) of the target property
            $target_property_type = $target_reflection->getProperty($property_name)->getType();
            $target_property_types = (
                !$target_property_type ? [] : (
                    $target_property_type instanceof ReflectionUnionType ?
                    $target_property_type->getTypes() : [$target_property_type]
                )
            );

            $target_property_value = $this->prepareTargetPropertyValue(
                $target,
                $property_name,
                $request_property_value,
                $target_property_value_orig,
                array_combine(array_map(fn ($t) => $t->getName(), $target_property_types), $target_property_types),
                $context,
                $datastore
            );

            // Finally, set target property
            if (!\is_null($target_property_value) || $target_property_type->allowsNull()) {
                $propertyAccessor->setValue($target, $property_name, $target_property_value);
                $this->postSetPropertyValue($target, $property_name, $target_property_value, $context);
            }
        }

        return $target;
    }

    protected function prepareTargetPropertyValue(
        RequestBodyTargetInterface $target,
        string $property_name,
        mixed $request_property_value,
        mixed $target_property_value,
        array $target_property_types,
        mixed $context = null,
        ?DataStore $datastore = null
    ): mixed {
        foreach ($target_property_types as $type_name => $target_property_type) {
            /* @var $target_property_type_refl ReflectionClass */
            $target_property_type_refl = \class_exists($type_name) ? new ReflectionClass($type_name) : null;

            // target expects object that implements RequestBodyTargetInterface
            if ($target_property_type_refl && $target_property_type_refl->implementsInterface(RequestBodyTargetInterface::class)) {
                if (\is_scalar($request_property_value)) { // if request property is scalar
                    $existing = null;
                    if ($datastore && !empty($target_property_type_refl->getAttributes(\Doctrine\ORM\Mapping\Entity::class))) {
                        $existing = $datastore->queryOne($type_name, $request_property_value);
                    }
                    return $existing ?:
                            $this->createRequestBodyTargetInterfaceFromScalarProperty($target, $property_name, $request_property_value, $type_name, $context, $datastore) ?:
                            $target_property_value;
                } elseif (
                    $request_property_value instanceof RequestBody &&
                    ($target_property_value instanceof RequestBodyTargetInterface || \is_null($target_property_value))
                ) { // if request property is RequestBody
                    return $this->populateChild($target, $property_name, $request_property_value, $target_property_value, null, $context, $datastore);
                }
            }

            // target expects array but source is string
            if ($type_name == 'array' && \is_string($request_property_value)) {
                return \array_map(fn ($v) => trim($v), \explode("\n", $request_property_value));
            }

            // target expects array or is instance of ArrayAccess (e.g. Doctrine Collection)
            if (
                \is_iterable($request_property_value) &&
                ($type_name == 'array' || $target_property_value instanceof ArrayAccess || $target_property_type_refl->implementsInterface(ArrayAccess::class))
            ) {
                if (\is_null($target_property_value) && $target_property_type_refl->implementsInterface(ArrayAccess::class) && $target_property_type_refl->isInstantiable()) {
                    $target_property_value = $target_property_type_refl->newInstance();
                }
                foreach ($request_property_value as $request_propitem_key => $request_propitem_value) {
                    if ($request_propitem_value instanceof RequestBody) {
                        $target_key_value = $target_property_value[$request_propitem_key] ?? null;
                        $request_propitem_value = $this->populateChild(
                            $target,
                            $property_name,
                            $request_propitem_value,
                            $target_key_value instanceof RequestBodyTargetInterface ? $target_key_value : null,
                            $request_propitem_key,
                            $context,
                            $datastore
                        );
                    }
                    if (!\is_null($request_propitem_value)) {
                        $target_property_value[$request_propitem_key] = $request_propitem_value;
                    } elseif (isset($target_property_value[$request_propitem_key])) {
                        unset($target_property_value[$request_propitem_key]);
                    }
                }
                return $target_property_value ?? null;
            }

            // target expects DateTim
            if (\is_bool($request_property_value) && in_array($type_name, [DateTimeInterface::class, DateTime::class])) { // source is boolean but target expects datetime then true = current datetime, false = null
                if ($request_property_value && !$target_property_value) {
                    return new DateTime();
                }
                if (!$request_property_value && $target_property_value && $target_property_type->allowsNull()) {
                    return null;
                }
            }
        }

        // if nothing matches, just return the request property value
        return $request_property_value;
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
