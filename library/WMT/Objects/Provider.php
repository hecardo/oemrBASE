<?php
/**
 * @package   WMT
 * @author    Ron Criswell <ron@medtechsvcs.com>
 * @copyright Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Objects;

/** 
 * Provides a representation of the patient data record. Fields are dymanically
 * processed based on the current database definitions. 
 */
class Provider {
	// Selected elements
	public $id;
	public $username;
	public $authorized;
	public $fname;
	public $mname;
	public $lname;
	public $facility;
	public $facility_id;
	public $active;
	public $specialty;
	public $email;
	public $phone;
	public $calendar;
	
	// Generated values
	public $format_name;
	
	/**
	 * Constructor for the 'provider' class which retrieves the requested 
	 * patient information from the database or creates an empty object.
	 * 
	 * @param int $id provider record identifier
	 * @return object instance of provider class
	 */
	public function __construct($id = false) {
		// create empty record or retrieve
		if (!$id) return false;

		// retrieve data
		$query = "SELECT * FROM `users` WHERE `id` = ?";
		$binds = array($id);
		$data = sqlQuery($query,$binds);

		$fields = $this->listFields(true);
		
		if ($data && $data['username']) {
			// load everything returned into object
			foreach ($fields AS $field) {
				$this->$field = $data[$field];
			}
		}
		else {
			throw new \Exception('mdtsProvider::_construct - no provider record with id ('.$id.').');
		}
		
		// preformat commonly used data elements
		$this->format_name = ($this->title)? "$this->title " : "";
		$this->format_name .= ($this->fname)? "$this->fname " : "";
		$this->format_name .= ($this->mname)? substr($this->mname,0,1).". " : "";
		$this->format_name .= ($this->lname)? "$this->lname " : "";
		$this->format_name .= ($this->valedictory)? "$this->valedictory" : ""; 

		return;
	}	

	/**
	 * Retrieve a provider object by USERNAME value. Uses the base constructor for the 'provider' class 
	 * to create and return the object. 
	 * 
	 * @static
	 * @method		getUsername
	 * @param 		string $username provider user name
	 * @return 		Provider
	 * 
	 */
	public static function getUsername($username) {
		if(!$username)
			throw new \Exception('mdtsProvider::getUserProvider - no provider username provided.');
		
		$data = sqlQuery("SELECT `id` FROM `users` WHERE `username` LIKE ?", array($username));
		if(!$data || !$data['id'])
			throw new \Exception('mdtsProvider::getUserProvider - no provider with username provided.');
		
		return new Provider($data['id']);
	}

	/**
	 * Retrieve a new provider object by npi.
	 * 
	 * @method		getNpi
	 * @param		string 	$npi
	 * @return		Provider
	 * @static
	 * 
	 */
	public static function getNpi($npi) {
		$id = null;
		
		// look for existing entry
		if ($npi) {
			$record = sqlQuery("SELECT id FROM users WHERE npi LIKE ?", array($npi));
			$id = $record['id'];
		}

		// create/retrieve data object
		$provider = new Provider($id);

		return $provider;
	}

	/**
	 * Retrieve a new provider object by cda guid.
	 * 
	 * @method		getCdaGuid
	 * @param		string 	$npi
	 * @return		Provider
	 * @static
	 * 
	 */
	public static function getCdaGuid($cda_guid) {
		$id = null;
		
		// look for existing entry
		if ($cda_guid) {
			$record = sqlQuery("SELECT id FROM users WHERE cda_guid LIKE ?", array($cda_guid));
			$id = $record['id'];
		}

		// create/retrieve data object
		$provider = new Provider($id);

		return $provider;
	}

	/**
	 * Retrieve a new provider object by name.
	 * 
	 * @method		getName
	 * @param		string 	$fname
	 * @param		string 	$mname
	 * @param		string 	$lname
	 * @return		Provider
	 * @static
	 * 
	 */
	public static function getName($fname,$mname,$lname) {
		$id = null;
		
		// look for existing entry
		if ($fname && $mname && $lname) {
			$record = sqlQuery("SELECT id FROM users WHERE fname LIKE ? AND mname LIKE ? AND lname LIKE ?", array($fname,$mname,$lname));
			$id = $record['id'];
		} elseif ($fname && $lname) {
			$record = sqlQuery("SELECT id FROM users WHERE fname LIKE ? AND lname LIKE ?", array($fname,$lname));
			$id = $record['id'];
		}

		// create/retrieve data object
		$provider = new Provider($id);

		return $provider;
	}

	
	/**
	 * Retrieve a provider object by NPI value. Uses the base constructor for the 'provider' class 
	 * to create and return the object. 
	 * 
	 * @static
	 * @param string $npi provider npi
	 * @return object instance of provider class
	 */
	public static function getNpiProvider($npi) {
		if(!$npi)
			throw new \Exception('mdtsProvider::getNpiProvider - no provider NPI provided.');
		
		$data = sqlQuery("SELECT `id` FROM `users` WHERE `npi` LIKE ?", array($npi));
		if(!$data || !$data['id'])
			throw new \Exception('mdtsProvider::getNpiProvider - no provider with NPI provided.');
		
		return new Provider($data['id']);
	}
	
	/**
	 * Retrieve list of provider objects
	 *
	 * @static
	 * @parm string $facility - id of a specific facility
	 * @param boolean $active - active status flag
	 * @return array $list - list of provider objects
	 */
	public static function fetchProviders($facility=false,$active=true) {
		$binds = null;
		$query = "SELECT `id` FROM `users` WHERE `authorized` = 1 ";
		$query .= "AND `npi` != '' AND `npi` IS NOT NULL ";
		$query .= "AND `username` != '' AND `username` IS NOT NULL ";
		
		if ($facility) {
			$query .= "AND `facility_id` = ? ";
			$binds[] = $facility;
		}

		if ($active) $query .= "AND `active` = 1 ";
		
		$query .= "ORDER BY lname, fname, mname";
		
		$list = array();
		$result = sqlStatementNoLog($query,$binds);
		while ($record = sqlFetchArray($result)) {
			$list[$record['id']] = new Provider($record['id']);
		}
		
		return $list;
	}

	/**
	 * Build selection list from table data.
	 *
	 * @param int $id - current entry id
	 */
	public function getOptions($id, $default='') {
		$result = '';
		
		// create default if needed
		if ($default) {
			$result .= "<option value='' ";
			$result .= (!$id || $id == '')? "selected='selected'" : "";
			$result .= ">".$default."</option>\n";
		}

		// get providers
		$list = self::fetchProviders();
		
		// build options
		foreach ($list AS $provider) {
			$result .= "<option value='" . $provider->id . "' ";
			if ($id == $provider->id) 
				$result .= "selected=selected ";
			$result .= ">" . $provider->format_name ."</option>\n";
		}
	
		return $result;
	}
	
	/**
	 * Echo selection option list from table data.
	 *
	 * @param id - current entry id
	 * @param result - string html option list
	 */
	public function showOptions($id, $default='') {
		echo self::getOptions($id, $default);
	}
	
	/**
	 * Returns an array of valid database fields for the object. Note that this
	 * function only returns fields that are defined in the object and are
	 * columns of the specified database.
	 *
	 * @return array list of database field names
	 */
	public function listFields() {
		$fields = array();

		$columns = sqlListFields('users');
		foreach ($columns AS $property) {
			if (property_exists($this, $property)) $fields[] = $property;
		}
		
		return $fields;
	}
	
}

?>