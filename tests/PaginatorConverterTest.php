<?php

namespace MLukman\DoctrineHelperBundle\Tests;

use MLukman\DoctrineHelperBundle\DTO\Paginator;
use MLukman\DoctrineHelperBundle\Service\PaginatorConverter;
use MLukman\DoctrineHelperBundle\Tests\App\BaseTestCase;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;

class PaginatorConverterTest extends BaseTestCase
{

    public function testApply(): void
    {
        $request = new Request(['page' => 2, 'limit' => 100]);
        $paramConfig = new ParamConverter();
        $paramConfig->setName('paginator');

        /** $var PaginatorConverter converter */
        $converter = $this->service(PaginatorConverter::class);
        $converter->apply($request, $paramConfig);

        $this->assertNotEmpty($request->attributes->get('paginator'));
        $this->assertEquals(Paginator::class, get_class($request->attributes->get('paginator')));
        $this->assertEquals(2, $request->attributes->get('paginator')->getPage());
        $this->assertEquals(100, $request->attributes->get('paginator')->getLimit());
    }
}