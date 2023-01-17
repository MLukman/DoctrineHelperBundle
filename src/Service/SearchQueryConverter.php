<?php

namespace MLukman\DoctrineHelperBundle\Service;

use MLukman\DoctrineHelperBundle\DTO\SearchQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

final class SearchQueryConverter implements ParamConverterInterface
{

    public function apply(Request $request, ParamConverter $configuration): bool
    {
        $name = $configuration->getName();
        $request->attributes->set($name, new SearchQuery($name, $request->query->get($name, null)));
        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        $class = $configuration->getClass();
        if (!is_string($class)) {
            return false;
        }
        return $class == SearchQuery::class;
    }
}