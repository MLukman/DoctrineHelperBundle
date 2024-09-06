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
        $data = $value->getContent();
        $compression = function_exists("gzencode") ? "gzip" : "";
        switch ($compression) {
            case "gzip":
                $data = gzencode($data);
                break;
        }
        return \pack('a255Qa250a5H*',
            $value->getName(),
            $value->getSize(),
            $value->getMimetype(),
            $compression,
            bin2hex($data));
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }
        $unpacked = \unpack('a255name/Qsize/a250mimetype/a5compression/H*content', $value);
        $filestore = new FileWrapper(trim($unpacked['name']), $unpacked['size'], trim($unpacked['mimetype']));
        $data = hex2bin($unpacked['content']);
        if (trim($unpacked['compression']) == 'gzip') {
            $data = gzdecode($data);
        }
        $filestore->setContent($data);
        return $filestore;
    }
}