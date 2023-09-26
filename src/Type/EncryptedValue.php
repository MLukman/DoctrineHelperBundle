<?php

namespace MLukman\DoctrineHelperBundle\Type;

use Stringable;

class EncryptedValue implements Stringable
{
    protected ?string $encryptedValue = null;

    public function __construct(protected ?string $plainValue = null)
    {
        $this->encryptedValue = $plainValue;
        $this->encrypt();
    }

    public function getEncryptedValue(): ?string
    {
        return $this->encryptedValue;
    }

    public function getPlainValue(): ?string
    {
        return $this->plainValue;
    }

    public function setEncryptedValue(?string $encryptedValue): void
    {
        $this->encryptedValue = $encryptedValue;
        $this->decrypt();
    }

    public function setPlainValue(?string $plainValue): void
    {
        $this->plainValue = $plainValue;
        $this->encrypt();
    }

    public function __toString(): string
    {
        return $this->getPlainValue();
    }

    public function encrypt(?string $plainText = null): ?string
    {
        if (!($to_encrypt = $plainText ?: $this->plainValue)) {
            return $this->encryptedValue = null;
        }
        $key = $this->getEncryptionKey();
        $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($to_encrypt, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary = true);
        return $this->encryptedValue = base64_encode($iv.$hmac.$ciphertext_raw);
    }

    public function decrypt(?string $cipherText = null): ?string
    {
        if (!($to_decrypt = $cipherText ?: $this->encryptedValue)) {
            return $this->plainValue = null;
        }
        $key = $this->getEncryptionKey();
        $c = base64_decode($to_decrypt);
        $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, $sha2len = 32);
        $ciphertext_raw = substr($c, $ivlen + $sha2len);
        $plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, $options = OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary = true);
        if (hash_equals($hmac, $calcmac)) {// timing attack safe comparison
            return $this->plainValue = $plaintext;
        }
        return null;
    }

    protected function getEncryptionKey(int $length = 32): string
    {
        $key = $_ENV['APP_SECRET'] ?? "12345";
        return substr(str_repeat($key, intval(ceil(1.0 * $length / strlen($key)))), 0, $length);
    }
}