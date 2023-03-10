<?php

namespace MLukman\DoctrineHelperBundle\Service;

use Exception;
use MLukman\DoctrineHelperBundle\DTO\RequestBody;
use MLukman\DoctrineHelperBundle\Type\FromUploadedFileInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * This ParamConverter will parse the request body, either in JSON format or form post format, and populate the properties of
 * the class specified as the type specified in controller route methods with the fields from the JSON/form.
 */
final class RequestBodyConverter implements ParamConverterInterface
{
    protected $errors = [];

    public function __construct(private ObjectValidator $validator,
                                private SerializerInterface $serializer)
    {
        
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

    public function apply(Request $request, ParamConverter $configuration): bool
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT'])) {
            $request->attributes->set($configuration->getName(), null);
            return false;
        }
        try {
            $body = ($request->headers->get('Content-type') == 'application/json'
                    ? $request->getContent() : \json_encode($request->request->all()));
            if (!($body_array = \json_decode($body, true))) {
                throw new BadRequestHttpException('Invalid inputs');
            }

            $obj = $this->parse($body_array, $configuration->getClass());
            if (($this->errors[$configuration->getName()] = $this->validator->validate($obj, true))) {
                // not valid
                $request->attributes->set($configuration->getName(), null);
            } else {
                // Special handling for file uploads
                $this->handleFileUpload($request->files->all(), $obj);
                $request->attributes->set($configuration->getName(), $obj);
            }
        } catch (Exception $e) {
            $request->attributes->set($configuration->getName(), null);
            $this->errors[$configuration->getName()] = [
                'exception' => $e,
            ];
        }
        return true;
    }

    public function parse(array $request, string $className): mixed
    {
        // Filter out empty strings & empty objects
        $toDeserialize = static::array_filter_recursive($request, function ($val) {
                return !($val === "");
            });
        // Actual deserialization is handled by Symfony serializer
        /** @var RequestBody $obj */
        return $this->serializer->deserialize(\json_encode($toDeserialize), $className, 'json', [
                ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
                ObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);
    }

    protected function handleFileUpload(array $files, &$target)
    {
        // if $target is array then recurse
        if (is_array($target)) {
            foreach ($target as $tkey => &$tval) {
                if (!isset($files[$tkey])) {
                    continue;
                }
                $this->handleFileUpload($files[$tkey], $tval);
            }
            return;
        }

        // Property accessor to set target properties
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        // iterate over all uploaded files
        $targetReflection = new ReflectionClass($target);
        foreach ($files as $fkey => $fval) {
            if (!$targetReflection->hasProperty($fkey)) {
                // $target has no matching key so skip
                continue;
            }
            if (is_array($fval)) {
                // turn out the iterated item is a nested array, recurse
                $this->handleFileUpload($fval, $target->{$fkey});
                continue;
            }
            if (!($fval instanceof UploadedFile)) {
                // for whatever reason it is not an uploaded file (super weird ...)
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
                    continue 2;
                }
            }

            // if UploadedFile is supported
            if (in_array(UploadedFile::class, $types)) {
                $propertyAccessor->setValue($target, $fkey, $fval);
                continue;
            }

            // last resort
            if (empty($types) || in_array('string', $types)) {
                $propertyAccessor->setValue($target, $fkey, $fval->getContent());
            }
        }
    }

    public function supports(ParamConverter $configuration): bool
    {
        $class = $configuration->getClass();
        if (!is_string($class)) {
            return false;
        }

        return in_array(RequestBody::class, class_parents($class));
    }

    public function getErrorsFor(string $parameterName): array
    {
        return $this->errors[$parameterName] ?? [];
    }
}