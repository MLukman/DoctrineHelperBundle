<?php

namespace MLukman\DoctrineHelperBundle\Type;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;

class TimestampIdGenerator extends AbstractIdGenerator
{
    public function generateId(EntityManagerInterface $em, $entity): string
    {
        $id = '';
        if (\method_exists($entity, 'getIdPrefix')) {
            $id .= $entity->getIdPrefix();
        }
        $id .= (new DateTime())->format('YmdHisu');
        if (\method_exists($entity, 'getIdSuffix')) {
            $id .= $entity->getIdSuffix();
        }
        return substr($id, 0, 50);
    }
}
