<?php

namespace MLukman\DoctrineHelperBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\Service\Attribute\Required;

class DataStore
{
    private $caches = [];
    protected EntityManagerInterface $em;

    #[Required]
    public function requiredByDataStore(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function em(): EntityManagerInterface
    {
        return $this->em;
    }

    public function repo(string $entity): EntityRepository
    {
        return $this->em->getRepository($entity);
    }

    public function queryBuilder(?string $entity = null, string $alias = 'e'): QueryBuilder
    {
        return $entity ? $this->repo($entity)->createQueryBuilder($alias) : $this->em->createQueryBuilder();
    }

    public function createQuery(string $dql): Query
    {
        return $this->em->createQuery($dql);
    }

    /**
     *
     * @param string $entity
     * @param mixed $id_or_criteria
     * @return mixed
     */
    public function queryOne(string $entity, mixed $id_or_criteria, array $orderBy = []): mixed
    {
        if (empty($id_or_criteria)) {
            return null;
        }

        if (is_array($id_or_criteria)) {
            $result = $this->queryMany($entity, $id_or_criteria, $orderBy, 1);
            return $result[0] ?? null;
        } else {
            return $this->repo($entity)->find($id_or_criteria);
        }
    }

    public function cachedQueryOne(string $entity, $id_or_criteria, array $sort = []): mixed
    {
        $signature = md5(serialize([$entity, $id_or_criteria, $sort]));
        if (!isset($this->caches[$signature]) || is_null($this->caches[$signature])) {
            $this->caches[$signature] = $this->queryOne($entity, $id_or_criteria, $sort);
        }
        return $this->caches[$signature];
    }

    /**
     *
     * @param string $entity
     * @param array $criteria
     * @param array $sort
     * @return array
     */
    public function queryMany(string $entity, array $criteria = [], array $sort = [], $limit = null): array
    {
        $qb = $this->queryBuilder($entity, 'a');
        $this->applyCriteriaToQueryBuilder($criteria, $qb);
        foreach ($sort as $col => $ord) {
            if (substr($ord, -2) == '()') {
                // sort by function
                $qb->addOrderBy($ord);
                break;
            } else {
                $qb->addOrderBy("a.$col", $ord);
            }
        }
        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }
        return $qb->getQuery()->execute();
    }

    /**
     *
     * @param string $entity
     * @param array $criteria
     * @return int
     */
    public function count(string $entity, array $criteria = []): int
    {
        $qb = $this->queryBuilder($entity, 'a')->select('count(a)');
        $this->applyCriteriaToQueryBuilder($criteria, $qb);
        return $qb->getQuery()->getSingleScalarResult();
    }

    public function countBy(string $entity, string|array $groupByField, array $criteria = []): array
    {
        if (!is_array($groupByField)) {
            $groupByField = [$groupByField];
        }
        $groupByField = array_filter($groupByField);
        if (empty($groupByField)) {
            return [];
        }
        $qb = $this->queryBuilder($entity, 'a');
        $this->applyCriteriaToQueryBuilder($criteria, $qb);
        $select = "count(a) as num";
        foreach ($groupByField as $f) {
            $qb->addGroupBy("a.$f");
            $select = "a.$f, $select";
        }
        $qb->select($select);
        $counts = [];
        $recurse = function (array $result, array &$fields) use (&$recurse) {
            return ($f = array_shift($fields)) ?
                [($result[$f]) => $recurse($result, $fields)] :
                $result['num'];
        };
        foreach ($qb->getQuery()->getArrayResult() as $result) {
            $fields = $groupByField;
            $counts = array_merge_recursive($counts, $recurse($result, $fields));
        }
        return $counts;
    }

    protected function applyCriteriaToQueryBuilder(array $criteria, QueryBuilder $qb, bool $or = false): void
    {
        $alias = $qb->getRootAliases()[0];
        $applyer = $or ? fn($qb, $where) => $qb->orWhere($where) : fn($qb, $where) => $qb->andWhere($where);
        foreach ($criteria as $col => $val) {
            $opmatch = [];
            if (preg_match('/^(>=|<=|>|<|!)(.*)$/', $col, $opmatch)) {
                $op = $opmatch[1] == '!' ? '!=' : $opmatch[1];
                $col = $opmatch[2];
            } else {
                $op = '=';
            }

            if (is_null($val)) {
                switch ($op) { // only '!' and '=' support null values
                    case '!=':
                        $applyer($qb, "$alias.$col IS NOT NULL");
                        break;
                    case '=':
                        $applyer($qb, "$alias.$col IS NULL");
                        break;
                }
            } else {
                if (is_array($val)) {
                    $op = $op == '!=' ? "NOT IN" : "IN";
                    $applyer($qb, "$alias.$col $op (:$col)");
                } else {
                    $applyer($qb, "$alias.$col $op :$col");
                }
                $qb->setParameter($col, $val);
            }
        }
    }

