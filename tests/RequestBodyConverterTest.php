<?php

namespace MLukman\DoctrineHelperBundle\Tests;

use MLukman\DoctrineHelperBundle\Service\RequestBodyConverter;
use MLukman\DoctrineHelperBundle\Tests\App\BaseTestCase;
use MLukman\DoctrineHelperBundle\Tests\App\SampleRequestBody;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;

class RequestBodyConverterTest extends BaseTestCase
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

    public function testApply(): void
    {
        $request = new Request(request: $this->source);
        $request->setMethod('POST');
        $paramConfig = new ParamConverter();
        $paramConfig->setClass(SampleRequestBody::class);
        $paramConfig->setName('submission');

        $this->converter->apply($request, $paramConfig);
        $converted = $request->attributes->get('submission');
        $this->doAssertion($this->source, $converted);
    }

    public function testParse(): void
    {
        $converted = $this->converter->parse($this->source, SampleRequestBody::class);
        print_r($converted);
        $this->doAssertion($this->source, $converted);
    }

    protected function doAssertion(array $source, $converted)
    {
        $this->assertNotEmpty($converted);
        foreach ($source as $key => $value) {
            if ($value === '') {
                $this->assertEquals(false, isset($converted->$key));
            } else {
                $this->assertObjectHasAttribute($key, $converted);
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