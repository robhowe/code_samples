<?php
/**
 * fdvegan_person_collection.php
 *
 * Implementation of Person Collection class for module fdvegan.
 * Stores a collection of actors.
 *
 * PHP version 5.6
 *
 * @category   Person
 * @package    fdvegan
 * @author     Rob Howe <rob@robhowe.com>
 * @copyright  2015-2016 Rob Howe
 * @license    This file is proprietary and subject to the terms defined in file LICENSE.txt
 * @version    Bitbucket via git: $Id$
 * @link       http://fivedegreevegan.aprojects.org
 * @since      version 0.1
 */


class fdvegan_PersonCollection extends fdvegan_Collection
{
    protected $_having_tmdbid = NULL;  // Bool flag to determine whether to load persons with no Tmdb info


    public function __construct($options = NULL)
    {
        parent::__construct($options);
    }


    public function setHavingTmdbId($value)
    {
        // Special flag to decide whether to load persons with no Tmdb info.
        $this->_having_tmdbid = (bool)$value;
        return $this;
    }

    public function getHavingTmdbId()
    {
        return $this->_having_tmdbid;
    }


    /**
     * Load all persons (actors) from our database;
     * no filters or validations at all.
     */
    public function loadPersonsArray($start = 0, $limit = 0)
    {
        $sql = <<<__SQL__
SELECT `person_id`, `tmdbid`, `full_name`, `biography`, `created`, `updated`, `synced` 
  FROM {fdvegan_person} 
__SQL__;
        if (!empty($this->_having_tmdbid)) {
            $sql .= <<<__SQL__
 WHERE {fdvegan_person}.`tmdbid` IS NOT NULL 
__SQL__;
        }
        $sql .= <<<__SQL__
 ORDER BY `last_name` ASC, `first_name` ASC, `middle_name` ASC 
__SQL__;
        if (($start > 0) || ($limit > 0)) {
            $sql .= <<<__SQL__
 LIMIT {$start}, {$limit}
__SQL__;
        }
        $result = db_query($sql);
        foreach ($result as $row) {
            $options = array('PersonId'        => $row->person_id,
                             'TmdbId'          => $row->tmdbid,
                             'FullName'        => $row->full_name,
                             'Biography'       => $row->biography,
                             'Created'         => $row->created,
                             'Updated'         => $row->updated,
                             'Synced'          => $row->synced,
                            );
            $this->_items[] = new fdvegan_Person($options);
        }

        return $this->getItems();
    }


    /**
     * Retrieve all persons from our database, but only return minimal data for them.
     *
     * This function is optimized for use by fdvegan.module::fdvegan_actor_form() so the
     *  select-dropdown is generated quickly.  The data here is stored via variable_set()
     *  so it doesn't have to be regenerated constantly.
     *
     * @return array  An assoc array of PersonId => FullName for all persons in the DB.
     */
    public function getMinPersonsArray()
    {
// @TODO need to add an isStale() check to this eventually!
// @TODO this is deprecated in Drupal 8, and should be done differently (per page, not site-wide) anyway.
        $this->_items = variable_get('fdvegan_min_persons_array', NULL);
        if (empty($this->_items)) {
            $sql = <<<__SQL__
SELECT {fdvegan_person}.`person_id`, {fdvegan_person}.`full_name` 
  FROM {fdvegan_person} 
 WHERE {fdvegan_person}.`tmdbid` IS NOT NULL 
 ORDER BY {fdvegan_person}.`last_name` ASC, {fdvegan_person}.`first_name` ASC, {fdvegan_person}.`middle_name` ASC
__SQL__;
            $result = db_query($sql);
            $this->_items[''] = '';
            foreach ($result as $row) {
                $this->_items[$row->person_id] = $row->full_name;
            }
            variable_set('fdvegan_min_persons_array', $this->_items);
        }
        return $this->getItems();
    }



    //////////////////////////////



}

