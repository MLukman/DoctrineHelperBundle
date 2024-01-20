<?php

namespace MLukman\DoctrineHelperBundle\Tests;

use MLukman\DoctrineHelperBundle\Tests\App\TestCaseBase;
use MLukman\DoctrineHelperBundle\Tests\App\SampleResponseBody;
use MLukman\DoctrineHelperBundle\Tests\App\SampleResponseBodySource;

class ResponseBodyTest extends TestCaseBase
{

    public function testCreateResponseFromSource(): void
    {
        $source = new SampleResponseBodySource;
        $source->name = 'root';
        $source->age = 12;
        $source->pair = $source;
        $source->children[] = $source;

        $response = SampleResponseBody::createResponseFromSource($source);
        $this->assertEquals(SampleResponseBody::class, get_class($response));
        $this->assertEquals('root', $response->pair->name ?? null, 'response->pair->name');
        $this->assertEquals('root', $response->children[0]->name ?? null, 'response->children[0]->name');
        $responseArray = \json_decode(\json_encode($response), true);
        $this->assertEquals('root', $responseArray['children'][0]['name'], 'responseArray[children][0][name]');
    }
}