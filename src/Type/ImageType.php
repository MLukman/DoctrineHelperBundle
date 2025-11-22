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

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ImageWrapper
    {
        $blob = parent::convertToPHPValue($value, $platform);
        try {
            return !empty($blob) ? new ImageWrapper($blob) : null;
        } catch (\Exception) {
            return null;
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return $value instanceof ImageWrapper ? $value->get() : null;
    }
}
