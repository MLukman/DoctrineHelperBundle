<?php

namespace MLukman\DoctrineHelperBundle\Tests;

use MLukman\DoctrineHelperBundle\Tests\App\BaseTestCase;
use MLukman\DoctrineHelperBundle\Tests\App\SampleRequestBody;
use MLukman\DoctrineHelperBundle\Tests\App\SampleRequestBodyTarget;

class RequestBodyTest extends BaseTestCase
{

    public function testPopulate(): void
    {
        $requestBody = new SampleRequestBody();
        $requestBody->name = 'Ahmad';
        $requestBody->age = 34.5;
        $requestBody->comment = 'This is test';
        $requestBody->nested = new SampleRequestBody();
        $requestBody->nested->name = 'Albab';

        $requestBodyTarget = new SampleRequestBodyTarget();
        $requestBody->populate($requestBodyTarget);

        print_r($requestBody);
        print_r($requestBodyTarget);

        $this->assertEquals($requestBody->name, $requestBodyTarget->name ?? null);
        $this->assertEquals($requestBody->age, $requestBodyTarget->age ?? null);
        $this->assertEquals($requestBody->comment, $requestBodyTarget->comment ?? null);
        $this->assertEquals($requestBody->nested->name, $requestBodyTarget->nested->name
                    ?? null);
    }
}