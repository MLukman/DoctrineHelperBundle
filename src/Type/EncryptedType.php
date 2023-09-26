<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\TextType;
use InvalidArgumentException;

class EncryptedType extends TextType
{

    public function getName(): string
    {
        return "encrypted";
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        if (!$value instanceof EncryptedValue) {
            $name = $this->getName();
            $class = EncryptedValue::class;
            throw new InvalidArgumentException("Column of type '$name' can only accept an object of class '$class'");
        }
        return $value->getEncryptedValue();
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        $obj = new EncryptedValue();
        $obj->setEncryptedValue($value);
        return $obj;
    }
}