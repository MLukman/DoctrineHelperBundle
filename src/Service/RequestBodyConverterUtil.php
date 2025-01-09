<?php

namespace MLukman\DoctrineHelperBundle\Service;

use Closure;
use Exception;
use MLukman\DoctrineHelperBundle\Type\FromUploadedFileInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
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

    public function __construct(protected ?SerializerInterface $serializer, private ObjectValidatorV2 $validator)
    {
        if (!$this->serializer) {
            $phpDocExtractor = new PhpDocExtractor();
            $typeExtractor = new PropertyInfoExtractor(
                typeExtractors:
                [
                    new ConstructorExtractor([$phpDocExtractor]),
                    $phpDocExtractor,
                    new ReflectionExtractor(),
                ]
            );
            $this->serializer = new Serializer(
                normalizers:
                [
                    new ObjectNormalizer(propertyTypeExtractor: $typeExtractor),
                    new DateTimeNormalizer(),
                    new ArrayDenormalizer(),
                ],
                encoders: ['json' => new JsonEncoder()]
            );
        }
    }

    public function resolve(Request $request, string $type, string $name): mixed
    {
        $processingDetails = [];
        if ($this->sanityCheck($request, $type, $name, $processingDetails)) {
            $obj = $this->doResolve($request, $type, $name, $processingDetails);
        }
        $this->processings[] = ['class' => $type, 'name' => $name, 'details' => $processingDetails];
        return $obj ?? null;
    }

    protected function sanityCheck(Request $request, string $type, string $name, array &$processings): bool
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT'])) {
            $processings[] = ['phase' => 'request_method_check', 'result' => 0, 'error' => 'Request method is not POST or PUT'];
            return false;
        }
        $processings[] = ['phase' => 'request_method_check', 'result' => 1];
        return true;
    }

    protected function doResolve(Request $request, string $type, string $name, array &$processings): mixed
    {
        try {
            $body_json = ($request->headers->get('Content-type') == 'application/json' ? $request->getContent() : \json_encode($request->request->all()));
            if (!($body_array = \json_decode($body_json, true))) {
                $processings[] = ['phase' => 'read_request', 'result' => 0, 'error' => 'Unable to decode JSON body'];
                return null;
            }
            $processings[] = ['phase' => 'read_request', 'result' => 1, 'details' => $body_array];

            try {
                $parsed = $this->parseValues($body_array, $type);
                $processings[] = ['phase' => 'parse_request', 'result' => 1, 'details' => $parsed];
            } catch (Exception $e) {
                $processings[] = ['phase' => 'parse_request', 'result' => 0, 'error' => 'RequestBody object failed validations', 'details' => $e->getTrace()];
                return null;
            }

            // Special handling for file uploads
            $details = $this->handleFileUpload($request->files->all(), $parsed);
            $processings[] = ['phase' => 'handle_file_uploads', 'result' => 1, 'details' => $details];

            // Validation
            $this->validator->reset();
            if (($errors = $this->validator->validate($parsed))) { // not valid because there are errors
                $this->addError($name, ['type' => 'validation_errors', 'validation_errors' => $errors]);
                $processings[] = ['phase' => 'validate_request', 'result' => 0, 'error' => 'RequestBody object failed validations', 'details' => $errors];
                return null;
            }
            $processings[] = ['phase' => 'validate_request', 'result' => 1];

            // finally return the object
            return $parsed;
        } catch (Exception $e) {
            $this->addError($name, ['type' => 'exception', 'exception' => $e]);
        }
        return null;
    }

    public function parseValues(array $values, string $className): mixed
    {
        // Filter out empty strings & empty objects
        $toDeserialize = static::array_filter_recursive($values, fn($val) => $val !== "");
        // Actual deserialization is handled by Symfony serializer
        return $this->serializer->deserialize(\json_encode($toDeserialize), $className, 'json', [
                ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
                ObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);
    }

    protected function handleFileUpload(array $files, &$target): array
    {
        $details = [];

        if (is_array($target)) { // if $target is array then recurse
            foreach ($target as $tkey => &$tval) {
                if (isset($files[$tkey])) {
                    $details[$tkey] = $this->handleFileUpload($files[$tkey], $tval);
                }
            }
            return $details;
        }

        // Property accessor to set target properties
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        // iterate over all uploaded files
        $targetReflection = new ReflectionClass($target);
        foreach ($files as $fkey => $fval) {
            if (!$targetReflection->hasProperty($fkey)) { // $target has no matching key so skip
                $details[$fkey] = "Skipped: target has no matching key";
                continue;
            }
            if (is_array($fval)) { // turn out the iterated item is a nested array, recurse
                $details[$fkey] = $this->handleFileUpload($fval, $target->{$fkey});
                continue;
            }
            if (!($fval instanceof UploadedFile)) { // for whatever reason it is not an uploaded file (super weird ...)
                $details[$fkey] = "Skipped: is not instance of UploadedFile class";
                continue;
            }

            // collect the type(s) supported by the target property
            $ftype = $targetReflection->getProperty($fkey)->getType();
            $ftypes = $ftype instanceof ReflectionUnionType ?
                array_map(fn(ReflectionNamedType $ntype) => $ntype->getName(), $ftype->getTypes()) :
                ($ftype instanceof ReflectionNamedType ? [$ftype->getName()] : []);

            // check & process whether any of the type(s) implements FromUploadedFileInterface
            foreach ($ftypes as $type) {
                $typeReflection = new ReflectionClass($type);
                if ($typeReflection->implementsInterface(FromUploadedFileInterface::class)) {
                    if (($fromUploadedFile = call_user_func([$type, 'fromUploadedFile'], $fval))) {
                        $propertyAccessor->setValue($target, $fkey, $fromUploadedFile);
                        $details[$fkey] = "File parsed as " . \get_class($fromUploadedFile);
                        continue 2;
                    } else {
                        $details[$fkey] = "File unable to be parsed as " . $type;
                    }
                }
            }

            if (in_array(UploadedFile::class, $ftypes)) { // if UploadedFile is supported
                $propertyAccessor->setValue($target, $fkey, $fval);
                $details[$fkey] = "File remains as " . UploadedFile::class;
                continue;
            }

            if (empty($ftypes) || in_array('string', $ftypes)) { // last resort, if string is accepted then parse as the file content
                $propertyAccessor->setValue($target, $fkey, $fval->getContent());
                $details[$fkey] = "File parsed as string";
            }
        }

        return $details;
    }

    public static function array_filter_recursive($input, Closure $callback = null)
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

    public function getParameterProcessings(): array
    {
        return $this->processings;
    }
}
