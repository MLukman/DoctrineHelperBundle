<?php

namespace MLukman\DoctrineHelperBundle\Service;

use MLukman\DoctrineHelperBundle\DTO\PreDefinedQueries;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class PreDefinedQueriesConverter implements ValueResolverInterface
{
    protected array $pdqs = [];

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!($argumentType = $argument->getType()) ||
                !is_a($argumentType, PreDefinedQueries::class, true)) {
            return [];
        }

        $name = $argument->getName();
        if (!isset($this->pdqs[$name])) {
            $this->pdqs[$name] = new PreDefinedQueries($name, $request->getRequestUri(), $request->query->get($name, null));
        }

        return [$this->pdqs[$name]];
    }
}
