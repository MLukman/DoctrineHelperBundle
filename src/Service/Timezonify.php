<?php

namespace MLukman\DoctrineHelperBundle\Service;

use DateTime;
use DateTimeZone;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Exception;
use MLukman\DoctrineHelperBundle\Attribute\Timezonify as TimezonifyAttribute;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * This service provides conversion of DateTime objects between UTC timezone and
 * the application timezone that can be either specified using the environment
 * variable APP_TIMEZONE or modified on-the-fly for each user session by calling
 * setTimezone() method.
 *
 * This service also automatically handles conversion of all DateTime fields of 
 * Doctrine entities to UTC when storing to database and from UTC when reading
 * from database.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postLoad)]
final class Timezonify
{
    protected DateTimeZone $tz;
    protected DateTimeZone $utc;
    protected PropertyAccessor $propertyAccessor;

    public function __construct(#[Autowire('%env(default::APP_TIMEZONE)%')] ?string $app_timezone)
    {
        $this->utc = new DateTimeZone("GMT");
        $this->setTimezone($app_timezone ?: date_default_timezone_get());
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function setTimezone(string|DateTimeZone $timezone): void
    {
        $this->tz = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
        if ($this->tz) {
            date_default_timezone_set($timezone);
        }
    }

    public function getTimezone(): DateTimeZone
    {
        return $this->tz;
    }

    public function convertToUTC(DateTime $original): DateTime
    {
        $newdt = new \DateTime($original->format('Y-m-d h:i:s A'), $this->tz);
        $newdt->setTimezone($this->utc);
        return $newdt;
    }

    public function convertFromUTC(DateTime $original): DateTime
    {
        $newdt = new \DateTime($original->format('Y-m-d h:i:s A'), $this->utc);
        $newdt->setTimezone($this->tz);
        return $newdt;
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        if (!($entity = $args->getObject())) {
            return;
        }
        $reflection = new ReflectionClass($entity);
        foreach ($reflection->getProperties() as $property) {
            $this->updateObjectDateTimeProperty($entity, $property, fn($value) => $this->convertToUTC($value));
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        if (!($entity = $args->getObject())) {
            return;
        }

        $reflection = new ReflectionClass($entity);
        foreach ($reflection->getProperties() as $property) {
            if (!$args->hasChangedField($property->getName())) {
                continue;
            }
            $this->updateObjectDateTimeProperty($entity, $property, fn($value) => $this->convertToUTC($value));
        }
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        if (!($entity = $args->getObject())) {
            return;
        }

        $reflection = new ReflectionClass($entity);
        foreach ($reflection->getProperties() as $property) {
            $this->updateObjectDateTimeProperty($entity, $property, fn($value) => $this->convertFromUTC($value));
        }
    }

    protected function updateObjectDateTimeProperty(
            mixed $object, ReflectionProperty $property, callable $updateFn
    ) {
        try {
            $property_name = $property->getName();

            if (!empty($property->getAttributes(TimezonifyAttribute::class)) &&
                    $property->isInitialized($object) &&
                    $this->propertyAccessor->isReadable($object, $property_name) &&
                    ($value = $this->propertyAccessor->getValue($object, $property_name)) instanceof DateTime && $this->propertyAccessor->isWritable($object, $property_name)) {
                $this->propertyAccessor->setValue($object, $property_name, $updateFn($value));
            }
        } catch (Exception $ex) {
            // Catch-all for whatever exception that got thrown due to Doctrine's peculiarity
            //  with using proxies and lazy-loading shenanigans
        }
    }
}
