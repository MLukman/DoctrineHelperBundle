<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;

class Hex16IdGenerator extends AbstractIdGenerator
{

    public function generateId(EntityManagerInterface $em, $entity): string
    {
        return bin2hex(random_bytes(8));
    }
}