<?php

namespace MLukman\DoctrineHelperBundle\Type;

/**
 * Custom Id Generator using unique random 8-character hexadecimal
 */
class Hex8IdGenerator extends BaseIdGenerator
{
    protected function generateRandomId(): string
    {
        return bin2hex(random_bytes(4));
    }
}
