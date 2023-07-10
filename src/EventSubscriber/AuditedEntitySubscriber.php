<?php

namespace MLukman\DoctrineHelperBundle\EventSubscriber;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use MLukman\DoctrineHelperBundle\Interface\AuditedEntityInterface;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class AuditedEntitySubscriber
{
    protected ?\Symfony\Bundle\SecurityBundle\Security $security = null;

    public function prePersist(PrePersistEventArgs $args): void
    {
        if (!($entity = $args->getObject()) || !($entity instanceof AuditedEntityInterface)) {
            return;
        }
        if (empty($entity->getCreated())) {
            $entity->setCreated(new DateTime());
        }
        if ($this->security) {
            $entity->setCreatedBy($this->security->getUser());
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        if (!($entity = $args->getObject()) || !($entity instanceof AuditedEntityInterface)) {
            return;
        }
        $entity->setUpdated(new DateTime());
        if ($this->security) {
            $entity->setUpdatedBy($this->security->getUser());
        }
    }

    public function setSecurity(?\Symfony\Bundle\SecurityBundle\Security $security)
    {
        $this->security = $security;
    }
}