<?php

namespace MLukman\DoctrineHelperBundle\Interface;

use Symfony\Component\Security\Core\User\UserInterface;

interface AuditedEntityByInterface
{
    public function setCreatedBy(?UserInterface $createdBy);
    public function setUpdatedBy(?UserInterface $updatedBy);
}
