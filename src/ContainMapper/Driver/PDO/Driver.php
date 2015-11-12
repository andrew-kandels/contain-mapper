<?php
/**
 * Contain Project
 *
 * This source file is subject to the BSD license bundled with
 * this package in the LICENSE.txt file. It is also available
 * on the world-wide-web at http://www.opensource.org/licenses/bsd-license.php.
 * If you are unable to receive a copy of the license or have
 * questions concerning the terms, please send an email to
 * me@andrewkandels.com.
 *
 * @category    akandels
 * @package     contain
 * @author      Andrew Kandels (me@andrewkandels.com)
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link        http://andrewkandels.com/contain
 */

namespace ContainMapper\Driver\PDO;

use Contain\Entity\EntityInterface;
use ContainMapper\Exception;
use ContainMapper;

/**
 * PDO Data Source Driver
 *
 * Quick reference:
 *      property($name):        `my_table`.`my_property`
 *      alias($name):           myTableMyProperty
 *      param($name, $value):   :myTableMyProperty exec($params['myTableMyProperty'] = $value)
 *      forSelect($name):       `my_table`.`my_property` AS 'myTableByProperty'
 *      map($name, $value):     `my_table`.`my_property` = :MyTableMyParam exec($params['myTableMyProperty'] = $value)
 *
 * @category    akandels
 * @package     contain
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 */
class Driver extends ContainMapper\Driver\AbstractDriver
{
    /**
     * @const string
     */
    const NAMING_UNDERSCORE = 'underscore';
    const NAMING_CAMEL      = 'camel';

    /**
     * @var string
     */
    protected $naming = 'underscore';

    /**
     * @var array
     */
    protected $params;

    /**
     * @var AbstractDefinition
     */
    protected $definition;

    /**
     * @var string
     */
    protected $table;

