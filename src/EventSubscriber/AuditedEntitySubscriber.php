<?php

namespace MLukman\DoctrineHelperBundle\EventSubscriber;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use MLukman\DoctrineHelperBundle\Trait\AuditedEntityTrait;

class AuditedEntitySubscriber implements EventSubscriberInterface
{
    protected ?\Symfony\Component\Security\Core\Security $security = null;

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        if (($entity = $args->getObject()) && static::checkAuditedEntityTrait($entity)) {
            if (!empty($entity->getCreated())) {
                return;
            }
            $entity->setCreated(new DateTime());
            if ($this->security) {
                $entity->setCreatedBy($this->security->getUser());
            }
        }
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        if (($entity = $args->getObject()) && static::checkAuditedEntityTrait($entity)) {
            $entity->setUpdated(new DateTime());
            if ($this->security) {
                $entity->setUpdatedBy($this->security->getUser());
            }
        }
    }

    public function setSecurity(?\Symfony\Component\Security\Core\Security $security)
    {
        $this->security = $security;
    }

    protected static function checkAuditedEntityTrait($class): bool
    {
        $uses = false;
        do {
            $uses = in_array(AuditedEntityTrait::class, class_uses($class));
        } while (!$uses && ($class = get_parent_class($class)));
        return $uses;
    }
}