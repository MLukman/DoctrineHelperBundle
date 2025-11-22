<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Exception;

/**
 * Base class for Custom Id Generators with uniqueness checking
 */
abstract class BaseIdGenerator extends AbstractIdGenerator
{
    abstract protected function generateRandomId(): string;
    public function generateId(EntityManagerInterface $em, object|null $entity): mixed
    {
        do {
            $id = $this->generateRandomId();
            try {
                $existing = $em->find(get_class($entity), $id);
            } catch (Exception $e) {
                return $id; // since can't check uniqueness just say it's unique
            }
        } while ($existing !== null);
        return $id;
    }
}
