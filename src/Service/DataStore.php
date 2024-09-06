<?php

namespace MLukman\DoctrineHelperBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

class DataStore
{
    private $caches = [];

    public function __construct(protected EntityManagerInterface $em)
    {

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
     * @param type $entity
     * @param type $id_or_criteria
     * @return mixed
     */
    public function queryOne(string $entity, $id_or_criteria,
                             array $orderBy = null): mixed
    {
        if (empty($id_or_criteria)) {
            return null;
        }

        return (is_array($id_or_criteria) ?
            $this->repo($entity)->findOneBy($id_or_criteria, $orderBy) :
            $this->repo($entity)->find($id_or_criteria));
    }

    public function cachedQueryOne(string $entity, $id_or_criteria,
                                   array $orderBy = null): mixed
    {
        $signature = md5(serialize([$entity, $id_or_criteria, $orderBy]));
        if (!isset($this->caches[$signature]) || is_null($this->caches[$signature])) {
            $this->caches[$signature] = $this->queryOne($entity, $id_or_criteria, $orderBy);
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
    public function queryMany(string $entity, array $criteria = [],
                              array $sort = [], $limit = null): array
    {
        return $this->repo($entity)->findBy($criteria, $sort, $limit);
    }

    /**
     *
     * @param string $entity
     * @param array $criteria
     * @return int
     */
    public function count(string $entity, array $criteria = []): int
    {
        return $this->repo($entity)->count($criteria);
    }

    /**
     *
     * @param type $entity
     * @param array $filters
     * @param array $sort
     * @param type $limit
     * @return array
     */
    public function queryUsingOr(string $entity, array $filters = [],
                                 array $sort = [], int $limit = 0): array
    {
        $qb = $this->queryBuilder($entity, 'e');
        foreach ($filters as $field => $value) {
            $qb->orWhere("e.$field = :$field")->setParameter($field, $value);
        }
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
     * @param type $entity The entity class name
     * @param array $fulltextColumns The array of columns that have fulltext index
     * @param string $searchterm The term to search for
     * @param array $filters Optional filter conditions (added as WHERE key = value)
     * @return array The search results
     */
    public function fulltextSearch($entity, Array $fulltextColumns,
                                   string $searchterm, Array $filters = []): QueryBuilder
    {
        $commaDelimitedColumns = join(", ", array_map(function ($item) {
                return "e.$item";
            }, $fulltextColumns));
        $repo = $this->em->getRepository($entity);
        /** @var QueryBuilder $qb */
        $qb = $repo->createQueryBuilder('e')
            ->addSelect("MATCH_AGAINST ({$commaDelimitedColumns}, :searchterm 'IN BOOLEAN MODE') as HIDDEN score")
            ->add('where', "MATCH_AGAINST({$commaDelimitedColumns}, :searchterm  'IN BOOLEAN MODE') > 0")
            ->setParameter('searchterm', $searchterm)
            ->orderBy('score', 'desc');
        foreach ($filters as $field => $value) {
            $qb->andWhere("e.{$field} = :{$field}_value")
                ->setParameter("{$field}_value", $value);
        }
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
    public function getPrevious(mixed $current, string $sortedBy,
                                array $matcherFields = []): mixed
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
    public function getNext(mixed $current, string $sortedBy,
                            array $matcherFields = []): mixed
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
    public function countPrevious(mixed $current, string $sortedBy,
                                  array $matcherFields = []): int
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
    public function countNext(mixed $current, string $sortedBy,
                              array $matcherFields = []): int
    {
        $qb = $this->previousNextQueryBuilder($current, $sortedBy, $matcherFields, 'next');
        return $qb->select('COUNT(1)')->getQuery()->getSingleScalarResult();
    }

    protected function previousNextQueryBuilder(mixed $current,
                                                string $sortedBy,
                                                array $matcherFields = [],
                                                string $prevOrNext = 'next'): QueryBuilder
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