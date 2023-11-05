<?php

namespace MLukman\DoctrineHelperBundle\DTO;

use ReflectionClass;
use RuntimeException;
use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;

/**
 * This class is the base class for objects that will be serialized into response bodies.
 * Extend this class by specifying typed properties.
 * For the purpose of populating the property values from corresponding entities, ensure
 * the property names have matching properties or getters in the entity classes.
 */
abstract class ResponseBody
{

    public static function createResponseFromSource(ResponseBodySourceInterface $source,
                                                    ?string $responseBodyClass = null,
                                                    array &$processedSources = array()): ?ResponseBody
    {
        static $propertyAccessor = null;
        if ($propertyAccessor === null) {
            $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()->disableExceptionOnInvalidPropertyPath()->getPropertyAccessor();
        }
        static $propertyInfoExtractor = null;
        if ($propertyInfoExtractor === null) {
            $phpDocExtractor = new PhpDocExtractor();
            $reflectionExtractor = new ReflectionExtractor();
            $propertyInfoExtractor = new PropertyInfoExtractor(
                listExtractors: [$reflectionExtractor],
                typeExtractors: [$phpDocExtractor, $reflectionExtractor],
                descriptionExtractors: [$phpDocExtractor],
                accessExtractors: [$reflectionExtractor],
                initializableExtractors: [$reflectionExtractor]
            );
        }

        // Sanity check whether or not this source is convertable
        $response = $responseBodyClass ? new $responseBodyClass : $source->createResponseBody();
        if (!$response) {
            return null;
        } elseif (!is_subclass_of($response, ResponseBody::class)) {
            throw new RuntimeException("ResponseBody conversion error: $responseBodyClass is not a subclass of ResponseBody");
        }

        // To prevent infinite recursion, we store into array all converted properties including those of nested objects
        $processedKey = sprintf("%s->%s", spl_object_hash($source), get_class($response));
        if (isset($processedSources[$processedKey])) {
            // if already converted, just return the converted ResponseBody object
            return $processedSources[$processedKey];
        }
        $processedSources[$processedKey] = &$response;

        // Now process all properties
        foreach ((new ReflectionClass($response))->getProperties() as $response_property) {
            $property_name = $response_property->getName();
            if (!$propertyAccessor->isWritable($response, $property_name) ||
                !$propertyAccessor->isReadable($source, $property_name)) {
                continue;
            }
            try {
                $source_property_value = $propertyAccessor->getValue($source, $property_name);
                $response_property_types = $propertyInfoExtractor->getTypes(get_class($response), $property_name);
                $response_property_value = $response->handleSourcePropertyValue(
                    $source_property_value,
                    $response_property_types ? $response_property_types[0] : null,
                    $processedSources);
                $propertyAccessor->setValue($response, $property_name, $response_property_value);
            } catch (InvalidArgumentException) {
                // exception for one property only skips that property
            }
        }
        return $response;
    }

    protected function handleSourcePropertyValue(mixed $source_property_value,
                                                 ?Type $response_property_type,
                                                 array $processedSources): mixed
    {
        if ($source_property_value && $response_property_type) {
            if ($response_property_type->isCollection()) {
                $array = [];
                $response_property_item_type = current($response_property_type->getCollectionValueTypes());
                foreach ($source_property_value as $key => $val) {
                    $array[$key] = $this->handleSourcePropertyValue(
                        $val,
                        $response_property_item_type ?: null,
                        $processedSources);
                }
                return $array;
            } elseif (($response_property_class = $response_property_type->getClassName())
                && is_subclass_of($response_property_class, ResponseBody::class)
                && $source_property_value instanceof ResponseBodySourceInterface) {
                return static::createResponseFromSource($source_property_value, $response_property_class, $processedSources);
            } elseif ($response_property_type->getBuiltinType() == 'string' && $source_property_value instanceof \Stringable) {
                return $source_property_value->__toString();
            }
        }
        return $source_property_value;
    }
}