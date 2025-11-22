<?php

namespace MLukman\DoctrineHelperBundle\Type;

/**
 * Custom Id Generator using unique random 16-character hexadecimal
 */
class Hex16IdGenerator extends BaseIdGenerator
{
    protected function generateRandomId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
