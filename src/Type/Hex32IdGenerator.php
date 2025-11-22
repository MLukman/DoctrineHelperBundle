<?php

namespace MLukman\DoctrineHelperBundle\Type;

/**
 * Custom Id Generator using unique random 32-character hexadecimal
 */
class Hex32IdGenerator extends BaseIdGenerator
{
    protected function generateRandomId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
