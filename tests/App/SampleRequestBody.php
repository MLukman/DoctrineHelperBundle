<?php

namespace MLukman\DoctrineHelperBundle\Tests\App;

use MLukman\DoctrineHelperBundle\DTO\RequestBody;
use MLukman\DoctrineHelperBundle\DTO\RequestBodyTargetInterface;

class SampleRequestBody extends RequestBody
{
    public ?string $name;
    public ?float $age;
    public ?string $comment;
    public ?SampleRequestBody $nested;
    public ?array $attributes;

    /**
     * @var SampleRequestBody[] $children
     */
    public ?array $children;

    protected function populateChild(RequestBodyTargetInterface $target,
                                     $targetChildName,
                                     RequestBody $requestBodyChild,
                                     ?RequestBodyTargetInterface $targetChild = null,
                                     $key = null, $context = null): ?RequestBodyTargetInterface
    {
        if ($targetChildName == 'nested' && !$targetChild) {
            return $requestBodyChild->populate(new SampleRequestBodyTarget(), $context);
        }
        return parent::populateChild($target, $targetChildName, $requestBodyChild, $targetChild, $key, $context);
    }
}