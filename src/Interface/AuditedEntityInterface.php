<?php

namespace MLukman\DoctrineHelperBundle\Interface;

use DateTimeInterface;

interface AuditedEntityInterface
{
    public function getCreated(): ?DateTimeInterface;
    public function getUpdated(): ?DateTimeInterface;
    public function getSaved(): ?DateTimeInterface;
    public function setCreated(?DateTimeInterface $created);
    public function setUpdated(?DateTimeInterface $updated);
    public function setCreatedBy(?\Symfony\Component\Security\Core\User\UserInterface $createdBy);
    public function setUpdatedBy(?\Symfony\Component\Security\Core\User\UserInterface $updatedBy);
}
