<?php

namespace MLukman\DoctrineHelperBundle\Tests;

use MLukman\DoctrineHelperBundle\Tests\App\TestCaseBase;
use MLukman\DoctrineHelperBundle\Tests\App\SampleRequestBody;
use MLukman\DoctrineHelperBundle\Tests\App\SampleRequestBodyTarget;

class RequestBodyTest extends TestCaseBase
{
    public function testPopulate(): void
    {
        $requestBody = new SampleRequestBody();
        $requestBody->name = 'Ahmad';
        $requestBody->age = 34.5;
        $requestBody->comment = 'This is test';
        $requestBody->nested = new SampleRequestBody();
        $requestBody->nested->name = 'Albab';
        $child = new SampleRequestBody();
        $child->name = 'Child';
        $child->fromScalar = 'Scalar';
        $requestBody->nested->children['child'] = $child;
        $requestBody->stringToArray = "Zero\nOne\nTwo\nThree";
        $requestBody->date = true;

        $requestBodyTarget = new SampleRequestBodyTarget();
        $requestBody->populate($requestBodyTarget);

        // assert primitive property types assignment
        $this->assertEquals($requestBody->name, $requestBodyTarget->name ?? null);
        $this->assertEquals($requestBody->age, $requestBodyTarget->age ?? null);
        $this->assertEquals($requestBody->comment, $requestBodyTarget->comment ?? null);
        // assert RequestBody::populateChild() working
        $this->assertEquals($requestBody->nested->name, $requestBodyTarget->nested->name ?? null);
        // assert RequestBody::populateChild() working for iterable property
        $this->assertEquals($requestBody->nested->children['child']->name, $requestBodyTarget->nested->children['child']->name ?? null);
        // assert RequestBody::createRequestBodyTargetInterfaceFromScalarProperty() working
        $this->assertEquals($requestBody->nested->children['child']->fromScalar, $requestBodyTarget->nested->children['child']->fromScalar->name ?? null);
        // assert multiline string -> array works
        $this->assertEquals("Three", $requestBodyTarget->stringToArray[3] ?? null);
        // assert bool -> DateTime works
        $this->assertInstanceOf(\DateTime::class, $requestBodyTarget->date ?? null);
    }
}
