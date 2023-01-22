<?php

namespace MLukman\DoctrineHelperBundle\Tests;

use MLukman\DoctrineHelperBundle\DTO\RequestBody;
use MLukman\DoctrineHelperBundle\Service\RequestBodyConverter;
use MLukman\DoctrineHelperBundle\Tests\App\BaseTestCase;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;

class RequestBodyConverterTest extends BaseTestCase
{
    private RequestBodyConverter $converter;

    public function setUp(): void
    {
        parent::setUp();
        $this->converter = $this->service(RequestBodyConverter::class);
    }

    public function testParse(): void
    {
        $requestBodyClass = new class extends RequestBody {
            public ?string $name;
            public ?float $age;
            public ?string $comment;
        };

        $source = [
            'name' => 'Ahmad',
            'age' => 34.1,
            'comment' => '',
        ];

        $converted = $this->converter->parse($source, $requestBodyClass::class);
        $this->doAssertion($source, $converted);
    }

    public function testApply(): void
    {
        $requestBodyClass = new class extends RequestBody {
            public ?string $name;
            public ?float $age;
            public ?string $comment;
        };
        $source = [
            'name' => 'Ahmad',
            'age' => 34.1,
            'comment' => '',
        ];

        $request = new Request(request: $source);
        $request->setMethod('POST');
        $paramConfig = new ParamConverter();
        $paramConfig->setClass($requestBodyClass::class);
        $paramConfig->setName('submission');

        $this->converter->apply($request, $paramConfig);
        $converted = $request->attributes->get('submission');
        $this->doAssertion($source, $converted);
    }

    protected function doAssertion(array $source, $converted)
    {
        $this->assertNotEmpty($converted);
        foreach ($source as $key => $value) {
            if ($value === '') {
                $this->assertEquals(false, isset($converted->$key));
            } else {
                $this->assertObjectHasAttribute($key, $converted);
                $this->assertEquals($value, $converted->$key);
            }
        }
    }
}