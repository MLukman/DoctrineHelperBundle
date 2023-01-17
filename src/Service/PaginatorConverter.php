<?php

namespace MLukman\DoctrineHelperBundle\Service;

use MLukman\DoctrineHelperBundle\DTO\Paginator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

final class PaginatorConverter implements ParamConverterInterface
{

    public function apply(Request $request, ParamConverter $configuration): bool
    {
        list($page, $limit) = [max(1, $request->query->get('page', 1)), $request->query->get('limit', 0)];
        $request->attributes->set($configuration->getName(), new Paginator($page, $limit));
        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        $class = $configuration->getClass();
        if (!is_string($class)) {
            return false;
        }
        return $class == Paginator::class;
    }
}