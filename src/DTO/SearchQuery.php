<?php

namespace MLukman\DoctrineHelperBundle\DTO;

use Doctrine\ORM\QueryBuilder;

final class SearchQuery
{

    public function __construct(protected string $name,
                                protected ?string $keyword)
    {

    }

    public function apply(QueryBuilder $qb, array|string $columns): QueryBuilder
    {
        return $this->applyLikeSearch($qb, $columns);
    }

    public function applyLikeSearch(QueryBuilder $qb, array|string $columns): QueryBuilder
    {
        if (!$this->keyword) {
            return $qb;
        }
        if (!is_array($columns)) {
            $columns = [$columns];
        }
        $conditions = [];
        foreach ($columns as $column) {
            $conditions[] = $qb->expr()->like($column, ':keyword');
        }
        return $qb->andWhere($qb->expr()->orX()->addMultiple($conditions))->setParameter('keyword', '%'.$this->keyword.'%');
    }

    public function applyFulltextSearch(QueryBuilder $qb,
                                        array $fulltextColumnsWithAlias,
                                        bool $or = false): QueryBuilder
    {
        $commaDelimitedColumns = join(", ", $fulltextColumnsWithAlias);
        $conditions = "MATCH_AGAINST({$commaDelimitedColumns}, :searchterm  'IN BOOLEAN MODE')";
        $sortColumn = "score".random_int(100, 999);
        $qb->addSelect("$conditions as HIDDEN $sortColumn")->addOrderBy($sortColumn, 'DESC');
        $qb->setParameter('searchterm', $this->keyword);
        if ($or) {
            return $qb->orWhere("$conditions  > 0");
        } else {
            return $qb->andWhere("$conditions  > 0");
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKeyword(): ?string
    {
        return $this->keyword;
    }
}