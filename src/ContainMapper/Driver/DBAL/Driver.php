<?php

namespace ContainMapper\Driver\DBAL;

use Contain\Entity\EntityInterface;
use Contain\Entity\Property\Type;
use Contain\Entity\Property\Type\EntityType;
use Contain\Entity\Property\Type\ListEntityType;
use ContainMapper\Driver\AbstractDriver;
use ContainMapper\Driver\ConnectionInterface;
use Doctrine\DBAL\Query\QueryBuilder;
use ContainMapper\Exception;
use ContainMapper\Mapper;

class Driver extends AbstractDriver {
    protected $table;
    protected $hydrationStyle;

    const HYDRATION_STYLE_UNDERSCORE_TO_CAMELCASE = 1;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;

    /**
     * @var \Doctrine\DBAL\Query\QueryBuilder
     */
    protected $qb;

    /**
     * @var int
     */
    protected $qbColNum = 0;

    public function __construct($connection)
    {
        $this->db = $connection;
        $this->hydrationStyle = self::HYDRATION_STYLE_UNDERSCORE_TO_CAMELCASE;
    }
    /**
     * Converts a result row into something a new entity can consume via hydration.
     *
     * @param   array                   Data
     * @return  array
     */
    public function convertResultRow(array $data)
    {
        foreach ($data as $property => $value) {
            $oldProperty = $property;

            switch ($this->hydrationStyle) {
                case self::HYDRATION_STYLE_UNDERSCORE_TO_CAMELCASE:
                    $property = $this->underscoreToCamelCase($property);
                    break;
                default:
                    // already good
                    break;
            }

            if (strcasecmp($oldProperty, $property)) {
                unset($data[$oldProperty]);
                $data[$property] = $value;
            }
        }

        return $data;
    }

    /**
     * @param int $hydrationStyle
     */
    public function setHydrationStyle($hydrationStyle)
    {
        $this->hydrationStyle = $hydrationStyle;
    }

