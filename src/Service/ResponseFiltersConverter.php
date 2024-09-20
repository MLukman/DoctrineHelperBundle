<?php

namespace MLukman\DoctrineHelperBundle\Service;

use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use MLukman\DoctrineHelperBundle\DTO\ResponseFilters;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * This ValueResolver will parse the JSON specified in the query string field with the same name as
 * the controller router method's function parameter.
 * It will then create and populate an instance of ResponseFilters.
 */
final class ResponseFiltersConverter implements ValueResolverInterface
{
    CONST OPS = [
        '=' => Comparison::EQ,
        '!=' => Comparison::NEQ,
        '>' => Comparison::GT,
        '>=' => Comparison::GTE,
        '<' => Comparison::LT,
        '<=' => Comparison::LTE,
        'IN' => Comparison::IN,
        'NOT IN' => Comparison::NIN,
    ];

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();
        $name = $argument->getName();

        if (!is_a($type, ResponseFilters::class, true)) {
            return [];
        }

        $queries = $request->query->all();
        $filters = $queries[$name] ?? null;
        if (!empty($filters) && !is_array($filters)) {
            $filters = \json_decode($filters, true);
        }
        return [new ResponseFilters(
                    self::makeCriteriaFromArray(is_array($filters) ? $filters : [])
        )];
    }

    public static function makeCriteriaFromArray(array $criterias): ?Criteria
    {
        $criteria = Criteria::create();
        foreach ($criterias as $key => $crit) {
            if (is_null($crit)) {
                $criteria->andWhere(Criteria::expr()->isNull($key));
            } elseif (is_string($crit) || is_numeric($crit) || is_bool($crit)) {
                $criteria->andWhere(Criteria::expr()->eq($key, static::makeValue($crit)));
            } elseif (is_array($crit) && count($crit) > 1) {
                if ($crit[0] == "BETWEEN" && count($crit) >= 3) {
                    $criteria->andWhere(new Comparison($key, Comparison::GTE, static::makeValue($crit[1])));
                    $criteria->andWhere(new Comparison($key, Comparison::LTE, static::makeValue($crit[2])));
                } elseif (isset(static::OPS[$crit[0]])) {
                    $criteria->andWhere(new Comparison($key, static::OPS[$crit[0]], static::makeValue($crit[1])));
                }
            }
        }
        return $criteria;
    }

    public static function makeValue(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[[:digit:]]{4}-[[:digit:]]{2}-[[:digit:]]{2}/', $value) && ($date = new DateTime($value))) {
            return $date;
        }
        return $value;
    }
}
