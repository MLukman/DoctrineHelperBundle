<?php

namespace MLukman\DoctrineHelperBundle;

use MLukman\DoctrineHelperBundle\Query\DateFunction;
use MLukman\DoctrineHelperBundle\Query\MatchAgainst;
use MLukman\DoctrineHelperBundle\Type\FileType;
use MLukman\DoctrineHelperBundle\Type\ImageType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class DoctrineHelperSymfonyBundle extends AbstractBundle
{

    public function loadExtension(array $config,
                                  ContainerConfigurator $containerConfigurator,
                                  ContainerBuilder $containerBuilder): void
    {
        $containerConfigurator->import('../config/services.yaml');
    }

    public function prependExtension(ContainerConfigurator $container,
                                     ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('doctrine', [
            'dbal' => [
                'types' => [
                    'image' => ImageType::class,
                    'file' => FileType::class,
                ],
            ],
            'orm' => [
                'dql' => [
                    'string_functions' => [
                        'MATCH_AGAINST' => MatchAgainst::class,
                        'DATE' => DateFunction::class,
                    ],
                ],
            ],
        ]);
    }
}