<?php

namespace MLukman\DoctrineHelperBundle\Service;

use MLukman\DoctrineHelperBundle\DTO\PreDefinedQueries;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

final class PreDefinedQueriesConverter implements ParamConverterInterface
{

    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $name = $configuration->getName();
        $request->attributes->set($name, new PreDefinedQueries($name, $request->getRequestUri(), $request->query->get($name, null)));
        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        $class = $configuration->getClass();
        if (!is_string($class)) {
            return false;
        }
        return $class === PreDefinedQueries::class;
    }
}