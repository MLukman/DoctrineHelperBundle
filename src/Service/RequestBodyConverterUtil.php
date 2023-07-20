<?php

namespace MLukman\DoctrineHelperBundle\Service;

use MLukman\DoctrineHelperBundle\DTO\RequestBody;
use MLukman\DoctrineHelperBundle\Type\FromUploadedFileInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * This is the Util class that assists RequestBodyConverter.
 * In particular, this class collects statistics and errors during all conversions
 * to be used later by the web profiler and any custom application logic.
 * This class is is autowire-enabled.
 */
class RequestBodyConverterUtil
{
    protected $processings = [];
    protected $errors = [];

    public function __construct(private SerializerInterface $serializer)
    {

    }

    public function parse(array $request, string $className): mixed
    {
        // Filter out empty strings & empty objects
        $toDeserialize = static::array_filter_recursive($request, function ($val) {
                return !($val === "");
            });
        // Actual deserialization is handled by Symfony serializer
        return $this->serializer->deserialize(\json_encode($toDeserialize), $className, 'json', [
                ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
                ObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);
    }

    public function handleFileUpload(array $files, &$target): array
    {
        $details = [];

        // if $target is array then recurse
        if (is_array($target)) {
            foreach ($target as $tkey => &$tval) {
                if (!isset($files[$tkey])) {
                    continue;
                }
                $details[$tkey] = $this->handleFileUpload($files[$tkey], $tval);
            }
            return $details;
        }

        // Property accessor to set target properties
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        // iterate over all uploaded files
        $targetReflection = new ReflectionClass($target);
        foreach ($files as $fkey => $fval) {
            if (!$targetReflection->hasProperty($fkey)) {
                // $target has no matching key so skip
                $details[$fkey] = "Skipped: target has no matching key";
                continue;
            }
            if (is_array($fval)) {
                // turn out the iterated item is a nested array, recurse
                $details[$fkey] = $this->handleFileUpload($fval, $target->{$fkey});
                continue;
            }
            if (!($fval instanceof UploadedFile)) {
                // for whatever reason it is not an uploaded file (super weird ...)
                $details[$fkey] = "Skipped: is not instance of UploadedFile class";
                continue;
            }

            // collect the type(s) supported by the target property
            $ftype = $targetReflection->getProperty($fkey)->getType();
            $types = ($ftype instanceof ReflectionNamedType ? [$ftype->getName()]
                    : ($ftype instanceof ReflectionUnionType ? array_reduce($ftype->getTypes(),
                    function ($carry, ReflectionNamedType $type) {
                        return $carry + [$type->getName()];
                    }, []) : []));

            // check & process whether any of the type(s) implements FromUploadedFileInterface
            foreach ($types as $type) {
                $typeReflection = new ReflectionClass($type);
                if ($typeReflection->implementsInterface(FromUploadedFileInterface::class)) {
                    $fromUploadedFile = call_user_func([$type, 'fromUploadedFile'], $fval);
                    $propertyAccessor->setValue($target, $fkey, $fromUploadedFile);
                    $details[$fkey] = "File parsed as ".\get_class($fromUploadedFile);
                    continue 2;
                }
            }

            // if UploadedFile is supported
            if (in_array(UploadedFile::class, $types)) {
                $propertyAccessor->setValue($target, $fkey, $fval);
                $details[$fkey] = "File parsed as ".\get_class($fval);
                continue;
            }

            // last resort
            if (empty($types) || in_array('string', $types)) {
                $propertyAccessor->setValue($target, $fkey, $fval->getContent());
                $details[$fkey] = "File parsed as string";
            }
        }

        return $details;
    }

    static function array_filter_recursive($input, $callback = null)
    {
        foreach ($input as &$value) {
            if (is_array($value)) {
                $value = static::array_filter_recursive($value, $callback);
            }
        }
        return \array_filter($input, $callback);
    }

    public function addError(string $parameterName, array $errorDetails): void
    {
        $this->errors[$parameterName] = $errorDetails;
    }

    public function getErrorsFor(string $parameterName): array
    {
        return $this->errors[$parameterName] ?? [];
    }

    public function addParameterProcessing(string $class, string $name,
                                           array $details): void
    {
        $this->processings[] = [
            'class' => $class,
            'name' => $name,
            'details' => $details,
        ];
    }

    public function getParameterProcessings(): array
    {
        return $this->processings;
    }
}