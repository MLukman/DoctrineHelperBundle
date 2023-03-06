<?php

namespace MLukman\DoctrineHelperBundle\Tests\App;

use MLukman\DoctrineHelperBundle\DTO\RequestBodyTargetInterface;

class SampleRequestBodyTarget implements RequestBodyTargetInterface
{
    public ?string $name;
    public ?float $age;
    public ?string $comment;
    public ?SampleRequestBodyTarget $nested;
    public ?array $attributes;

    /**
     * @var SampleRequestBodyTarget[] $children
     */
    public ?array $children;

}