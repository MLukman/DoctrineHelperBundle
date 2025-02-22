<?php

namespace MLukman\DoctrineHelperBundle\Tests;

use MLukman\DoctrineHelperBundle\Service\RequestBodyConverter;
use MLukman\DoctrineHelperBundle\Tests\App\TestCaseBase;
use MLukman\DoctrineHelperBundle\Tests\App\SampleRequestBody;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class RequestBodyConverterTest extends TestCaseBase
{
    private RequestBodyConverter $converter;
    private array $source = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->converter = $this->service(RequestBodyConverter::class);
        $json = <<<JSON
        {
            "name":"Ahmad",
            "age":34.1,
            "nested":{
               "name":"Albab",
               "age":10.1,
               "attributes":[
                  "red",
                  "circle"
               ],
               "children":[
                  {
                     "name":"Cucu"
                  }
               ]
            },
            "children":[
               {
                  "name":"Cucu",
                  "age":2.3
               }
            ]
         }
        JSON;
        $this->source = \json_decode($json, true);
    }

    public function testResolve(): void
    {
        $request = new Request(request: $this->source);
        $request->setMethod('POST');
        $paramConfig = new ArgumentMetadata('submission', SampleRequestBody::class, false, false, null);

        list($converted) = $this->converter->resolve($request, $paramConfig);
        $this->doAssertion($this->source, $converted);
    }

    public function testParse(): void
    {
        $converted = $this->converter->getUtil()->parseValues($this->source, SampleRequestBody::class);
        $this->doAssertion($this->source, $converted);
    }

    protected function doAssertion(array $source, $converted)
    {
        $this->assertNotEmpty($converted);
        foreach ($source as $key => $value) {
            if ($value === '') {
                $this->assertEquals(false, isset($converted->$key));
            } else {
                $this->assertEquals(true, isset($converted->$key));
                $converted_value = $converted->$key;
                if (is_array($value)) {
                    if (is_object($converted_value)) {
                        $this->doAssertion($value, $converted_value);
                    } else {
                        $this->assertIsArray($converted_value);
                        foreach ($value as $inner_key => $inner_value) {
                            $this->assertArrayHasKey($inner_key, $converted_value);
                            if (is_object($converted_value[$inner_key])) {
                                $this->doAssertion($inner_value, $converted_value[$inner_key]);
                            } else {
                                $this->assertEquals($inner_value, $converted_value[$inner_key]);
                            }
                        }
                    }
                } else {
                    $this->assertEquals($value, $converted_value);
                }
            }
        }
    }
}
