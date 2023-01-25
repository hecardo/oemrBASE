<?php
/**
 * @package   WMT
 * @author    Ron Criswell <ron@medtechsvcs.com>
 * @copyright Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Objects;

/**
 *  Object definition for patient.
 */
class Patient {
	public $id;
	public $title;
	public $language;
	public $fname;
	public $lname;
	public $mname;
	public $prev_fname;
	public $prev_lname;
	public $prev_mname;
	public $suffix;
	public $prefix;
	public $DOB;
	public $street;
	public $postal_code;
	public $city;
	public $state;
	public $country_code;
	public $ss;
	public $phone_home;
	public $phone_contact;
	public $phone_cell;
	public $status;
	public $sex;
	public $referrer;
	public $referrerID;
	public $providerID;
	public $ref_providerID;
	public $email;
	public $race;
	public $race_rollup;
	public $ethnicity;
	public $ethnoracial;
	public $pubpid;
	public $pid;
	public $referral_source;
	
	public $external;  	// store in separate table
	public $facility;	// store in separate table
	
	/**
	 * Create or retrieve a new patient object.
	 * 
	 * @method		__construct
	 * @param		int	$id
	 * 
	 */
	public function __construct($id=false) {
		if (!$id) return;
		
		// look for existing patient record
		$record = sqlQuery("SELECT * FROM `patient_data` WHERE `id` = ?", array($id));
		
		if ($record && $record['id']) {
			// retrieve data associated with this object
			foreach ($record AS $field => $value) {
				$this->$field = $value;
			}
		} else {
			return false;
		}

		// also get employer data
		$query = "SELECT `name` AS 'emp_name', `street` AS 'emp_street', `city` AS 'emp_city', `postal_code` AS 'emp_zip', `state` AS 'emp_state', `country` AS 'emp_county' ";
		$query .= "FROM `employer_data` WHERE `pid` = ? ORDER BY DATE DESC LIMIT 1";
		$binds = array($data['pid']);
		
		$record = sqlQuery($query,$binds);
	
		if ($record && $record['emp_name']) {
			// retrieve data associated with this object
			foreach ($record AS $field => $value) {
				$this->$field = $value;
			}
		}
		
		// preformat commonly used data elements
		$this->format_name .= ($this->fname)? "$this->fname " : "";
		$this->format_name .= ($this->mname)? substr($this->mname,0,1).". " : "";
		$this->format_name .= ($this->lname)? "$this->lname " : "";

		if ($this->DOB && strtotime($this->DOB) !== false) { // strtotime returns FALSE
			$this->age = floor( (strtotime('today') - strtotime($this->DOB)) / 31556926 );
			$this->birth_date = date('Y-m-d', strtotime($this->DOB));
		}
		
		return;
	}	

	/**
	 * Returns the next PID for the patient table.
	 *
	 * @static
	 * @return int patient identifier
	 */
	public static function getNewPid() {
		$result = sqlQuery("SELECT MAX(pid) + 1 AS pid FROM `patient_data`");
		$pid = ($result['pid'] > 0)? $result['pid']: '1'; 

		return $pid;
	}
	
	/**
	 * Create or retrieve a new patient object by pid.
	 * 
	 * @method		getPid
	 * @param		string pid
	 * @return		patient
	 * @static
	 * 
	 */
	public static function getPid($pid) {
		$id = null;
		
		// look for existing patient record
		if ($pid) {
			$record = sqlQuery("SELECT id FROM patient_data WHERE pid LIKE ?", array($pid));
			$id = $record['id'];
		}
		
		// create or retrieve record
		$patient = new Patient($id);

		return $patient;
	}
	
	/**
	 * Alias to getPid()
	 */
	public static function getPidPatient($pid) {
		return self::getPid($pid);
	}
	
	/**
	 * Create or retrieve a new patient object by pubpid.
	 * 
	 * @method		getPubpid
	 * @param		patient
	 * @static
	 * 
	 */
	public static function getPubpid($pubpid) {
		$id = null;
		
		// look for existing patient record
		if ($pubpid) {
			$record = sqlQuery("SELECT id FROM patient_data WHERE pubpid LIKE ?", array($pubpid));
			$id = $record['id'];
		}
		
		// create or retrieve record
		$patient = new Patient($id);

		return $patient;
	}
	
	/**
	 * Create or retrieve a new patient object by external identifier.
	 * 
	 * @method		getExternal
	 * @param		string	$facility - cda_guid value
	 * @param		string	$external - external identifier
	 * @return		patient
	 * @static
	 * 
	 */
	public static function getExternal($facility, $external) {
		$id = null;
		
		// look for existing patient record
		if ($external) {
			$query = "SELECT pd.id FROM patient_data pd, patient_external pe ";
			$query .= "WHERE pe.facility LIKE ? AND pe.external LIKE ? AND pe.pid = pd.pid";
			$record = sqlQuery($query, array($facility, $external));
			$id = $record['id'];
		}
		
		// create or retrieve record
		$patient = new Patient($id);

		return $patient;
	}
	
	/**
	 * Stores data from a patient object into the database. This is the 
	 * replacement for the 'insert' and 'update' functions.
	 *
	 * @return int $id identifier for object
	 */
	public function store() {
		$insert = true;
		if($this->id) $insert = false;

		// set defaults
		$this->activity = 1;
		$this->date = date('Y-m-d H:i:s');
		
		// set pid on insert if empty
		if ($insert) {
			if (empty($this->pid)) $this->pid = self::getNewPid();
			if (empty($this->pubpid)) $this->pubpid = $this->pid;
		}
		
		// create record
		$sql = '';
		$binds = array();
		$fields = $this->listFields(true);
		
		// selective updates
		foreach ($fields AS $field) {
			if ($field == 'id') continue;
			
			$value = $this->$field;
			if ($value == 'YYYY-MM-DD' || $value == "_blank") $value = "";
			
			$sql .= ($sql)? ", `$field` = ? " : "`$field` = ? ";
			$binds[] = ($value == 'NULL')? "" : $value;
		}
		
		// run the statement
		if ($insert) { 
			// patient insert
			$this->id = sqlInsert("INSERT INTO `patient_data` SET $sql",$binds);
		} else { 
			// patient update
			$binds[] = $this->id;		
			sqlStatement("UPDATE `patient_data` SET $sql WHERE id = ?",$binds);
		}
		
		// also store employer data if needed
		if (isset($this->emp_name)) {
			$query = "INSERT INTO `employer_data` SET ";
			$query .= "`name`=?, `street`=?, `city`=?, `state`=?, `postal_code`=?, `country` = ?, `pid`=?, `date`=? ";
			$binds = array();
			$binds[] = $this->emp_name;
			$binds[] = $this->emp_street;
			$binds[] = $this->emp_city;
			$binds[] = $this->emp_state;
			$binds[] = $this->emp_zip;
			$binds[] = $this->emp_county;
			$binds[] = $this->pid;
			$binds[] = date('Y-m-d H:i:s');
			sqlInsert($query,$binds);		
		}
		
		return $this->id;
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

		$columns = sqlListFields('patient_data');
		foreach ($columns AS $property) {
			if (property_exists($this, $property)) $fields[] = $property;
		}
		
		return $fields;
	}
	
}

?>