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
class FSFileType extends JsonType
{
    public function getName(): string
    {
        return "fsfile";
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
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
            'datetime' => $value->getDatetime()->getTimestamp(),
        ];

        return \json_encode($metadata);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?FileWrapper
    {
        if ($value === null) {
            return null;
        }
        $values = \json_decode($value, true);
        return new FileWrapper(
            $values['name'],
            $values['size'],
            $values['mimetype'],
            $values['uuid'],
            isset($values['datetime']) ? (new DateTime())->setTimestamp($values['datetime']) : null
        );
    }
}
