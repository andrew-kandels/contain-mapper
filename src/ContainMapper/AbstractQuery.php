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

namespace ContainMapper;

use InvalidArgumentException;
use Traversable;

/**
 * Fetch/Query Unit of Work
 *
 * @category    akandels
 * @package     contain
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 */
abstract class AbstractQuery
{
    /**
     * @var integer
     */
    protected $limit;

    /**
     * @var integer
     */
    protected $defaultLimit;

    /**
     * @var integer
     */
    protected $skip;

    /**
     * @var array
     */
    protected $sort;

    /**
     * @var array
     */
    protected $timeout;

    /**
     * @var array
     */
    protected $options = array();

    /**
     * @var array
     */
    protected $properties = array();

    /**
     * Limits the results of any find/search operation from the mapper
     * to a maximum count./
     *
     * @param   integer             Number of Hydrated Entities
     * @return self
     */
    public function limit($num)
    {
        $this->limit = $num;
        return $this;
    }

    /**
     * Adds a limit (if not set).
     *
     * @param   integer             Number of Hydrated Entities
     * @return self
     */
    public function setDefaultLimit($num)
    {
        if (!$this->limit) {
            $this->limit($num);
        }
        return $this;
    }

    /**
     * Returns the maximum number of results to hydrate in a find/search
     * call.
     *
     * @return  integer             Number of hydrated entities (maximum)
     */
    public function getLimit()
    {
        return $this->limit !== null ? $this->limit : $this->defaultLimit;
    }

    /**
     * Skips a number of entities in any find/search operation.
     *
     * @param    integer             Number of entities to skip.
     * @return self
     */
    public function skip($num)
    {
        $this->skip = $num;
        return $this;
    }

    /**
     * Returns the number of results to skip when searching/finding.
     *
     * @return  integer             Number of entities to skip
     */
    public function getSkip()
    {
        return $this->skip;
    }

    /**
     * Sets the query timeout value.
     *
     * @param   integer
     * @return self
     */
    public function timeout($seconds)
    {
        $this->timeout = (int) $seconds;
        return $this;
    }

    /**
     * Sets the query timeout value.
     *
     * @param   integer
     * @return self
     */
    public function setTimeout($seconds)
    {
        $this->timeout($seconds);
        return $this;
    }

    /**
     * Gets the query timeout value.
     *
     * @return  integer
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Configures how the results should be sorted in the next
     * find/search query.
     *
     * @param array $criteria Sort criteria
     *
     * @return self
     */
    public function sort(array $criteria)
    {
        $this->sort = $criteria;
        return $this;
    }

    /**
     * Adds a sort (if not set).
     *
     * @param array $criteria Sort criteria
     *
     * @return self
     */
    public function setDefaultSort(array $criteria)
    {
        if (!$this->sort) {
            $this->sort($criteria);
        }

        return $this;
    }

    /**
     * Returns how the results should be sorted in the next
     * find/search query.
     *
     * @return array
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * Sets a mapper level option that will be passed to the next
     * mapper method invokation.
     *
     * @param string $name  Option Name
     * @param mixed  $value Option Value
     *
     * @return self
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;
        return $this;
    }

    /**
     * Sets mapper level options that will be passed to the next
     * mapper method invokation.
     *
     * @param array|Traversable $options Option Name
     *
     * @return self
     */
    public function setOptions($options)
    {
        if (!is_array($options) && !$options instanceof Traversable) {
            throw new InvalidArgumentException('$options must be an array or an instance '
                . 'of Traversable.'
            );
        }

        foreach ($options as $name => $value) {
            $this->setOption($name, $value);
        }

        return $this;
    }

    /**
     * Pulls the mapper level options out of the stack in preparation
     * for a mapper method invokation and then clears the stack for the
     * next.
     *
     * @param array $defaults Options
     *
     * @return array
     */
    public function getOptions(array $defaults = array())
    {
        $result = array();
        foreach ($defaults as $name => $value) {
            $result[$name] = $value;
            if (isset($this->options[$name])) {
                $result[$name] = $this->options[$name];
            }
        }

        return $result;
    }

    /**
     * Resets internal query options and settings.
     *
     * @return self
     */
    public function clear()
    {
        $this->sort    = $this->limit = $this->skip = null;
        $this->options = $this->properties = array();

        return $this;
    }

    /**
     * Selects which properties to query and fill in the next mapper's
     * entity hydration.
     *
     * @param   Traversable|array|string                Properties to Select
     * @return self
     */
    public function properties($properties = array())
    {
        $this->properties = array();

        if (is_array($properties) || $properties instanceof Traversable) {
            foreach ($properties as $property) {
                $this->properties[] = $property;
            }
        } elseif (is_string($properties)) {
            $this->properties[] = $properties;
        } else {
            throw new InvalidArgumentException('$properties should be an array, instance of '
                . 'Traversable or a single property name.'
            );
        }

        return $this;
    }

    /**
     * Returns a list of properties to retrieve when the mapper
     * next hydrates an entity.
     *
     * @return  array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Imports all internal options in a format created by export().
     *
     * @param array $mp Exported values
     *
     * @return self
     */
    public function fromArray(array $mp)
    {
        if (isset($mp['properties'])) {
            $this->properties($mp['properties']);
        }

        if (isset($mp['options'])) {
            $this->setOptions($mp['options']);
        }

        if (isset($mp['limit'])) {
            $this->setLimit($mp['limit']);
        }

        if (isset($mp['skip'])) {
            $this->setSkip($mp['skip']);
        }

        if (isset($mp['sort'])) {
            $this->setSort($mp['sort']);
        }

        if (isset($mp['timeout'])) {
            $this->setTimeout($mp['timeout']);
        }

        return $this;
    }

    /**
     * Exports all internal options. Primarily used for debugging and for
     * injecting one query objects parameters into another.
     *
     * @return  array
     */
    public function export()
    {
        return array(
            'properties' => $this->getProperties(),
            'sort' => $this->getSort(),
            'limit' => $this->getLimit(),
            'skip' => $this->getSkip(),
            'options' => $this->getOptions(),
            'timeout' => $this->getTimeout(),
        );
    }
}
