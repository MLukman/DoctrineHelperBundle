<?php

namespace MLukman\DoctrineHelperBundle\Tests;

use MLukman\DoctrineHelperBundle\DTO\RequestBody;
use MLukman\DoctrineHelperBundle\DTO\RequestBodyTargetInterface;
use MLukman\DoctrineHelperBundle\Tests\App\BaseTestCase;

class RequestBodyTest extends BaseTestCase
{

    public function testPopulate(): void
    {
        $requestBodyClass = new class extends RequestBody {
            public ?string $name;
            public ?float $age;
            public ?string $comment;
        };

        $requestBody = new $requestBodyClass;
        $requestBody->name = 'Ahmad';
        $requestBody->age = 34.5;
        $requestBody->comment = 'This is test';

        $requestBodyTargetClass = new class implements RequestBodyTargetInterface {
            public ?string $name = null;
            public ?float $age = null;
            public ?string $comment = null;
        };

        $requestBodyTarget = new $requestBodyTargetClass;
        $requestBody->populate($requestBodyTarget);

        $this->assertEquals($requestBody->name, $requestBody->name);
        $this->assertEquals($requestBody->age, $requestBody->age);
        $this->assertEquals($requestBody->comment, $requestBody->comment);
    }
}