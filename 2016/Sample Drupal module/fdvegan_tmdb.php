<?php
/**
 * fdvegan_tmdb.php
 *
 * Implementation of Tmdb class for module fdvegan.
 * Wrapper class for low-level 3rd party TMDb library.
 *
 * Example TMDb API URL:
 *   http://api.themoviedb.org/3/configuration?api_key=[redacted]
 *
 * PHP version 5.6
 *
 * @category   Tmdb
 * @package    fdvegan
 * @author     Rob Howe <rob@robhowe.com>
 * @copyright  2015-2016 Rob Howe
 * @license    This file is proprietary and subject to the terms defined in file LICENSE.txt
 * @version    Bitbucket via git: $Id$
 * @link       http://fivedegreevegan.aprojects.org
 * @since      version 0.8
 * @see        TMDb.php
 */


class fdvegan_Tmdb extends TMDb
{
    const BASE_URL = 'http://www.themoviedb.org/';

	/**
	 * The API-key
	 *
	 * @var string
	 */
    // Private key to the TMDb API.  This is created by setting up
    // an account on http://www.themoviedb.org/
	protected $_apikey = '[redacted]';


	/**
	 * Default constructor
	 *
	 * @param string $apikey			API-key recieved from TMDb
	 * @param string $defaultLang		Default language (ISO 3166-1)
	 * @param boolean $config			Load the TMDb-config
	 * @return void
	 */
	public function __construct($apikey = NULL, $default_lang = 'en', $config = FALSE, $scheme = TMDb::API_SCHEME)
	{
		$this->_apikey = empty($apikey) ? $this->_apikey : (string) $apikey;
		$this->_apischeme = ($scheme == TMDb::API_SCHEME) ? TMDb::API_SCHEME : TMDb::API_SCHEME_SSL;
		$this->setLang($default_lang);

		if($config === TRUE)
		{
			$this->getConfiguration();
		}
	}


    /**
     * Returns the TMDb API base_url from "configuration".
     */
    public static function getTmdbBaseUrl()
    {
// @TODO - make this a once /week cronjob instead of hard-coding?:
        return 'http://image.tmdb.org/t/p/';
    }


    public static function getTmdbPersonInfoUrl($tmdbid)
    {
        $ret_val = '';
        if (!empty($tmdbid)) {
            $ret_val = self::BASE_URL . "person/{$tmdbid}";
        }
        return $ret_val;
    }


    public static function getTmdbMovieInfoUrl($tmdbid)
    {
        $ret_val = '';
        if (!empty($tmdbid)) {
            $ret_val = self::BASE_URL . "movie/{$tmdbid}";
        }
        return $ret_val;
    }


	/**
	 * Map TMDb gender values to the equivalent FDV value.
	 *
	 * @param string $value    Valid values are:  0,1,2.
     * @return string  FDV value.
	 */
    public static function mapTmdbGenderToFDV($value)
    {
        // TMDb uses int 0=unknown,1=female,2=male
        $tmdb_gender_map = array(
            NULL => NULL,
            '0'  => NULL,
            '1'  => 'F',
            '2'  => 'M',
        );
        if (!array_key_exists($value, $tmdb_gender_map)) {
            fdvegan_Content::syslog('LOG_ERR', "mapTmdbGenderToFDV('{$value}') invalid gender");
            throw new FDVegan_InvalidArgumentException("mapTmdbGenderToFDV('{$value}') invalid gender.");
        }
        return $tmdb_gender_map[$value];
    }


	/**
	 * Map standard types to the equivalent TMDb value.
	 *
	 * @param string $type    Valid values are:  'person', 'movie', or 'moviebackdrop'.
     * @return string  TMDb value.
	 */
    public static function mapTmdbImageType($type)
    {
        $tmdb_image_type_map = array(
            'person'        => TMDb::IMAGE_PROFILE,
            'movie'         => TMDb::IMAGE_POSTER,
            'moviebackdrop' => TMDb::IMAGE_BACKDROP,
        );
        if (!array_key_exists($type, $tmdb_image_type_map)) {
            fdvegan_Content::syslog('LOG_ERR', "mapTmdbImageType('{$type}') invalid type");
            throw new FDVegan_InvalidArgumentException("mapTmdbImageType({$type}) invalid type.");
        }
        return $tmdb_image_type_map[$type];
    }


	/**
	 * Map standard sizes to the equivalent TMDb value.
	 *
	 * @param string $type    Valid values are:  'person', 'movie', or 'moviebackdrop'.
	 * @param string $size    Valid values are:  "s,m,l,o" or: 'small', 'medium', 'large', or 'original'.
     * @return string    TMDb value.
	 */
    public static function mapTmdbImageSize($type = 'person', $size = 'medium')
    {
        $tmdb_image_type = fdvegan_tmdb::mapTmdbImageType($type);
        $media_size = substr($size, 0, 1);

        // @TODO - make this a once /week cronjob instead of hard-coding?
        //$tmdb_api = new fdvegan_Tmdb();
        //$available_sizes = $tmdb_api->getAvailableImageSizes($tmdb_image_type);
        $tmdb_image_size_map = array();
        $tmdb_image_size_map['person'] = array(
            's' => 'w45',
            'm' => 'w185',
            'l' => 'h632',
            'o' => 'original'
        );
        $tmdb_image_size_map['movie'] = array(
            's' => 'w92',
            'm' => 'w185',
            'l' => 'w500',
            'o' => 'original'
        );
        $tmdb_image_size_map['moviebackdrop'] = array(
            's' => 'w300',
            'm' => 'w780',
            'l' => 'w1280',
            'o' => 'original'
        );
        if (!array_key_exists($media_size, $tmdb_image_size_map[$type])) {
            fdvegan_Content::syslog('LOG_ERR', "mapTmdbImageSize('{$type}','{$media_size}') invalid size");
            throw new FDVegan_InvalidArgumentException("mapTmdbImageSize({$type},{$media_size}) invalid size.");
        }
        return $tmdb_image_size_map[$type][$media_size];
    }


