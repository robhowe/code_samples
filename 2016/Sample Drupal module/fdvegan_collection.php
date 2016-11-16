<?php
/**
 * fdvegan_collection.php
 *
 * Implementation of base collection functionality for module fdvegan.
 *
 * PHP version 5.6
 *
 * @category   Collection
 * @package    fdvegan
 * @author     Rob Howe <rob@robhowe.com>
 * @copyright  2015-2016 Rob Howe
 * @license    This file is proprietary and subject to the terms defined in file LICENSE.txt
 * @version    Bitbucket via git: $Id$
 * @link       http://fivedegreevegan.aprojects.org
 * @since      version 0.1
 */

abstract class fdvegan_Collection extends fdvegan_BaseClass implements ArrayAccess, IteratorAggregate, Countable  // , ToArray
{
    protected $_items        = array();
    protected $_metaOnlyFlag = false;

    private $_meta = array();


    protected function __construct($options = NULL)
    {
        parent::__construct($options);
    }


    public function isMetaOnly()
    {
        return $this->_metaOnlyFlag;
    }

    public function getMetaData()
    {
        return $this->_meta;
    }


    private function assignCheck($value, $type, $paramName)
    {
        return $value;
        
        if ($value === NULL) {
            return $value;
        }

        switch ($type) {
        case 'array':
            if (is_array($value)) {
                return $value;
            }
            break;

        default:
            if ($value instanceof $type) {
                return $value;
            }
            break;
        }

        throw new FDVegan_InvalidArgumentException('Wrong type for $' . "$paramName given in constructor (expected $type, got " . get_class($value) . ")");
    }


    /**
     * The Meta fields parallel the __get() and __set() functions.
     */

    public function getMeta($name)
    {
        if (isset($this->_meta[$name])) {
            return $this->_meta[$name];
        } else {
            return NULL;
        }
    }

    protected function setMetaValues($values, $metaOnlyFlag=false)
    {
        $this->_meta['total'] = count($values);
        if ($metaOnlyFlag) {
            $this->_meta['first'] = $this->_meta['last'] = -1;
            $this->_metaOnlyFlag=true;
        } else {
            $this->_meta['first'] = count($values) ? 0 : -1;
            $this->_meta['last'] = count($values) - 1;
        }
    }

    public function setMeta($name, $value)
    {
        $this->_meta[$name] = $value;
    }
    
    public function issetMeta($name)
    {
        return isset($this->_meta[$name]);
    }

    public function unsetMeta($name)
    {
        if (isset($this->_meta[$name])) {
            unset($this->_meta[$name]);
        }
    }

    public function __get($name) 
    {
    }

    public function __isset($name) 
    {
        return false;
    }


    /**
     * ArrayAccess Interface
     */
    public function getAt($offset)
    {
        return $this->offsetGet($offset);
    }

    public function getItems()
    {
        return $this->_items;
    }

    public function offsetExists($offset)
    {
        return isset($this->_items[$offset]);
    }

    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return $this->_items[$offset];
        }
    }

    public function offsetSet($offset, $value)
    {
        if (empty($offset)) {
            $this->_items[] = $value;
        } else {
            $this->_items[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->_items[$offset]);
    }

    public function sort($_fields_)
    {
        throw new FDVegan_NotImplementedException('Sort not available for ' . __CLASS__);
    }


    /**
     * Truncate this collection after the given index limit.
     * This does NOT delete any DB records or corresponding media files on the filesystem!
     */
    public function truncate($trunc_at)
    {
        array_splice($this->_items, $trunc_at, (count($this->_items) - $trunc_at));
        return $this->_items;
    }


    public function deleteAll()
    {
        $this->_items = array();  // effectively unset()'s all existing _items
        return $this->_items;
    }


    /**
     * Countable Interface
     */
    public function count() 
    {
        return count($this->_items);
    }


    /**
     * IteratorAggregate Interface
     */
    public function getIterator()
    {
        return new ArrayIterator($this->_items);
    }


    /**
     * ToArray
     */
    public function toArray()
    {
        $result = $this->getMetaData();
        if ($this->_metaOnlyFlag) {
            $result['data'] = NULL;
        } else {
            $result['data'] = array();
            if (is_array($this->_items)) {
                foreach ($this->_items as $item) {
                    $result['data'][] = $item->toArray();
                }
            }
        }
        return $result;
    }


    /**
     * This is the externally-available function for updating the items
     * in the collection. It ensures that triggers get fired in a standard
     * way for all Collections.
     * 
     * This shouldn't need to be implemented by child classes.
     * Instead, child classes should implement protected function doUpdate()
     * 
     * @return int  The number of items affected.
     */
    final public function update($PM = NULL)
    {
        $affected = $this->doUpdate();
        return $affected;
    }

    /**
     * This is the class-specific, internal-use-only function for bulk updating
     * the items in the collection.
     * This should *only* be called via update()
     * 
     * @return int  The number of items affected.
     */
    protected function doUpdate()
    {
        $affected = 0;
        foreach ($this->_items as $item) {
            if (is_object($item) && method_exists($item, 'update')) {
                $affected += $item->update();
            }
        }
        return $affected;
    }



}

