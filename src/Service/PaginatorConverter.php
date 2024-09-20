<?php

namespace MLukman\DoctrineHelperBundle\Service;

use MLukman\DoctrineHelperBundle\DTO\Paginator;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class PaginatorConverter implements ValueResolverInterface
{
    protected array $paginators = [];

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!($argumentType = $argument->getType()) ||
                !is_a($argumentType, Paginator::class, true)) {
            return [];
        }

        return [
            $this->createPaginator($argumentType, $request->query->all())
        ];
    }

    public function createPaginator(string $class, array $queries): Paginator
    {
        if (!isset($this->paginator[$class])) {
            $this->paginators[$class] = (new ReflectionClass($class))
                    ->newInstance(max(1, $queries['page'] ?? 1), $queries['limit'] ?? 0);
        }
        return $this->paginators[$class];
    }
}
