<?php

namespace MLukman\DoctrineHelperBundle\Trait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\MappedSuperclass]
trait AuditedEntityTrait
{

    use AuditedEntityDatesTrait;

    public function setCreatedBy(?UserInterface $createdBy)
    {
        
    }

    public function setUpdatedBy(?UserInterface $updatedBy)
    {
        
    }
}
