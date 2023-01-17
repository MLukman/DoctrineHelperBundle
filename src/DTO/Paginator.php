<?php

namespace MLukman\DoctrineHelperBundle\DTO;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

class Paginator
{
    protected int $defaultLimit = 100;
    protected int $count = 0;
    protected int $maxPage = 0;

    public function __construct(protected int $page, protected int $limit)
    {
        $this->page = max(1, $this->page);
    }

    public function getDefaultLimit(): int
    {
        return $this->defaultLimit;
    }

    public function setDefaultLimit(int $defaultLimit): self
    {
        $this->defaultLimit = max($defaultLimit, 1);
        $this->calculateMaxPage();
        return $this;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return ($this->limit > 0) ? $this->limit : $this->defaultLimit;
    }

    protected function calculateMaxPage()
    {
        $this->maxPage = max(1, ceil($this->count * 1.0 / $this->getLimit()));
    }

    public function getMaxPage(): int
    {
        return $this->maxPage;
    }

    public function getFrom(): int
    {
        return $this->count == 0 ? 0 : ($this->page - 1) * $this->getLimit() + 1;
    }

    public function getTo(): int
    {
        return $this->count == 0 ? 0 : min($this->page * $this->getLimit(), $this->count);
    }

    public function paginateResults(QueryBuilder $qb): array
    {
        $limit = $this->getLimit();
        $clonedQB = clone $qb;
        $clonedQB->setMaxResults($limit)->setFirstResult(($this->page - 1) * $limit);
        $paginator = new DoctrinePaginator($clonedQB, true);
        $this->count = count($paginator);
        $this->calculateMaxPage();
        if ($this->page > $this->maxPage) {
            $this->page = $this->maxPage;
            return $this->paginateResults($qb);
        }
        return iterator_to_array($paginator);
    }
}