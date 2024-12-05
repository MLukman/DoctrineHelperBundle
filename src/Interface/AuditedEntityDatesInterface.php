<?php

namespace MLukman\DoctrineHelperBundle\Interface;

use DateTimeInterface;

interface AuditedEntityDatesInterface
{
    public function getCreated(): ?DateTimeInterface;
    public function getUpdated(): ?DateTimeInterface;
    public function getSaved(): ?DateTimeInterface;
    public function setCreated(?DateTimeInterface $created);
    public function setUpdated(?DateTimeInterface $updated);
}
