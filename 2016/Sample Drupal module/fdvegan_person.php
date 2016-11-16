<?php
/**
 * fdvegan_person.php
 *
 * Implementation of Person class for module fdvegan.
 * Stores all info related to a single actor.
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


class fdvegan_Person extends fdvegan_BaseClass
{
    protected $_person_id         = NULL;
    protected $_tmdbid            = NULL;
    protected $_full_name         = NULL;


    // other person-data code snipped...


    protected $_tags              = NULL;  // all tags applied to the person
    protected $_quotes            = NULL;  // all quotes from the person
    protected $_credits           = NULL;  // combined movie & TV credits
    protected $_person_images     = NULL;  // all images of all sizes of type 'person'


    public function __construct($options = NULL)
    {
        parent::__construct($options);

        if (!empty($options['PersonId'])) {
            $this->_loadPersonByPersonId();
        } else {
            $this->_loadPersonByFullName();
        }
    }


    public function setPersonId($value, $overwrite_even_with_empty=TRUE)
    {
        if ($overwrite_even_with_empty || !empty($value)) {
            $this->_person_id = (int)$value;
        }
        return $this;
    }

    public function getPersonId()
    {
        return $this->_person_id;
    }


    public function setTmdbId($value, $overwrite_even_with_empty=TRUE)
    {
        if ($overwrite_even_with_empty || !empty($value)) {
            $this->_tmdbid = (int)$value;
        }
        return $this;
    }

    public function getTmdbId()
    {
        return $this->_tmdbid;
    }


    public function setFullName($value, $overwrite_even_with_empty=TRUE)
    {
        if ($overwrite_even_with_empty || !empty($value)) {
            $this->_full_name = substr($value, 0, 254);
        }
        return $this;
    }

    public function getFullName()
    {
        return $this->_full_name;
    }


    public function getTmdbInfoUrl()
    {
        return fdvegan_Tmdb::getTmdbPersonInfoUrl($this->getTmdbId());
    }


    public function setQuotes($value)
    {
        $this->_quotes = (object)$value;
        return $this;
    }

    public function getQuotes()
    {
        if ($this->_quotes == NULL) {
            // Lazy-load from our DB
            $options = array('Person' => $this,
                            );
            $this->_quotes = new fdvegan_QuoteCollection($options);
        }
        return $this->_quotes;
    }

    public function getQuoteText()
    {
        $ret_val = '';
        if ($this->getQuotes()->count()) {
            $ret_val = $this->getQuotes()[0]->getQuote();
        }
        return $ret_val;
    }


    public function setCredits($value)
    {
        $this->_credits = (object)$value;
        return $this;
    }

   public function getCredits()
    {
        if ($this->_credits == NULL) {
            // Lazy-load from our DB
            $options = array('PersonId'        => $this->getPersonId(),
                             'TmdbId'          => $this->getTmdbId(),
                             'RefreshFromTmdb' => $this->getRefreshFromTmdb(),
                            );
            $this->_credits = new fdvegan_CreditCollection($options);
        }
        return $this->_credits;
    }

    public function getNumCredits()
    {
        return count($this->getCredits());
    }


    public function setPersonImages($value)
    {
        $this->_person_images = (object)$value;
        return $this;
    }

    public function getPersonImages()
    {
        if ($this->_person_images == NULL) {
            // Lazy-load from our DB
            $options = array('Person'          => $this,
                             'MediaType'       => 'person',
                             'RefreshFromTmdb' => $this->getRefreshFromTmdb(),
                             'ScrapeFromTmdb'  => $this->getScrapeFromTmdb(),
                            );
            $this->_person_images = new fdvegan_MediaSizeCollection($options);
        }
        return $this->_person_images;
    }


	/**
	 * Get the best image URL for this person.
	 *
	 * @param string $size    Valid values are: "s,m,l,o" or: 'small', 'medium', 'large', or 'original'.
     * @return string  URL or ''
     *                 e.g.: "http://fivedegreevegan.aprojects.org/pictures/tmdb/person/s/123-1.jpg"
	 */
    public function getImagePath($media_size = 'medium', $orUseDefault = true)
    {
        $size = substr($media_size, 0, 1);
        return $this->getPersonImages()[$size][0]->getPath();
    }


    public function storePerson()
    {
        if ($this->getPersonId()) {  // Must already exist in our DB, so is an update.
            $sql = <<<__SQL__
UPDATE {fdvegan_person} SET 
       `tmdbid` = :tmdbid, 
       `full_name` = :full_name, 
       `updated` = now(), 
       `synced` = :synced 
 WHERE `person_id` = :person_id
__SQL__;
            try {
                db_query($sql, array(':person_id'       => $this->getPersonId(),
                                     ':tmdbid'          => $this->getTmdbId(),
                                     ':full_name'       => $this->getFullName(),
                                     ':synced'          => $this->getSynced(),
                                    ));
            }
            catch (Exception $e) {
                throw new FDVegan_PDOException("Caught exception: {$e->getMessage()} while UPDATing person: ". print_r($this,1), $e->getCode(), $e, 'LOG_ERR');
            }
            fdvegan_Content::syslog('LOG_DEBUG', 'Updated person in our DB: person_id='. $this->_person_id .', full_name="'. $this->_full_name . '"');

        } else {  // Must be a new person to our DB, so is an insert.

            try {
                if (empty($this->getCreated())) {
                    $this->setCreated(date('Y-m-d G:i:s'));
                }
                $this->_person_id = db_insert('fdvegan_person')
                ->fields(array(
                  'tmdbid'            => $this->getTmdbId(),
                  'full_name'         => $this->getFullName(),
                  'created'           => $this->getCreated(),
                  'synced'            => $this->getSynced(),
                ))
                ->execute();
            }
            catch (Exception $e) {
                throw new FDVegan_PDOException("Caught exception: {$e->getMessage()} while INSERTing person: ". print_r($this,1), $e->getCode(), $e, 'LOG_ERR');
            }
            fdvegan_Content::syslog('LOG_DEBUG', "Inserted new person into our DB: person_id={$this->getPersonId()}, full_name=\"{$this->getFullName()}\".");
        }

        return $this->getPersonId();
    }


    public function loadPersonFromTmdbById()
    {
        if (empty($this->getTmdbId())) {
            fdvegan_Content::syslog('LOG_INFO', "loadPersonFromTmdbById({$this->getPersonId()}) TmdbId unknown for person.");
            return FALSE;
        }

        if ($this->isStale()) {
            fdvegan_Content::syslog('LOG_DEBUG', "PersonId={$this->getPersonId()} TmdbId={$this->getTmdbId()} data stale, so reloading, updated={$this->getUpdated()}.");
            $tmdb_api = new fdvegan_Tmdb();
            $tmdb_person = $tmdb_api->getPerson($this->getTmdbId());
            $this->setTmdbData($tmdb_person);
            if (!empty($this->getTmdbData())) {
                $this->setFullName($this->_tmdb_data['name'], FALSE);  // TMDb often doesn't return some fields, so don't set them if empty
                $this->setUpdated(date('Y-m-d G:i:s'));
                $this->setSynced(date('Y-m-d G:i:s'));
                // Note - FDV stores actor "adult-rated" as a tag, not an explicit person field,
                //        so if you ever want to implement storing the TMDb "adult" field returned here,
                //        it would be a separate function call to fdvegan_person_tag::($this->_tmdb_data['adult']).
            }
            $this->storePerson();  // even if just for the `updated` & `synced` fields
        }

        if ($this->getRefreshFromTmdb()) {
            // Next, load this person's movie credits from TMDb, and update our DB.
            $this->getCredits();
        }
        if ($this->getScrapeFromTmdb()) {
            $this->getPersonImages();
        }

        return $this->getPersonId();
    }



    //////////////////////////////



    private function _processLoadPersonResult($result)
    {
        if ($result->rowCount() != 1) {
            throw new FDVegan_NotFoundException("person_id={$this->getPersonId()} not found");
        }

        foreach ($result as $row) {
            $this->setPersonId($row->person_id);
            $this->setTmdbId($row->tmdbid);
            $this->setFullName($row->full_name);
            $this->setCreated($row->created);
            $this->setUpdated($row->updated);
            $this->setSynced($row->synced);
        }

        if ($this->getRefreshFromTmdb()) {
            $this->loadPersonFromTmdbById();
        }

        return $this->getPersonId();
    }


    private function _loadPersonByPersonId()
    {
        $sql = <<<__SQL__
SELECT `person_id`, `tmdbid`, `full_name`, `created`, `updated`, `synced` 
  FROM {fdvegan_person} 
 WHERE `person_id` = :person_id
__SQL__;
        $result = db_query($sql, array(':person_id' => $this->getPersonId()));

        return $this->_processLoadPersonResult($result);
    }


    private function _loadPersonByFullName()
    {
        $sql = <<<__SQL__
SELECT `person_id`, `tmdbid`, `full_name`, `created`, `updated`, `synced` 
  FROM {fdvegan_person} 
 WHERE `full_name` = :full_name 
 LIMIT 1
__SQL__;
        $result = db_query($sql, array(':full_name' => $this->getFullName()));

        return $this->_processLoadPersonResult($result);
    }


}

