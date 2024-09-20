<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPTrait.php to edit this template
 */

namespace MLukman\DoctrineHelperBundle\Trait;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\MappedSuperclass]
trait AuditedEntityTrait
{
    #[ORM\Column(type: Types::DATETIME_MUTABLE, options: ["default" => "CURRENT_TIMESTAMP"])]
    protected ?DateTimeInterface $created = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?DateTimeInterface $updated = null;

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
        return $this;
    }

    public function setUpdated(?DateTimeInterface $updated): self
    {
        $this->updated = $updated;
        return $this;
    }

    /** Class to override */
    public function setCreatedBy(?\Symfony\Component\Security\Core\User\UserInterface $createdBy)
    {
        
    }

    /** Class to override */
    public function setUpdatedBy(?\Symfony\Component\Security\Core\User\UserInterface $updatedBy)
    {
        
    }
}
