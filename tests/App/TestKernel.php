<?php

namespace MLukman\DoctrineHelperBundle\Tests\App;

use MLukman\DoctrineHelperBundle\DoctrineHelperBundle;
use MLukman\DoctrineHelperBundle\Service\ObjectValidator;
use MLukman\DoctrineHelperBundle\Service\PaginatorConverter;
use MLukman\DoctrineHelperBundle\Service\PreDefinedQueriesConverter;
use MLukman\DoctrineHelperBundle\Service\RequestBodyConverter;
use MLukman\DoctrineHelperBundle\Service\ResponseFiltersConverter;
use MLukman\DoctrineHelperBundle\Service\SearchQueryConverter;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    protected array $services = [
        ObjectValidator::class,
        PaginatorConverter::class,
        PreDefinedQueriesConverter::class,
        RequestBodyConverter::class,
        ResponseFiltersConverter::class,
        SearchQueryConverter::class,
    ];

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineHelperBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $container, string $env = null) {
            foreach ($this->services as $service) {
                $container->setDefinition($service,
                    (new Definition($service))
                        ->setPublic(true)
                        ->setAutowired(true)
                );
            }
        });
    }
}