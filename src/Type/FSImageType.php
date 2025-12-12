<?php

namespace MLukman\DoctrineHelperBundle\Type;

use DateTime;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;
use InvalidArgumentException;

/**
 * This subclass of BlobType stores & retrieves a file content along with its
 * filename, size & mime type to & from database LONGBLOB column.
 * MUST be used with FileWrapper objects only.
 */
class FSImageType extends JsonType
{
    public function getName(): string
    {
        return "fsimage";
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof ImageWrapper) {
            $name = $this->getName();
            $class = ImageWrapper::class;
            throw new InvalidArgumentException("Column of type '$name' can only accept an object of class '$class'");
        }

        $metadata = [
            'uuid' => $value->uuid(),
            'outputFormat' => $value->getOutputFormat(),
        ];

        return \json_encode($metadata);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ImageWrapper
    {
        if ($value === null) {
            return null;
        }
        $values = \json_decode($value, true);
        return new ImageWrapper(outputFormat: $values['outputFormat'], uuid: $values['uuid']);
    }
}
