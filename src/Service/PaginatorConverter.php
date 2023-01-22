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
        $request->attributes->set($configuration->getName(),
            $this->createPaginator($request->query->all()));
        return true;
    }

    public function createPaginator(array $queries): Paginator
    {
        list($page, $limit) = [max(1, $queries['page'] ?? 1), $queries['limit'] ?? 0];
        return new Paginator($page, $limit);
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