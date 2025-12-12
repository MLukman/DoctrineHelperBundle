<?php

namespace MLukman\DoctrineHelperBundle\Service;

use Closure;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Persistence\Proxy;
use MLukman\DoctrineHelperBundle\Type\ImageWrapper;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postLoad)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
final class FSImageTypeSubscriber
{
    protected array $filesToDelete = [];
    protected array $filesToStore = [];

    public function __construct(#[Autowire('%kernel.project_dir%')] private string $dir) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->flagFilesToStore($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        // if the update set null to previously contained value,
        // store the path to the old path to be deleted during postUpdate event
        $this->iterateFSImageProperties(
            $args->getObject(),
            function (string $name, ?ImageWrapper $file, string $directory) use ($args) {
                if (
                    $args->hasChangedField($name) && ($oldval = $args->getOldValue($name)) &&
                    (is_null($file) || $file->uuid() != $oldval->uuid())
                ) {
                    $filepath = $directory . $oldval->uuid();
                    $this->filesToDelete[$filepath] = $filepath;
                }
            }
        );
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        // store the new files
        $this->flagFilesToStore($args->getObject());
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        // delete files previously identified during preUpdate event
        foreach ($this->filesToDelete as $filename) {
            if (file_exists($filename)) {
                unlink($filename);
            }
        }
        $this->filesToDelete = [];

        // store files previously flagged
        foreach ($this->filesToStore as $filepath => $content) {
            // Save as .new file before renaming to the correct filename.
            // This trick is necessary if the $content is a resource that comes 
            // from fopen of  the same filename as the one we are writing to.
            file_put_contents($filepath . '.new', $content);
            if (is_resource($content)) {
                fclose($content);
            }
            rename($filepath . '.new', $filepath);
        }
        $this->filesToStore = [];
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $this->iterateFSImageProperties(
            $args->getObject(),
            function (string $name, ?ImageWrapper $file, string $directory) {
                if ($file && file_exists($filepath = $directory . $file->uuid())) {
                    $file->setSource(function () use ($filepath) {
                        $stream = fopen('php://temp', "w+b");
                        fwrite($stream, file_get_contents($filepath));
                        rewind($stream);
                        return $stream;
                    });
                }
            }
        );
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->iterateFSImageProperties(
            $args->getObject(),
            function (string $name, ?ImageWrapper $file, string $directory) {
                if ($file && file_exists($filepath = $directory . $file->uuid())) {
                    $this->filesToDelete[$filepath] = $filepath;
                }
            }
        );
    }

    protected function flagFilesToStore(object $entity)
    {
        $this->iterateFSImageProperties(
            $entity,
            function (string $name, ?ImageWrapper $file, string $directory) {
                if ($file && $file->mightBeModified()) {
                    $this->filesToStore[$directory . $file->uuid()] = $file->get();
                }
            }
        );
    }

    protected function iterateFSImageProperties(object $entity, Closure $callback)
    {
        $refl = new ReflectionClass($entity);
        if ($entity instanceof Proxy) {
            $refl = $refl->getParentClass();
        }
        foreach ($refl->getProperties() as $property) {
            /* @var $property ReflectionProperty */
            foreach ($property->getAttributes(ORM\Column::class) as $column) {
                /* @var $column ReflectionAttribute */
                if (($column->getArguments()['type'] ?? null) != 'fsimage') {
                    continue;
                }
                $directory = $this->dir . '/var/fsimages/' . str_replace('\\', '/', $refl->getName()) . '/' . $property->getName() . '/';
                if (!is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }
                call_user_func($callback, $property->getName(), $property->getValue($entity), $directory);
            }
        }
    }
}
