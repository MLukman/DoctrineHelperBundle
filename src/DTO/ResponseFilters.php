<?php

namespace MLukman\DoctrineHelperBundle\DTO;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * This class will be populated with criteria that are parsed from the query string parameter that is typed with this class.
 */
final class ResponseFilters
{
    public function __construct(private Criteria $_criteria)
    {
        
    }

    public function getCriteria(): Criteria
    {
        return $this->_criteria;
    }

    public function apply(array|Collection $collection)
    {
        if ($collection instanceof Collection) {
            $collection = $collection->toArray();
        }
        try {
            return array_values((new ArrayCollection($collection))->matching($this->_criteria)->toArray());
        } catch (Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}
