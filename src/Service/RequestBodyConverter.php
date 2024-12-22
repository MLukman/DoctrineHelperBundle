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
    public function __construct(private RequestBodyConverterUtil $util)
    {

    }

    public function getUtil(): RequestBodyConverterUtil
    {
        return $this->util;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (is_subclass_of($type = $argument->getType(), RequestBody::class, true)) {
            return [$this->util->resolve($request, $type, $argument->getName())];
        }
        return [];
    }
}
