<?php

namespace MLukman\DoctrineHelperBundle\Trait;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use MLukman\DoctrineHelperBundle\Service\Timezonify;

#[ORM\MappedSuperclass]
trait AuditedEntityDatesTrait
{
    #[Timezonify]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ["default" => "CURRENT_TIMESTAMP"])]
    protected ?DateTimeInterface $created = null;

    #[Timezonify]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?DateTimeInterface $updated = null;

    #[Timezonify]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ["default" => "CURRENT_TIMESTAMP"])]
    protected ?DateTimeInterface $saved = null;

    public function getCreated(): ?DateTimeInterface
    {
        return $this->created;
    }

    public function getUpdated(): ?DateTimeInterface
    {
        return $this->updated;
    }

    public function setCreated(?DateTimeInterface $created): self
    {
        $this->created = $created;
        $this->saved = $created;
        return $this;
    }

    public function setUpdated(?DateTimeInterface $updated): self
    {
        $this->updated = $updated;
        $this->saved = $updated;
        return $this;
    }

    public function getSaved(): ?DateTimeInterface
    {
        return $this->saved;
    }
}
