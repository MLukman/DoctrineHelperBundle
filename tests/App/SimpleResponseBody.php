<?php

namespace MLukman\DoctrineHelperBundle\Tests\App;

use MLukman\DoctrineHelperBundle\DTO\ResponseBody;

class SimpleResponseBody extends ResponseBody
{
    public ?string $name;
    public ?int $age;

}