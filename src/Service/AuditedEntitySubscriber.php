<?php

namespace MLukman\DoctrineHelperBundle\Service;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use MLukman\DoctrineHelperBundle\Interface\AuditedEntityByInterface;
use MLukman\DoctrineHelperBundle\Interface\AuditedEntityDatesInterface;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class AuditedEntitySubscriber
{
    protected ?\Symfony\Bundle\SecurityBundle\Security $security = null;

    public function prePersist(PrePersistEventArgs $args): void
    {
        if (!($entity = $args->getObject())) {
            return;
        }
        if ($entity instanceof AuditedEntityDatesInterface && empty($entity->getCreated())) {
            $entity->setCreated(new DateTime());
        }
        if ($entity instanceof AuditedEntityByInterface && $this->security) {
            $entity->setCreatedBy($this->security->getUser());
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        if (!($entity = $args->getObject())) {
            return;
        }
        if ($entity instanceof AuditedEntityDatesInterface) {
            $entity->setUpdated(new DateTime());
        }
        if ($entity instanceof AuditedEntityByInterface && $this->security) {
            $entity->setUpdatedBy($this->security->getUser());
        }
    }

    public function setSecurity(?\Symfony\Bundle\SecurityBundle\Security $security)
    {
        $this->security = $security;
    }
}
