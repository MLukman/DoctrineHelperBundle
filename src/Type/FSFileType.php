<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;
use InvalidArgumentException;

/**
 * This subclass of BlobType stores & retrieves a file content along with its
 * filename, size & mime type to & from database LONGBLOB column.
 * MUST be used with FileWrapper objects only.
 */
class FSFileType extends JsonType
{
    public function getName(): string
    {
        return "fsfile";
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof FileWrapper) {
            $name = $this->getName();
            $class = FileWrapper::class;
            throw new InvalidArgumentException("Column of type '$name' can only accept an object of class '$class'");
        }

        $metadata = [
            'name' => $value->getName(),
            'size' => $value->getSize(),
            'mimetype' => $value->getMimetype(),
            'uuid' => $value->getUuid(),
        ];

        return \json_encode($metadata);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }
        extract(\json_decode($value, true));
        return new FileWrapper($name, $size, $mimetype, $uuid);
    }
}
