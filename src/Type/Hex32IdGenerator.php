<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;

class Hex32IdGenerator extends AbstractIdGenerator
{
    public function generateId(EntityManagerInterface $em, $entity): string
    {
        return bin2hex(random_bytes(16));
    }
}
