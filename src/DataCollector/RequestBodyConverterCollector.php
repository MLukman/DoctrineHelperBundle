<?php

namespace MLukman\DoctrineHelperBundle\DataCollector;

use MLukman\DoctrineHelperBundle\Service\RequestBodyConverterUtil;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RequestBodyConverterCollector extends AbstractDataCollector
{
    public function __construct(private RequestBodyConverterUtil $util)
    {
        
    }

    public function collect(Request $request, Response $response, Throwable $exception = null): void
    {
        $this->data = [
            'processings' => $this->util->getParameterProcessings(),
        ];
    }

    public function getProcessings(): array
    {
        return $this->data['processings'];
    }

    public function getName(): string
    {
        return 'doctrine_helper.request_body_conversions';
    }

    public static function getTemplate(): ?string
    {
        return '@DoctrineHelper/data_collector/template.html.twig';
    }
}
