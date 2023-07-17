<?php

namespace MLukman\DoctrineHelperBundle\Service;

use MLukman\DoctrineHelperBundle\DTO\SearchQuery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class SearchQueryConverter implements ValueResolverInterface
{

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();
        $name = $argument->getName();

        if (!is_a($type, SearchQuery::class, true)) {
            return [];
        }

        return [new SearchQuery($name, $request->query->get($name, null))];
    }
}