    /**
     * {@inheritDoc}
     */
    public function persist(EntityInterface $entity)
    {
        if ($entity->isPersisted()) {
            $this->update($entity);
        } else {
            $this->insert($entity);
        }

        $entity->persisted(true)->clean();

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function findOne($criteria = null)
    {
        $this->params = array();

        $stmt = $this->select(sprintf('SELECT * FROM `%s` %s',
            $this->getTable(),
            $this->criteria($criteria)
        ));

        if ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            return $this->convertResultRow($row);
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function find($criteria = null)
    {
        $this->params = array();

        $stmt = $this->select(sprintf('SELECT * FROM `%s` %s',
            $this->getTable(),
            $this->criteria($criteria)
        ));

        return new Cursor($stmt, $this);
    }

    /**
     * Sets the property naming scheme for the database.
     *
     * @return  self
     */
    public function setNamingScheme($naming)
    {
        $this->naming = $naming;
        return $this;
    }

    /**
     * Gets the naming scheme for the database.
     *
     * @return  string
     */
    public function getNamingScheme()
    {
        return $this->naming;
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

            switch ($this->getNamingScheme()) {
                case self::NAMING_UNDERSCORE:
                    $property = $this->convertUnderscoreToCamel($property);
                    break;

                case self::NAMING_CAMEL:
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
     * Writes out a SQL SET for a property/value pair.
     *
     * @param   string              Name
     * @param   mixed               Value
     * @return  string
     */
    protected function map($name, $value)
    {
        return sprintf('%s = %s',
            $this->property($name),
            $this->param($name, $value)
        );
    }

    /**
     * Executes a query (stored if there are parameters) and returns the PDOStatement object.
     *
     * @param   string              SQL
     * @return  PDOStatement
     */
    protected function select($sql)
    {
        $db = $this
            ->getConnection()
            ->getConnection();

        if ($this->sort) {
            $order = array();

            foreach ($this->sort as $key => $dir) {
                if (!in_array($dir = strtoupper($dir), array('ASC', 'DESC'))) {
                    throw new \InvalidArgumentException('$dir of "' . $dir . '" is not a valid sort direction');
                }

                $order[] = sprintf('%s %s', $this->property($key), $dir);
            }

            $sql .= ' ORDER BY ' . implode(', ', $order);
        }

        if ($this->limit) {
            if ($this->skip) {
                $sql .= sprintf(' LIMIT %d, %d', $this->skip, $this->limit);
            } else {
                $sql .= sprintf(' LIMIT %d', $this->limit);
            }
        }

        if ($this->params) {
            $stmt = $db->prepare($sql);
            try {
                $stmt->execute($this->params);
            } catch (\PDOException $e) {
                throw new \RuntimeException(sprintf('(%s) %s: Query: %s with parameters: %s',
                    get_class($e),
                    $e->getMessage(),
                    $sql,
                    json_encode($this->params)
                ));
            }

            return $stmt;
        }

        $stmt = $db->query($sql);

        return $stmt;
    }

    /**
     * Writes out a select for a property.
     *
     * @param   string              Name
     * @return  string
     */
    protected function forSelect($name)
    {
        return sprintf("%s AS '%s'",
            $this->property($name),
            $this->alias($name)
        );
    }

    /**
     * Returns a reference to a property on a table.
     *
     * @param   string              Name
     * @return  string
     */
    protected function property($name)
    {
        $table = $this->getTable();

        switch ($this->getNamingScheme()) {
            case self::NAMING_UNDERSCORE:
                return sprintf('`%s`.`%s`', $table, $this->convertCamelToUnderscore($name));
                break;

            case self::NAMING_CAMEL:
            default:
                return sprintf('`%s`.`%s`', $table, $name);
                break;
        }
    }

    /**
     * Sets a parameter and returns the reference for SQL insertion.
     *
     * @param   string              Name
     * @param   string              Value
     * @return  string              Placeholder for SQL insertion
     */
    protected function param($name, $value)
    {
        if (!is_scalar($value)) {
            $value = (string) $value;
        }

        $alias = $this->alias($name);
        $this->params[$alias] = $value;
        return (':' . $alias);
    }

    /**
     * Returns an alias name for a property on the table.
     *
     * @param   string              Name
     * @return  string              Alias
     */
    protected function alias($name)
    {
        $table = $this->getTable();

        switch ($this->getNamingScheme()) {
            case self::NAMING_UNDERSCORE:
                $property = $this->convertCamelToUnderscore($name);
                return $this->convertUnderscoreToCamel($table . '_' . $property);
                break;

            case self::NAMING_CAMEL:
            default:
                return ($table . ucfirst($name));
                break;
        }
    }

    /**
     * Converts properties from underscore notation to camel-case.
     *
     * @param   string
     * @return  string
     */
    protected function convertUnderscoreToCamel($key)
    {
        $parts = preg_split('/_/', $key);

        for ($i = 1; $i < count($parts); $i++) {
            $parts[$i] = strtoupper(substr($parts[$i], 0, 1)) . substr($parts[$i], 1);
        }

        return implode('', $parts);
    }

    /**
     * Converts properties from camel case to underscore notation.
     *
     * @param   string
     * @return  string
     */
    protected function convertCamelToUnderscore($key)
    {
        return strtolower(preg_replace('/[A-Z]+/', '_$0', $key));
    }

    /**
     * Returns the active query parameters.
     *
     * @return  array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Sets the query parameters.
     *
     * @param   array $params
     * @return  self
     */
    public function setParams(array $params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Converts array-based criteria to a WHERE clause.
     *
     * @param   array                   Criteria
     * @return  string
     */
    public function criteria(array $criteria = array())
    {
        if (!$criteria) {
            return '';
        }

        $allowedTypes = array('raw', 'value');
        $where        = array();

        foreach ($criteria as $key => $value) {
            if ($value instanceof Closure) {
                $value = $value();
            }

            if (is_array($value)) {
                if (count($value) < 2) {
                    throw new \InvalidArgumentException('$value of array type in PDO query must include 2 indexes: ($type, $value)');
                }

                list($type, $expr) = $value;

                if ($type == 'raw') {
                    $where[] = sprintf('(%s %s)', $this->property($key), $expr);
                    if (is_array($params = array_slice($value, 2)) && $params) {
                        $this->params = reset($params) + $this->params;
                    }

                    continue;
                }

                if ($type == 'value') {
                    $where[] = sprintf('%s = %s', $this->property($key), $expr);
                    continue;
                }

                throw new \InvalidArgumentException('$value of array type must be of the following allowed types: ' . implode(', ', $allowedTypes));
            }

            $where[] = $this->map($key, $value);
        }

        return sprintf(' WHERE %s', implode(' AND ', $where));
    }

    /**
     * Gets the definition class for an entity in order to identify no_hydrate columns.
     *
     * @param   EntityInterface $entity
     * @return  Contain\Entity\AbstractDefinition
     */
    protected function getDefinition(EntityInterface $entity)
    {
        if ($this->definition) {
            return $this->definition;
        }

        $parts = explode('\\', get_class($entity));

        $className = implode('\\', array_merge(array_slice($parts, 0, -1), array('Definition'), array_slice($parts, -1)));

        if (!class_exists($className)) {
            throw new \InvalidArgumentException(sprintf('Cannot identify definition class from "%s", tried "%s" - does not exist',
                get_class($entity),
                $className
            ));
        }

        return ($this->definition = new $className());
    }

    /**
     * Is a property hydrated?
     *
     * @param   EntityInterface $entity
     * @param   string                          Name
     * @return  boolean
     */
    protected function isHydratedProperty(EntityInterface $entity, $property)
    {
        $definition = $this->getDefinition($entity);
        $property = $definition->getProperty($property);

        return !$property->getOption('no_hydrate');
    }

    /**
     * Updates an entity into a table.
     *
     * @param   EntityInterface $entity
     * @return  self
     */
    protected function update(EntityInterface $entity)
    {
        $this->params = array();

        if (!$primary = $entity->primary()) {
            throw new \InvalidArgumentException('Cannot update $entity, does not have any primary keys defined');
        }

        if (!$dirty = $entity->dirty()) {
            return $this;
        }

        // anything to do?
        if (!$export = $entity->export($dirty)) {
            return $this;
        }

        $sets = array();

        foreach ($export as $key => $value) {
            if (isset($primary[$key])) {
                // @todo this is a hack to quickly fix a cardtronics issue!
                // Removal of the 'clean' method call in hydrate is probable cause
                // Fix by putting back in or using 'reset' instead.
                unset($export[$key]);
                continue;
                throw new \InvalidArgumentException('Cannot update - primary key property changed');
            }

            if (!$this->isHydratedProperty($entity, $key)) {
                continue;
            }

            $sets[] = $this->map($key, $value);
        }

        // nothing to do afterall
        if (!$sets) {
            return $this;
        }

        $sql = sprintf('UPDATE `%s` SET %s%s',
            $this->getTable(),
            implode(', ', $sets),
            $this->criteriaPrimary($entity)
        );

        $stmt = $this
            ->getConnection()
            ->getConnection()
            ->prepare($sql)
            ->execute($this->params);

        return $this;
    }

    /**
     * Returns criteria for the primary keys.
     *
     * @param   EntityInterface $entity
     * @return  string                      SQL for WHERE
     */
    protected function criteriaPrimary(EntityInterface $entity)
    {
        if (!$primary = $entity->primary()) {
            throw new \InvalidArgumentException('Cannot update $entity, does not have any primary keys defined');
        }

        return $this->criteria($primary);
    }

    /**
     * Inserts an entity into a table.
     *
     * @param   EntityInterface $entity
     * @return  self
     */
    protected function insert(EntityInterface $entity)
    {
        $this->params = array();

        $export = $entity->export(null, true);
        $sets   = array();

        foreach ($export as $key => $value) {
            if (!$this->isHydratedProperty($entity, $key) || $value === null) {
                continue;
            }

            $sets[] = $this->map($key, $value);
        }

        // nothing to do afterall
        if (!$sets) {
            return $this;
        }

        $sql = sprintf('INSERT INTO `%s`%s',
            $this->getTable(),
            $sets ? ' SET ' . implode(', ', $sets) : ''
        );

        $db = $this
            ->getConnection()
            ->getConnection();

        $stmt = $db
            ->prepare($sql)
            ->execute($this->params);

        if ($id = $db->lastInsertId()) {
            $entity->setExtendedProperty('_id', $db->lastInsertId());

            if (($primary = $entity->primary()) && count($primary) == 1) {
                $entity->set(implode('', array_slice(array_keys($primary), 0, 1)), $entity->getExtendedProperty('_id'));
            }
        } elseif (($primary = $entity->primary()) && count($primary) == 1) {
            $entity->setExtendedProperty('_id', array_shift($primary));
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(EntityInterface $entity)
    {
        $this->params = array();

        if (!$primary = $entity->primary()) {
            throw new \InvalidArgumentException('Cannot update $entity, does not have any primary keys defined');
        }

        if (!$entity->isPersisted()) {
            throw new \InvalidArgumentException('Cannot delete $entity, is not persisted');
        }

        $sql = sprintf('DELETE FROM `%s`%s',
            $this->getTable(),
            $this->criteriaPrimary($entity)
        );

        $this
            ->getConnection()
            ->getConnection()
            ->prepare($sql)
            ->execute($this->params);

        $entity->persisted(false)->clean()->setExtendedProperty('_id', null);

        return $this;
    }

    /**
     * Sets the PDO database table.
     *
     * @param   string
     * @return  self
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Gets the PDO database table.
     *
     * @return  string
     */
    public function getTable()
    {
        if (!$this->table) {
            throw new \RuntimeException('No $table defined for PDO connection, call setTable first');
        }

        return $this->table;
    }
}
