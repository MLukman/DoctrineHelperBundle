<?php

namespace MLukman\DoctrineHelperBundle\Type;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\TextType;

class DateStringType extends TextType
{
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTime
    {
        return DateTime::createFromFormat('Ymd', $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Ymd');
        }
        return parent::convertToDatabaseValue($value, $platform);
    }
}
