<?php

namespace MLukman\DoctrineHelperBundle\Trait;

use Doctrine\ORM\Mapping as ORM;
use MLukman\DoctrineHelperBundle\Type\TimestampIdGenerator;

/**
 * A trait that an entity class can use to have its Id generated using timestamp string format,
 * e.g 20230101123456789012
 */
trait TimestampIdEntityTrait
{
    #[ORM\Id]
    #[ORM\Column(length: 50)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: TimestampIdGenerator::class)]
    protected ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Class to override this method if the generated Id needs to be prefixed with a custom string
     * @return string
     */
    public function getIdPrefix(): string
    {
        return '';
    }

    /**
     * Class to override this method if the generated Id needs to be suffixed with a custom string
     * @return string
     */
    public function getIdSuffix(): string
    {
        return '';
    }
}