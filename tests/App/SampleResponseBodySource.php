<?php

namespace MLukman\DoctrineHelperBundle\Tests\App;

use MLukman\DoctrineHelperBundle\DTO\ResponseBody;
use MLukman\DoctrineHelperBundle\DTO\ResponseBodySourceInterface;

class SampleResponseBodySource implements ResponseBodySourceInterface
{
    public ?string $name;
    public ?int $age;
    public ?SampleResponseBodySource $pair;
    public array $children = [];

    public function createResponseBody(): ?ResponseBody
    {
        return new SampleResponseBody();
    }
}