<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BlobType;
use InvalidArgumentException;

/**
 * This subclass of BlobType stores & retrieves a file content along with its
 * filename, size & mime type to & from database LONGBLOB column.
 * MUST be used with FileWrapper objects only.
 */
class FileType extends BlobType
{

    public function getName(): string
    {
        return "file";
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        if (!$value instanceof FileWrapper) {
            $name = $this->getName();
            $class = FileWrapper::class;
            throw new InvalidArgumentException("Column of type '$name' can only accept an object of class '$class'");
        }
        return \pack('a255Qa255H*',
            $value->getName(),
            $value->getSize(),
            $value->getMimetype(),
            bin2hex($value->getContent()));
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }
        $unpacked = \unpack('a255name/Qsize/a255mimetype/H*content', $value);
        $filestore = new FileWrapper(trim($unpacked['name']), $unpacked['size'], trim($unpacked['mimetype']));
        $filestore->setContent(hex2bin($unpacked['content']));
        return $filestore;
    }
}