    /**
     * @param mixed $table
     */
    public function setTable($modelName)
    {
        $this->table = self::camelcaseToUnderscore($modelName);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTable()
    {
        return $this->table;
    }

    public function resetQueryBuilder()
    {
        $this->qb = null;

        $this->qbColNum = 0;
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        if ($this->qb == null) {
            $this->qb = $this->db->createQueryBuilder();
            $this->qb->select('t.*')
                ->from($this->table,'t');

            $this->qbColNum = 0;
        }

        return $this->qb;
    }

    /**
     * Rewrites the export() output of an entity into an array
     * for the DBAL connection.
     *
     * @param EntityInterface $entity
     *
     * @return array
     */
    public function getInsertCriteria(EntityInterface $entity)
    {
        $properties      = $entity->properties();
        $data            = $entity->export();
        $return          = array();

        foreach ($properties as $name) {
            $type  = $entity->type($name);
            $value = $data[$name];

            if ($type instanceof EntityType) {
                continue;
            } elseif ($type instanceof Type\ListType) {
                continue;
            } else {
                $return[$this->convertPropertyToColumn($name)] = $value;
            }
        }

        return $return;
    }

    /**
     * Rewrites the dirty() output from an entity into something
     * TableGateway can use in an update statement.
     *
     * @param EntityInterface $entity Reference entity
     *
     * @return array
     */
    public function getUpdateCriteria(EntityInterface $entity)
    {
        $dirty = array();
        $return = array();

        $primary = array_keys($entity->primary());
        if ($properties = $entity->dirty()) {

            $dirty = $entity->export($properties);
        }

        foreach ($dirty as $property => $value) {
            if (in_array($property, $primary) && empty($value)) {
                continue;
            }

            $type = $entity->type($property);

            if ($type instanceof EntityType) {
                continue;
            } elseif ($type instanceof Type\ListType) {
                continue;
            } else {
                $return[$this->convertPropertyToColumn($property)] = $value;
            }
        }

        return $return;
    }

    public function persist(EntityInterface $entity)
    {
        $primary = $entity->primary();

        if (!$entity->isPersisted()) {
            $data = $this->getUpdateCriteria($entity);
            $this->db->insert($this->table, $data);

            if (count($primary) == 1) {
                $column = array_keys($primary);
                $column = array_shift($column);
                $entity->set($column, $this->db->lastInsertId());
            }
        } else {

            if (!$primary) {
                return false;
            }
            $data = $this->getUpdateCriteria($entity);
            if ($data) {
                $this->db->update($this->table, $data, $this->convertPropertyToColumn($primary));
            }
        }

        return $this;
    }


    public function hydrate(EntityInterface $entity, $values)
    {
        if ($this->hydrationStyle == self::HYDRATION_STYLE_UNDERSCORE_TO_CAMELCASE) {
            foreach ($values as $prop => $val) {
                $entity->set(self::underscoreToCamelCase($prop), $val);
            }
        }
    }

    public function convertPropertyToColumn($data)
    {
        if ($this->hydrationStyle == self::HYDRATION_STYLE_UNDERSCORE_TO_CAMELCASE) {
            if (is_array($data)) {
                foreach ($data as $prop => $val) {
                    $propNew = self::camelcaseToUnderscore($prop);
                    if ($propNew != $prop) {
                        $data[$propNew] = $val;
                        unset($data[$prop]);
                    }
                }
            } else {
                $data = self::camelcaseToUnderscore($data);
            }
        }

        return $data;
    }

    public static function camelcaseToUnderscore($model)
    {
        if (strcmp(strtolower($model),$model) === 0) {
            return $model;
        }

        $parts = explode('\\', $model);
        $className = $parts[count($parts)-1];

        return strtolower( preg_replace( '/([A-Z])/', '_$1', lcfirst($className) ) );
    }

    public static function underscoreToCamelCase($val)
    {
        $val = str_replace(' ','', ucwords(str_replace(array('_','-'), ' ',$val)));
        $val[0] = strtolower($val[0]);

        return $val;
    }


    public function findOne($criteria = null)
    {
        $qb = $this->getQueryBuilder()
                   ->setMaxResults(1);

        $colNum = 0;
        foreach ($criteria as $column => $required) {
            $qb->andWhere($column . ' = ?')
               ->setParameter($colNum,$required);
            $colNum++;
        }

        $stmt = $qb->execute();

        $this->resetQueryBuilder();

        return $stmt->fetch();
    }

    public function delete(EntityInterface $entity)
    {
        if (!$primary = $entity->primary()) {
            return $this;
        }

        if ($this->hydrationStyle == self::HYDRATION_STYLE_UNDERSCORE_TO_CAMELCASE) {
            foreach ($primary as $col => $id) {
                unset($primary[$col]);
                $primary[self::camelcaseToUnderscore($col)] = $id;
            }
        }

        $this->db->delete($this->table, $primary);

        return $this;
    }

    public function andWhere($column,$value) {
        $qb = $this->getQueryBuilder();

        $qb->andWhere($column . ' = ?');
        $qb->setParameter($this->qbColNum, $value);
        $this->qbColNum++;
    }

    public function orWhere($column,$value) {

        $qb = $this->getQueryBuilder();

        $qb->andWhere($column . ' = ?');
        $qb->setParameter($this->qbColNum, $value);
        $this->qbColNum++;
    }

    public function find($criteria = null) {
        $qb = $this->getQueryBuilder();

        foreach ($criteria as $column => $required) {
            $this->andWhere($column,$required);
        }

        $stmt = $qb->execute();
        $this->resetQueryBuilder();

        return new Cursor($stmt, $this);
    }

    public function findBy($criteria = null) {
        $this->getQueryBuilder()
             ->setMaxResults(1);

        return $this->find($criteria);
    }

    public function getConnection()
    {
        return $this->db;
    }

}