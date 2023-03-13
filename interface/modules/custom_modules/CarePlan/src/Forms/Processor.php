<?php
/**
 * @package   	WMT
 * @subpackage	Laboratory
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Laboratory\Common;

/** 
 * Provides a representation of the patient data record. Fields are dymanically
 * processed based on the current database definitions. 
 */
class Processor {
	// Selected elements
	public $ppid;
	public $uuid;
	public $name;
	public $npi;
	public $send_app_id;
	public $send_fac_id;
	public $recv_app_id;
	public $recv_fac_id;
	public $DorP;
	public $direction;
	public $protocol;
	public $remote_host;
	public $remote_port;
	public $login;
	public $password;
	public $orders_path;
	public $results_path;
	public $notes;
	public $lab_director;
	public $active;
	public $type;
	
	public $lab_account;
	public $lab_work_path;
	public $lab_ins_pick;   // special for SFA
	public $lab_sms_notify;
	public $lab_draw_bill;
	public $lab_psc_only;
	public $lab_appt_id;
	public $lab_appt_name;
	public $lab_cat_id;
	public $lab_cat_name;
	
	public $mod_id;
	public $mod_active;
	
	/**
	 * Constructor for the 'processor' class which retrieves the requested 
	 * procedure Processor information from the database or creates an empty object.
	 * 
	 * @param int $ppid processor record identifier
	 * @return object instance of Processor provider object class
	 */
	public function __construct($ppid = false) {
		// create empty record or retrieve
		if (!$ppid) return false;

		// retrieve data
		$query = "SELECT * FROM `procedure_providers` WHERE `ppid` = ?";
		$binds = array($ppid);
		$data = sqlQuery($query, $binds);

		if ($data) {
			foreach ($data AS $field => $value) {
				// load everything returned into object
				$this->$field = $value;
			}
		} else {
			throw new \Exception('Processor::_construct - no processor record with ppid ('.$ppid.').');
		}
		
		// retrieve module data
		$query = "SELECT * FROM `modules` WHERE `mod_directory` LIKE ?";
		$binds = array('Laboratory');
		$data = sqlQuery($query, $binds);
		
		if ($data && $data['mod_active'] === 1 && isset($data['mod_id'])) {
			$this->mod_id = $data['mod_id'];
			$this->mod_active = true;
		} else {
			throw new \Exception('Processor::_construct - laboratory module is not active.');
		}
		
		// retrieve module configuration
		$query = "SELECT * FROM `module_configuration` WHERE `module_id` = ?";
		$binds = array($this->mod_id);
		$result = sqlStatement($query, $binds);
		while ($row = sqlFetchArray($result)) {
			switch ($row['field_name']) {
				case 'lab_work_path':
					$this->lab_work_path = $row['field_value'];
					break;
				case 'lab_account':
					$this->lab_account = $row['field_value'];
					break;
				case 'lab_psc_only':
					$this->lab_psc_only = $row['field_value'];
					break;
				case 'lab_orphan_pid':
					$this->lab_orphan_pid = $row['field_value'];
					break;
				case 'lab_pick_ins':
					$this->lab_pick_ins = $row['field_value'];
					break;
				case 'lab_draw_bill':
					$this->lab_draw_bill = $row['field_value'];
					break;
				case 'lab_sms_notify':
					$this->lab_sms_notify = $row['field_value'];
					break;
				case 'lab_appt_name':
					$this->lab_appt_name = $row['field_value'];
					$appt = sqlQueryNoLog("SELECT pc_catid FROM openemr_postcalendar_categories WHERE pc_catname LIKE ?",[$this->lab_appt_name]);
					$this->lab_appt_id = $appt['pc_catid'];
					break;
				case 'lab_cat_name':
					$this->lab_cat_name = $row['field_value'];
					$cat = sqlQueryNoLog("SELECT id FROM categories WHERE name LIKE ?",[$this->lab_cat_name]);
					$this->lab_cat_id = $cat['id'];
			}
		}
		
		return;
	}	

	/**
	 * Retrieve list of Processor provider objects
	 *
	 * @static
	 * @parm string 	$type - Processor provider type
	 * @param boolean 	$active - active status flag
	 * @return array 	$list - list of lab provider objects
	 */
	public static function fetchLabs($type=false, $active=true) {
		$binds = null;
		
		$query = "
			SELECT `ppid` FROM `procedure_providers` 
			WHERE 1 = 1 
		";
		
		if ($type) {
			$query .= "AND `type` LIKE ? ";
			$binds[] = $type;
		} else {
			$query .= "AND NULLIF(`type`,'') IS NOT NULL ";
		}
		
		if ($active) {
			$query .= "AND `active` = 1 ";
		}
		
		$query .= "ORDER BY name";
		
		$list = array();
		$result = sqlStatementNoLog($query, $binds);
		while ($record = sqlFetchArray($result)) {
			$list[$record['ppid']] = new Processor($record['ppid']);
		}
		
		return $list;
	}

	
	/**
	 * Retrieve a provider object by USERNAME value. Uses the base constructor for the 'provider' class 
	 * to create and return the object. 
	 * 
	 * @static
	 * @method		getUsername
	 * @param 		string $username provider user name
	 * @return 		Processor
	 * 
	 */
	public static function getUsername($username) {
		if(!$username)
			throw new \Exception('Processor::getUserLaboratory - no provider username provided.');
		
		$data = sqlQuery("SELECT `id` FROM `users` WHERE `username` LIKE ?", array($username));
		if(!$data || !$data['id'])
			throw new \Exception('Processor::getUserLaboratory - no provider with username provided.');
		
		return new Processor($data['id']);
	}

	/**
	 * Retrieve a new provider object by npi.
	 * 
	 * @method		getNpi
	 * @param		string 	$npi
	 * @return		Processor
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
		$provider = new Processor($id);

		return $provider;
	}

	/**
	 * Retrieve a new provider object by cda guid.
	 * 
	 * @method		getCdaGuid
	 * @param		string 	$npi
	 * @return		Processor
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
		$provider = new Processor($id);

		return $provider;
	}

	/**
	 * Retrieve a new provider object by name.
	 * 
	 * @method		getName
	 * @param		string 	$fname
	 * @param		string 	$mname
	 * @param		string 	$lname
	 * @return		Processor
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
		$provider = new Processor($id);

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
	public static function getNpiLaboratory($npi) {
		if(!$npi)
			throw new \Exception('Processor::getNpiLaboratory - no provider NPI provided.');
		
		$data = sqlQuery("SELECT `id` FROM `users` WHERE `npi` LIKE ?", array($npi));
		if(!$data || !$data['id'])
			throw new \Exception('Processor::getNpiLaboratory - no provider with NPI provided.');
		
		return new Processor($data['id']);
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
		$list = self::fetchLaboratorys();
		
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