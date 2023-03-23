<?php

namespace MLukman\DoctrineHelperBundle\EventSubscriber;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use MLukman\DoctrineHelperBundle\Interface\AuditedEntityInterface;

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
        if (($entity = $args->getObject()) && static::checkAuditedEntity($entity)) {
            if (empty($entity->getCreated())) {
                $entity->setCreated(new DateTime());
            }
            if ($this->security) {
                $entity->setCreatedBy($this->security->getUser());
            }
        }
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        if (($entity = $args->getObject()) && static::checkAuditedEntity($entity)) {
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

    protected static function checkAuditedEntity($class): bool
    {
        return $class instanceof AuditedEntityInterface;
    }
}