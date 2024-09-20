<?php

namespace MLukman\DoctrineHelperBundle\Service;

use Closure;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping as ORM;
use MLukman\DoctrineHelperBundle\Type\FileWrapper;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postLoad)]
#[AsDoctrineListener(event: Events::postRemove)]
final class FSFileTypeSubscriber
{
    protected $postUpdateToDelete = [];

    public function __construct(#[Autowire('%kernel.project_dir%')] private string $dir)
    {

    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->storeFile($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        // if the update set null to previously contained value,
        // store the path to the old path to be deieted during postUpdate event
        $this->iterateFSFileProperties(
            $args->getObject(),
            function (string $name, ?FileWrapper $value, string $directory) use ($args) {
                if (!$value && ($oldval = $args->getOldValue($name))) {
                    $this->postUpdateToDelete[] = $directory . $oldval->getUuid();
                }
            }
        );
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        // delete files previously identified during preUpdate event
        foreach ($this->postUpdateToDelete as $filename) {
            if (file_exists($filename)) {
                unlink($filename);
            }
        }
        $this->postUpdateToDelete = [];
        // store the new files
        $this->storeFile($args->getObject());
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $this->iterateFSFileProperties(
            $args->getObject(),
            function (string $name, ?FileWrapper $value, string $directory) {
                if ($value && file_exists($filepath = $directory . $value->getUuid())) {
                    $value->setStreamCallback(fn () => fopen($filepath, "rb"));
                }
            }
        );
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->iterateFSFileProperties(
            $args->getObject(),
            function (string $name, ?FileWrapper $value, string $directory) {
                if ($value && file_exists($filepath = $directory . $value->getUuid())) {
                    unlink($filepath);
                }
            }
        );
    }

    protected function storeFile(object $entity)
    {
        $this->iterateFSFileProperties(
            $entity,
            function (string $name, ?FileWrapper $value, string $directory) {
                if ($value) {
                    file_put_contents($directory . $value->getUuid(), $value->getContent());
                }
            }
        );
    }

    protected function iterateFSFileProperties(object $entity, Closure $callback)
    {
        $refl = new ReflectionClass($entity);
        foreach ($refl->getProperties() as $property) {
            /* @var $property ReflectionProperty */
            foreach ($property->getAttributes(ORM\Column::class) as $column) {
                /* @var $column ReflectionAttribute */
                if (($column->getArguments()['type'] ?? null) == 'fsfile') {
                    $directory = $this->dir . '/var/fsfiles/' . str_replace('\\', '/', get_class($entity)) . '/' . $property->getName() . '/';
                    if (!is_dir($directory)) {
                        mkdir($directory, 0777, true);
                    }
                    call_user_func($callback, $property->getName(), $property->getValue($entity), $directory);
                }
            }
        }
    }
}