	/**
	 * Map standard types to the equivalent TMDb value returned from the API.
	 * For some reason, TMDb adds an 's' to the ImageType name returned as an ArrayKey.
	 *
	 * @param string $type    Valid values are:  'person', 'movie', or 'moviebackdrop'.
     * @return string    TMDb result-index value.
	 */
    public static function mapTmdbImageResultType($type)
    {
        $tmdb_image_type_map = array(
            'person'        => TMDb::IMAGE_PROFILE . 's',
            'movie'         => TMDb::IMAGE_POSTER . 's',
            'moviebackdrop' => TMDb::IMAGE_BACKDROP . 's',
        );
        if (!array_key_exists($type, $tmdb_image_type_map)) {
            fdvegan_Content::syslog('LOG_ERR', "mapTmdbImageResultType('{$type}') invalid type");
            throw new FDVegan_InvalidArgumentException("mapTmdbImageResultType({$type}) invalid type.");
        }
        return $tmdb_image_type_map[$type];
    }


	/**
	 * Retrieve all images for a particular movie.
	 * This function exists only to help map fdvegan image types to the TMDb API.
	 *
	 *
	 * @param mixed $id					TMDb-id or IMDB-id
	 * @param mixed $lang				Filter the result with a language (ISO 3166-1) other then default, use FALSE to retrieve results from all languages
	 * @return TMDb result array
	 */
	public function getMoviebackdropImages($id, $lang = NULL)
	{
		return $this->getMovieImages($id, $lang);
	}


	/**
	 * Setter for the TMDB-config.
	 *
	 * $param array $config
	 * @return void
	 */
	public function setConfig($config)
	{
		parent::setConfig($config);
        variable_set('fdvegan_tmdb_config', $config);
	}


	/**
	 * Get configuration from TMDb.
	 *
	 * @return TMDb result array
	 */
	public function getConfiguration()
	{
        if (empty($this->_config)) {
            $this->_config = variable_get('fdvegan_tmdb_config');
        }
        if ($this->isConfigStale() || empty($this->_config)) {
            $config = $this->_makeCall('configuration');
            if (!empty($config)) {
                $this->setConfig($config);
            }
        }
        return $this->_config;
	}


	/**
	 * Get Image URL.
	 *
	 * @param string $filepath			Filepath to image
	 * @param const $imagetype			Image type: TMDb::IMAGE_BACKDROP, TMDb::IMAGE_POSTER, TMDb::IMAGE_PROFILE
	 * @param string $size				Valid size for the image
	 * @return string
	 */
	public function getImageUrl($filepath, $imagetype, $size)
	{
		$config = $this->getConfig();

		if(isset($config['images']))
		{
			$base_url = $config['images']['base_url'];
			$available_sizes = $this->getAvailableImageSizes($imagetype);

			if(in_array($size, $available_sizes))
			{
				return $base_url.$size.$filepath;
			}
			else
			{
				throw new FDVegan_TmdbException('The size "'.$size.'" is not supported by TMDb');
			}
		}
		else
		{
			throw new FDVegan_TmdbException("getImageUrl('{$filepath}','{$imagetype}','{$size}') no config available!", NULL, NULL, 'LOG_CRIT');
		}
	}


    public function isConfigStale()
    {
        $stale_flag = FALSE;
        // @TODO - if/when needed, consider implementing this properly someday.

        return $stale_flag;
    }



    //////////////////////////////



    /**
     * Validate the response from the TMDb API.
     * Throws an exception if the API-usage limit is reached, so everything will abruptly stop rather than continue making calls.
     * @return bool    TRUE if success, FALSE if failure
     */
    private function _validateTmdbResponse($results, $url = NULL) {
        //fdvegan_Content::syslog('LOG_DEBUG', "_validateTmdbResponse() for url=\"{$url}\" returned: " . print_r($results,1));
        $ret_val = FALSE;
        if (!empty($results)) {
            if (array_key_exists('status_code', $results)) {
                $statusCode = $results['status_code'];
                $statusMessage = $results['status_message'];
                if ($statusCode == 25) {
                    throw new FDVegan_TmdbException("_validateTmdbResponse({$statusCode}) TMDb API for url=\"{$url}\" says: \"{$statusMessage}\".", $statusCode, NULL, 'LOG_WARNING');
                }
                fdvegan_Content::syslog('LOG_NOTICE', "_validateTmdbResponse({$statusCode}) TMDb API for url=\"{$url}\" says: \"{$statusMessage}\".");
            } else {
                $ret_val = TRUE;
            }
        }

        return $ret_val;
    }


}

