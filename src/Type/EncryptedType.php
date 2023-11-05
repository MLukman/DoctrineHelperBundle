<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\TextType;

class EncryptedType extends TextType
{
    protected static string $encryptionKey;

    public function getName(): string
    {
        return "encrypted";
    }

    protected static function getEncryptionKey(int $length = 32): string
    {
        if (empty(static::$encryptionKey ?? null)) {
            static::$encryptionKey = $_ENV['APP_SECRET'] ?? "Lorem ipsum dolor sit amet eget.";
        }
        return substr(
            str_repeat(
                static::$encryptionKey,
                intval(
                    ceil(1.0 * $length / strlen(static::$encryptionKey))
                )
            ),
            0,
            $length
        );
    }

    public static function setEncryptionKey(string $encryptionKey): void
    {
        static::$encryptionKey = $encryptionKey;
    }

    public static function encrypt(?string $plainText = null): ?string
    {
        if (!($to_encrypt = $plainText)) {
            return null;
        }
        $key = static::getEncryptionKey(32);
        $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($to_encrypt, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary = true);
        return base64_encode($iv.$hmac.$ciphertext_raw);
    }

    public static function decrypt(?string $cipherText = null): ?string
    {
        if (empty($cipherText) || !($c = base64_decode($cipherText))) {
            return null;
        }
        try {
            $key = static::getEncryptionKey(32);
            $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
            $iv = substr($c, 0, $ivlen);
            $hmac = substr($c, $ivlen, $sha2len = 32);
            $ciphertext_raw = substr($c, $ivlen + $sha2len);
            $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary = true);
            if (hash_equals($hmac, $calcmac)) { // timing attack safe comparison
                return openssl_decrypt($ciphertext_raw, $cipher, $key, OPENSSL_RAW_DATA, $iv);
            }
        } catch (\Exception $ex) {

        }
        return null;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        return static::encrypt($value);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        return static::decrypt($value) ?? $value;
    }
}