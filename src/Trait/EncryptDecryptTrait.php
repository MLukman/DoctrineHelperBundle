<?php

namespace MLukman\DoctrineHelperBundle\Trait;

trait EncryptDecryptTrait
{
    protected static string $encryptionKey;

    protected static function getEncryptionKey(int $length = 32): string
    {
        if (empty(static::$encryptionKey ?? null)) {
            static::$encryptionKey = $_ENV['APP_SECRET'] ?? die("Server admin needs to define APP_SECRET environment variable");
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
        $ivlen = openssl_cipher_iv_length($cipher = "AES-256-GCM");
        $iv = openssl_random_pseudo_bytes($ivlen);
        $tag = null;
        $ciphertext_raw = openssl_encrypt($to_encrypt, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        $hmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary = true);
        return base64_encode($iv . $hmac . $tag . $ciphertext_raw);
    }

    public static function decrypt(?string $cipherText = null): ?string
    {
        if (empty($cipherText) || !($c = base64_decode($cipherText))) {
            return null;
        }
        try {
            $key = static::getEncryptionKey(32);
            $ivlen = openssl_cipher_iv_length($cipher = "AES-256-GCM");
            $iv = substr($c, 0, $ivlen);
            $hmac = substr($c, $ivlen, $sha2len = 32);
            $tag = substr($c, $ivlen + $sha2len, 16);
            $ciphertext_raw = substr($c, $ivlen + $sha2len + 16);
            $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary = true);
            if (hash_equals($hmac, $calcmac)) { // timing attack safe comparison
                return openssl_decrypt($ciphertext_raw, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
            }
        } catch (\Exception $ex) {
        }
        return null;
    }
}
