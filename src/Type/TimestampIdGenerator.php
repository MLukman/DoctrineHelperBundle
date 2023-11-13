<?php

namespace MLukman\DoctrineHelperBundle\Type;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;

class TimestampIdGenerator extends AbstractIdGenerator
{

    public function generateId(EntityManagerInterface $em, $entity): string
    {
        return
            (\method_exists($entity, 'getIdPrefix') ? $entity->getIdPrefix() : '').
            (new DateTime())->format('YmdHisu').
            (\method_exists($entity, 'getIdSuffix') ? $entity->getIdSuffix() : '');
    }
}