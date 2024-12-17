<?php

namespace MLukman\DoctrineHelperBundle\Service;

use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ObjectValidatorV2
{
    private array $errors = [];

    public function __construct(private ValidatorInterface $validator)
    {

    }

    public function validate(mixed $entity, string|GroupSequence|array|null $groups = null): array
    {
        $validationResults = $this->validator->validate($entity, null, $groups);
        foreach ($validationResults as $violation) {
            /* @var $violation ConstraintViolationInterface */
            $this->addValidationError($violation->getPropertyPath(), $violation);
        }
        return $this->errors;
    }

    public function addValidationError(string $prop, ConstraintViolationInterface|string $violation)
    {
        if (!isset($this->errors[$prop])) {
            $this->errors[$prop] = [];
        }
        $this->errors[$prop][] = ($violation instanceof ConstraintViolation) ? $violation->getMessage() : $violation;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function reset(): void
    {
        $this->errors = [];
    }
}