    /**
     *
     * @param string $entity
     * @param array $filters
     * @param array $sort
     * @param int $limit
     * @return array
     */
    public function queryUsingOr(string $entity, array $filters = [], array $sort = [], int $limit = 0): array
    {
        $qb = $this->queryBuilder($entity, 'e');
        $this->applyCriteriaToQueryBuilder($filters, $qb, true);
        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }
        foreach ($sort as $srt => $ord) {
            $qb->addOrderBy("e.$srt", $ord);
        }
        return $qb->getQuery()->execute();
    }

    /**
     * Perform full-text for $searchterm on the provided $fulltextColumns
     * @param string $entity The entity class name
     * @param array $fulltextColumns The array of columns that have fulltext index
     * @param string $searchterm The term to search for
     * @param array $filters Optional filter conditions (added as WHERE key = value)
     * @return array The search results
     */
    public function fulltextSearch($entity, array $fulltextColumns, string $searchterm, array $filters = []): QueryBuilder
    {
        $commaDelimitedColumns = join(", ", array_map(function ($item) {
            return "e.$item";
        }, $fulltextColumns));
        /** @var QueryBuilder $qb */
        $qb = $this->queryBuilder($entity, 'e')
            ->addSelect("MATCH_AGAINST ({$commaDelimitedColumns}, :searchterm 'IN BOOLEAN MODE') as HIDDEN score")
            ->add('where', "MATCH_AGAINST({$commaDelimitedColumns}, :searchterm  'IN BOOLEAN MODE') > 0")
            ->setParameter('searchterm', $searchterm)
            ->orderBy('score', 'desc');
        $this->applyCriteriaToQueryBuilder($filters, $qb);
        return $qb;
    }

    /**
     * Query for at most one entity that is previous to current based on
     * sortedBy field and optionally a list of matcher fields
     * @param mixed $current The current entity
     * @param string $sortedBy The field name to sort by with
     * @param array $matcherFields Optional list of fields that need to match with those of the current
     * @return mixed The previous entity or null
     */
    public function getPrevious(mixed $current, string $sortedBy, array $matcherFields = []): mixed
    {
        $qb = $this->previousNextQueryBuilder($current, $sortedBy, $matcherFields, 'prev');
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Query for at most one entity that is next to current based on
     * sortedBy field and optionally a list of matcher fields
     * @param mixed $current The current entity
     * @param string $sortedBy The field name to sort by with
     * @param array $matcherFields Optional list of fields that need to match with those of the current
     * @return mixed The next entity or null
     */
    public function getNext(mixed $current, string $sortedBy, array $matcherFields = []): mixed
    {
        $qb = $this->previousNextQueryBuilder($current, $sortedBy, $matcherFields, 'next');
        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Count how many entities previous to current based on
     * sortedBy field and optionally a list of matcher fields
     * @param mixed $current The current entity
     * @param string $sortedBy The field name to sort by with
     * @param array $matcherFields Optional list of fields that need to match with those of the current
     * @return int The count
     */
    public function countPrevious(mixed $current, string $sortedBy, array $matcherFields = []): int
    {
        $qb = $this->previousNextQueryBuilder($current, $sortedBy, $matcherFields, 'prev');
        return $qb->select('COUNT(1)')->getQuery()->getSingleScalarResult();
    }

    /**
     * Count how many entities next to current based on
     * sortedBy field and optionally a list of matcher fields
     * @param mixed $current The current entity
     * @param string $sortedBy The field name to sort by with
     * @param array $matcherFields Optional list of fields that need to match with those of the current
     * @return int The count
     */
    public function countNext(mixed $current, string $sortedBy, array $matcherFields = []): int
    {
        $qb = $this->previousNextQueryBuilder($current, $sortedBy, $matcherFields, 'next');
        return $qb->select('COUNT(1)')->getQuery()->getSingleScalarResult();
    }

    protected function previousNextQueryBuilder(mixed $current, string $sortedBy, array $matcherFields = [], string $prevOrNext = 'next'): QueryBuilder
    {
        $class = get_class($current);
        $qb = $this->queryBuilder($class, 'e')
            ->join($class, 'c', 'WITH', 'c = :current')
            ->andWhere('e != c')
            ->setParameter('current', $current)
            ->setMaxResults(1);
        foreach ($matcherFields as $field) {
            $qb->andWhere("e.{$field} = c.{$field}");
        }
        switch ($prevOrNext) {
            case 'prev':
            case 'previous':
                $qb->andWhere("e.{$sortedBy} <= c.{$sortedBy}")
                    ->orderBy("e.{$sortedBy}", 'DESC');
                break;
            case 'next':
                $qb->andWhere("e.{$sortedBy} >= c.{$sortedBy}")
                    ->orderBy("e.{$sortedBy}", 'ASC');
                break;
        }
        return $qb;
    }

    public function commit(&...$entities)
    {
        foreach ($entities as $entity) {
            $this->manage($entity);
        }
        $this->em->flush();
        foreach ($entities as $entity) {
            $this->reloadEntity($entity);
        }
    }

    public function delete(&$entity)
    {
        $this->em->remove($entity);
        $this->em->flush();
        $entity = null;
    }

    public function manage($entity)
    {
        $this->em->persist($entity);
    }

    public function unmanage($entity)
    {
        $this->em->detach($entity);
    }

    public function reloadEntity(&$entity)
    {
        $this->em->refresh($entity);
    }
}
