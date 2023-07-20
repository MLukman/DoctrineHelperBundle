<?php

namespace MLukman\DoctrineHelperBundle\Tests;

use MLukman\DoctrineHelperBundle\DTO\Paginator;
use MLukman\DoctrineHelperBundle\Service\PaginatorConverter;
use MLukman\DoctrineHelperBundle\Tests\App\BaseTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class PaginatorConverterTest extends BaseTestCase
{

    public function testResolve(): void
    {
        $request = new Request(['page' => 2, 'limit' => 100]);
        $paramConfig = new ArgumentMetadata('paginator', Paginator::class, false, false, null);

        /** $var PaginatorConverter converter */
        $converter = $this->service(PaginatorConverter::class);
        list($paginator) = $converter->resolve($request, $paramConfig);

        $this->assertNotEmpty($paginator);
        $this->assertEquals(Paginator::class, get_class($paginator));
        $this->assertEquals(2, $paginator->getPage());
        $this->assertEquals(100, $paginator->getLimit());
    }
}