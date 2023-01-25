<?php

namespace MLukman\DoctrineHelperBundle\Tests\App;

use MLukman\DoctrineHelperBundle\DTO\RequestBody;

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

}