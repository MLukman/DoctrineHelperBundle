<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\TextType;
use MLukman\DoctrineHelperBundle\Trait\EncryptDecryptTrait;
use Symfony\Component\Serializer\SerializerInterface;

class EncryptedObjectType extends TextType
{
    use EncryptDecryptTrait;

    private SerializerInterface $serializer;

    public function getName(): string
    {
        return "encrypted_object";
    }
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return static::encrypt(serialize($value));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        return ($decrypted = static::decrypt($value)) ? unserialize($decrypted) : null;
    }
}
