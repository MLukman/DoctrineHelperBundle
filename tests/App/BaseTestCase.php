<?php

namespace MLukman\DoctrineHelperBundle\Tests\App;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class BaseTestCase extends KernelTestCase
{

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', false);
    }

    public function setUp(): void
    {
        static::bootKernel();
    }

    protected function service(string $className)
    {
        return self::$kernel->getContainer()->get($className);
    }
}