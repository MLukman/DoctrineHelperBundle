<?php

namespace MLukman\DoctrineHelperBundle\Service;

use Exception;
use MLukman\DoctrineHelperBundle\DTO\RequestBody;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * This ValueResolver will parse the request body, either in JSON format or form post format, and populate the properties of
 * the class specified as the type specified in controller route methods with the fields from the JSON/form.
 */
final class RequestBodyConverter implements ValueResolverInterface
{

    public function __construct(private ObjectValidator $validator,
                                private RequestBodyConverterUtil $util)
    {

    }

    public function getUtil(): RequestBodyConverterUtil
    {
        return $this->util;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $processingDetails = [];

        $type = $argument->getType();
        $name = $argument->getName();

        if (!is_subclass_of($type, RequestBody::class, true)) {
            return [];
        }

        $obj = $this->doResolve($request, $type, $name, $processingDetails);
        $this->util->addParameterProcessing($type, $name, $processingDetails);

        return [$obj];
    }

    protected function doResolve(Request $request, string $type, string $name,
                                 array &$processings): mixed
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT'])) {
            $processings[] = [
                'phase' => 'request_method_check',
                'result' => 0,
                'error' => 'Request method is not POST or PUT',
            ];
            return null;
        }
        $processings[] = [
            'phase' => 'request_method_check',
            'result' => 1,
        ];

        try {
            $body = ($request->headers->get('Content-type') == 'application/json'
                    ? $request->getContent() : \json_encode($request->request->all()));
            if (!($body_array = \json_decode($body, true))) {
                $processings[] = [
                    'phase' => 'read_request',
                    'result' => 0,
                    'error' => 'Unable to decode JSON body',
                ];
                return null;
            }
            $processings[] = [
                'phase' => 'read_request',
                'result' => 1,
                'details' => $body_array,
            ];

            try {
                $obj = $this->util->parse($body_array, $type);
            } catch (Exception $e) {
                $processings[] = [
                    'phase' => 'parse_request',
                    'result' => 0,
                    'error' => 'RequestBody object failed validations',
                    'details' => $e->getTrace(),
                ];
                return null;
            }
            $processings[] = [
                'phase' => 'parse_request',
                'result' => 1,
                'details' => $obj,
            ];

            if (($errors = $this->validator->validate($obj, true))) {
                // not valid
                $this->util->addError($name, [
                    'type' => 'validation_errors',
                    'validation_errors' => $errors,
                ]);
                $processings[] = [
                    'phase' => 'validate_request',
                    'result' => 0,
                    'error' => 'RequestBody object failed validations',
                    'details' => $errors,
                ];
                return null;
            }
            $processings[] = [
                'phase' => 'validate_request',
                'result' => 1,
            ];

            // Special handling for file uploads
            $details = $this->util->handleFileUpload($request->files->all(), $obj);
            $processings[] = [
                'phase' => 'handle_file_uploads',
                'result' => 1,
                'details' => $details,
            ];

            // finally return the object
            return $obj;
        } catch (Exception $e) {
            $this->util->addError($name, [
                'type' => 'exception',
                'exception' => $e,
            ]);
        }
        return null;
    }
}