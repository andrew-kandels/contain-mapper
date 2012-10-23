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

namespace ContainMapper\Driver\File;

use ContainMapper;
use ContainMapper\Exception;
use Contain\Entity\EntityInterface;

/**
 * File-based Data Source
 *
 * @category    akandels
 * @package     contain
 * @copyright   Copyright (c) 2012 Andrew P. Kandels (http://andrewkandels.com)
 * @license     http://www.opensource.org/licenses/bsd-license.php BSD License
 */
class Driver extends ContainMapper\Driver\AbstractDriver
{
    /**
     * Persists an entity in MongoDB.
     *
     * @param   EntityInterface                 Entity to persist
     * @return  $this
     */
    public function persist(EntityInterface $entity)
    {
        $file = $this->getPathToEntity($entity);

        if (!file_put_contents($file, json_encode($entity->export()))) {
            throw new Exception\RuntimeException("Unable to open '$fileName' for writing.");
        }

        return $this;
    }

    /**
     * Builds the path to an entity in the file source.
     *
     * @param   Contain\Entity\EntityInterface|false
     * @return  string
     * @throw   ContainMapper\Exception\InvalidArgumentException
     */
    protected function getPathToEntity(EntityInterface $entity)
    {
        if (!$primary = $entity->primary()) {
            throw new Exception\InvalidArgumentException('$entity does not have primary properties set');
        }

        return sprintf('%s/%s.json',
            $this->connection->getConnection(),
            implode('-', array_values($primary))
        );
    }

    /**
     * Finds an entity by primary key.
     *
     * @param   string                  Primary key value
     * @return  Contain\Entity\EntityInterface|false
     */
    public function findOne($slug = null)
    {
        $file = sprintf('%s/%s.json',
            $this->connection->getConnection(),
            $slug
        );

        if (!file_exists($file)) {
            return false;
        }

        return json_decode(file_get_contents($file));
    }

    /**
     * Finds all entities.
     *
     * @return  Contain\Entity\EntityInterface[]
     */
    public function find($ignore = null)
    {
        $iterator = new \DirectoryIterator($this->connection->getConnection());
        $results  = array();
        $cnt      = 0;

        foreach ($iterator as $item) {
            $cnt++;

            if ($cnt < $this->getSkip()) {
                continue;
            }

            if ($item->isFile() && preg_match('/\.json$/', $item->getFilename())) {
                $entity = json_decode(file_get_contents($item->getPathname()), true);

                if ($entity) {
                    $results[] = $entity;
                }
            }

            if ($this->getLimit() > 0 && $cnt >= $this->getLimit()) {
                break;
            }
        }

        return $results;
    }

    /**
     * Deletes an entity.
     *
     * @param   Contain\Entity\EntityInterface
     * @return  $this
     */
    public function delete(EntityInterface $entity)
    {
        $file = $this->getPathToEntity($entity);

        if (file_exists($file)) {
            unlink($file);
        }

        return $this;
    }
}
