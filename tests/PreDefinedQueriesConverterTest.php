<?php

namespace MLukman\DoctrineHelperBundle\Tests;

use MLukman\DoctrineHelperBundle\DTO\PreDefinedQueries;
use MLukman\DoctrineHelperBundle\Service\PreDefinedQueriesConverter;
use MLukman\DoctrineHelperBundle\Tests\App\BaseTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class PreDefinedQueriesConverterTest extends BaseTestCase
{

    public function testResolve(): void
    {
        $request = new Request(['pdq' => 'all']);
        $paramConfig = new ArgumentMetadata('pdq', PreDefinedQueries::class, false, false, null);

        /** $var PreDefinedQueriesConverter converter */
        $converter = $this->service(PreDefinedQueriesConverter::class);
        list($pdq) = $converter->resolve($request, $paramConfig);
        /** $var PreDefinedQueries $pdq */
        $pdq->addQuery('all', fn($qb) => $qb);

        $this->assertNotEmpty($pdq);
        $this->assertEquals(PreDefinedQueries::class, get_class($pdq));
        $this->assertEquals('all', $pdq->getSelectedId());
    }
}