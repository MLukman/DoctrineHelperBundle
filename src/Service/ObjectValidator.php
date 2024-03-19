<?php

namespace MLukman\DoctrineHelperBundle\Service;

use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ObjectValidator
{

    public function __construct(private ValidatorInterface $validator)
    {

    }

    public function validate(mixed $entity, bool $asArray = true,
                             array &$errors = [], array $groups = []): array
    {
        $validationResults = $this->validator->validate($entity, null, $groups);
        foreach ($validationResults as $violation) {
            /* @var $violation ConstraintViolationInterface */
            $this->addValidationError($errors, $violation->getPropertyPath(), $violation);
        }
        if ($asArray) {
            return array_map(
                function (array $e) {
                    return array_map(
                    function (ConstraintViolation|string $f) {
                        return ($f instanceof ConstraintViolation) ?
                        $f->getMessage() : $f;
                    },
                    $e);
                },
                $errors);
        }
        return $errors;
    }

    public function addValidationError(array &$errors, string $prop,
                                       ConstraintViolationInterface|string $violation)
    {
        if (!isset($errors[$prop])) {
            $errors[$prop] = [];
        }
        $errors[$prop][] = $violation;
    }
}