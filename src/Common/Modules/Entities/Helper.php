<?php

namespace Common\Modules\Entities;

/**
 * Class Helper
 * @package Common\Modules\Entities
 */
class Helper
{
    /**
     * @param $var
     * @return bool
     */
    public static function filterNotNull($var)
    {
        return null !== $var;
    }

    /**
     * @param $var
     * @return bool
     */
    public static function filterValuable($var)
    {
        return is_numeric($var) || is_string($var) || is_bool($var);
    }

    /**
     * @param $entities
     * @return array
     */
    public static function convertToArray($entities)
    {
        $result = array();

        foreach ($entities as $entitiesKey => $entity) {
            if (is_array($entity)) {
                self::convertToArray($entity);
            }
            if ($entity instanceof Entity) {
                $result[$entitiesKey] = $entity->toArray();
            }
        };

        return $result;
    }

    /**
     * @param $var
     * @return string
     */
    public static function toSnakeCase($var) {
        $var = preg_replace(
            '/(?!^)[[:upper:]][[:lower:]]/',
            '$0',
            preg_replace('/(?!^)[[:upper:]]+/', '_' . '$0', $var)
        );
        return strtolower($var);
    }
}
