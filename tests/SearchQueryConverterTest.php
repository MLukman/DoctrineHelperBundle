<?php

namespace MLukman\DoctrineHelperBundle\Tests;

use MLukman\DoctrineHelperBundle\DTO\SearchQuery;
use MLukman\DoctrineHelperBundle\Service\SearchQueryConverter;
use MLukman\DoctrineHelperBundle\Tests\App\BaseTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class SearchQueryConverterTest extends BaseTestCase
{

    public function testResolve(): void
    {
        $request = new Request(['search' => 'google']);
        $paramConfig = new ArgumentMetadata('search', SearchQuery::class, false, false, null);

        /** $var PaginatorConverter converter */
        $converter = $this->service(SearchQueryConverter::class);
        list($search) = $converter->resolve($request, $paramConfig);

        $this->assertNotEmpty($search);
        $this->assertEquals(SearchQuery::class, get_class($search));
        $this->assertEquals('google', $search->getKeyword());
    }
}