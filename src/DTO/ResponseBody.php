<?php

namespace MLukman\DoctrineHelperBundle\DTO;

use IteratorAggregate;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * This class is the base class for objects that will be serialized into response bodies.
 * Extend this class by specifying typed properties.
 * For the purpose of populating the property values from corresponding entities, ensure
 * the property names have matching properties or getters in the entity classes.
 */
abstract class ResponseBody
{

    static public function createResponseFromSource(ResponseBodySourceInterface $source,
                                                    array &$processed = [],
                                                    ?string $responseBodyClass = null): ?ResponseBody
    {
        $entity_class = get_class($source);
        if (in_array($entity_class, $processed)) {
            // special trick to prevent recursion by throwing exception to the calling code to skip populating the property
            throw new RuntimeException('Class has already been processed');
        }
        $processed[] = $entity_class;
        $response = $responseBodyClass ? new $responseBodyClass : $source->createResponseBody();
        if (!$response) {
            return null;
        }
        $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()->disableExceptionOnInvalidPropertyPath()->getPropertyAccessor();
        foreach ((new ReflectionClass($response))->getProperties() as $response_property) {
            $property_name = $response_property->getName();
            if (!$propertyAccessor->isWritable($response, $property_name) ||
                !$propertyAccessor->isReadable($source, $property_name)) {
                continue;
            }
            try {
                $source_property_value = $propertyAccessor->getValue($source, $property_name);
                $response_property_value = static::handleSourcePropertyValue($response_property, $source_property_value, $processed);
                $propertyAccessor->setValue($response, $property_name, $response_property_value);
            } catch (InvalidArgumentException | RuntimeException $ex) {
                // exception for one property only skips that property
            }
        }
        return $response;
    }

    static public function handleSourcePropertyValue(ReflectionProperty $response_property,
                                                     mixed $source_property_value,
                                                     array $processed): mixed
    {
        if ($source_property_value instanceof ResponseBodySourceInterface) {
            return static::createResponseFromSource($source_property_value, $processed);
        }
        $response_property_type = $response_property->getType()->getName();
        if ($source_property_value instanceof IteratorAggregate &&
            $response_property_type == 'array') {
            $array = [];
            foreach ($source_property_value as $key => $val) {
                $array[$key] = static::handleSourcePropertyValue($response_property, $val, $processed);
            }
            return $array;
        }
        return $source_property_value;
    }
}