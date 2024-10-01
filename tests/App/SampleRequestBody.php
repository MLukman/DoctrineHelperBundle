<?php

namespace MLukman\DoctrineHelperBundle\Tests\App;

use MLukman\DoctrineHelperBundle\DTO\RequestBody;
use MLukman\DoctrineHelperBundle\DTO\RequestBodyTargetInterface;
use MLukman\DoctrineHelperBundle\Service\DataStore;

class SampleRequestBody extends RequestBody
{
    public ?string $name;
    public ?float $age;
    public ?string $comment;
    public ?SampleRequestBody $nested;
    public ?string $fromScalar;
    public ?array $attributes;
    public ?string $stringToArray;
    public ?bool $date;

    /**
     * @var SampleRequestBody[] $children
     */
    public ?array $children;

    protected function populateChild(
        RequestBodyTargetInterface $target,
        string $targetChildName,
        RequestBody $requestBodyChild,
        ?RequestBodyTargetInterface $targetChild = null,
        ?string $key = null,
        mixed $context = null,
        ?DataStore $datastore = null
    ): ?RequestBodyTargetInterface {
        if (in_array($targetChildName, ['nested', 'children']) && !$targetChild) {
            return $requestBodyChild->populate(new SampleRequestBodyTarget(), $context);
        }
        return parent::populateChild($target, $targetChildName, $requestBodyChild, $targetChild, $key, $context);
    }

    protected function createRequestBodyTargetInterfaceFromScalarProperty(
        RequestBodyTargetInterface $target,
        string $property_name,
        mixed $scalar_value,
        string $requestBodyTargetClass,
        mixed $context = null,
        ?DataStore $datastore = null
    ): ?RequestBodyTargetInterface {
        if ($requestBodyTargetClass == SampleRequestBodyTarget::class) {
            $child = new SampleRequestBodyTarget();
            $child->name = strval($scalar_value);
            return $child;
        }
        return parent::createRequestBodyTargetInterfaceFromScalarProperty($target, $property_name, $scalar_value, $requestBodyTargetClass, $context);
    }
}
