<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\TextType;
use MLukman\DoctrineHelperBundle\Trait\EncryptDecryptTrait;

class EncryptedType extends TextType
{
    use EncryptDecryptTrait;

    public function getName(): string
    {
        return "encrypted";
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return static::encrypt($value);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return static::decrypt($value) ?? $value;
    }
}
