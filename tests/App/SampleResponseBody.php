<?php

namespace MLukman\DoctrineHelperBundle\Tests\App;

use MLukman\DoctrineHelperBundle\DTO\ResponseBody;

class SampleResponseBody extends ResponseBody
{
    public ?string $name;
    public ?int $age;
    public ?SimpleResponseBody $pair = null;

    /** @var SimpleResponseBody[] */
    public array $children = [];

}