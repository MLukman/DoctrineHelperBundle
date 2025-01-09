<?php

namespace MLukman\DoctrineHelperBundle\Tests\App;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use MLukman\DoctrineHelperBundle\DTO\RequestBodyTargetInterface;

class SampleRequestBodyTarget implements RequestBodyTargetInterface
{
    public ?string $name = 'default';
    public ?float $age;
    public ?string $comment;
    public ?SampleRequestBodyTarget $nested;
    public ?SampleRequestBodyTarget $fromScalar;
    public ?array $attributes;
    public ?ArrayCollection $children;
    public ?array $stringToArray;
    public ?DateTime $date;
}
