<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BlobType;

class ImageType extends BlobType
{
    public function getName(): string
    {
        return "image";
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ImageWrapper
    {
        $blob = parent::convertToPHPValue($value, $platform);
        return $blob ? new ImageWrapper($blob) : null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        return $value ? $value->get() : null;
    }
}
