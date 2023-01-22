<?php

namespace MLukman\DoctrineHelperBundle\Tests;

use MLukman\DoctrineHelperBundle\DTO\SearchQuery;
use MLukman\DoctrineHelperBundle\Service\SearchQueryConverter;
use MLukman\DoctrineHelperBundle\Tests\App\BaseTestCase;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;

class SearchQueryConverterTest extends BaseTestCase
{

    public function testApply(): void
    {
        $request = new Request(['search' => 'google']);
        $paramConfig = new ParamConverter();
        $paramConfig->setName('search');

        /** $var PaginatorConverter converter */
        $converter = $this->service(SearchQueryConverter::class);
        $converter->apply($request, $paramConfig);

        $this->assertNotEmpty($request->attributes->get('search'));
        $this->assertEquals(SearchQuery::class, get_class($request->attributes->get('search')));
        $this->assertEquals('google', $request->attributes->get('search')->getKeyword());
    }
}