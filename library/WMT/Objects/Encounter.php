<?php
/**
 * @package   WMT
 * @author    Ron Criswell <ron@medtechsvcs.com>
 * @copyright Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

/**
 * All new classes are defined in the MDTS namespace
 */
namespace WMT\Objects;

/** 
 * Provides standardized base class for an encounter which
 * is typically extended for specific types of encounters.
 */
class Encounter {
	public $id;
	public $date;
	public $groupname;
	public $authorized;
	public $user;
	public $reason;
	public $facility;
	public $facility_id;
	public $pid;
	public $encounter;
	public $onset_date;
	public $sensitivity;
	public $billing_note;
	public $pc_catname;
	public $pc_catid;
	public $provider_id;
	public $supervisor_id;
	public $referral_source;
	public $billing_facility;
	
	public $form_title;
	public $form_name;

	/**
	 * Constructor for the 'encounter' class which retrieves the requested 
	 * record from the database or creates an empty object.
	 * 
	 * @param int $id record identifier
	 * @return object instance of class
	 */
	public function __construct($id = false) {
		if(!$id) return false;

		/* SFA Specific
		$query = "SELECT fe.*, pc.`pc_catname`, st.`id` AS student_id, ";
		$query .= "CONCAT(pr.`fname`, IF(ISNULL(pr.`mname`),'',CONCAT(' ', pr.`mname`)), ' ', pr.`lname`, IF(ISNULL(pr.`suffix`),'',CONCAT(' ', pr.`suffix`))) AS 'provider_name', ";
		$query .= "CONCAT(st.`fname`, IF(ISNULL(st.`mname`),'',CONCAT(' ', st.`mname`)), ' ', st.`lname`, IF(ISNULL(st.`suffix`),'',CONCAT(' ', st.`suffix`))) AS 'student_name' ";
		$query .= "FROM `form_encounter` fe ";
		$query .= "LEFT JOIN `openemr_postcalendar_categories` pc ON fe.`pc_catid` = pc.`pc_catid` ";
		$query .= "LEFT JOIN `users` pr ON fe.`provider_id` = pr.`id` ";
		$query .= "LEFT JOIN `users` st ON st.`student` = st.`id` ";
		$query .= "WHERE fe.`id` = ? ";
		*/
		
		$query = "SELECT fe.*, pc.`pc_catname`, ";
		$query .= "CONCAT(pr.`fname`, IF(ISNULL(pr.`mname`),'',CONCAT(' ', pr.`mname`)), ' ', pr.`lname`, IF(ISNULL(pr.`suffix`),'',CONCAT(' ', pr.`suffix`))) AS 'provider_name' ";
		$query .= "FROM `form_encounter` fe ";
		$query .= "LEFT JOIN `openemr_postcalendar_categories` pc ON fe.`pc_catid` = pc.`pc_catid` ";
		$query .= "LEFT JOIN `users` pr ON fe.`provider_id` = pr.`id` ";
		$query .= "WHERE fe.`id` = ? ";
		$data = sqlQueryNoLog($query, array($id));
	
		if ($data) {
			// load everything returned into object
			foreach ($data as $key => $value) {
				if ($key == 'date' || $key == 'onset_date') {
					$time = substr($value, -8);
					$value = substr($value, 0, 10);
					if ($time == '00:00:00') { $time = ''; }
					if ($value == '0000-00-00') { $value = ''; $time = ''; }
				}
				
				if ($key == 'date') {
					$this->encounter_date = $value;
					$this->encounter_time = $time;
				}
				
				if ($key == 'reason') {
					$this->full_reason = $data['reason'];
					$this->short_reason = substr($data['reason'], 0, 100);
				}
				
				$this->$key = $value;
				
				// Special replacements
				if ($key == 'encounter') { $this->encounter_id = $value; }
				if ($key == 'billing_facility') { $this->facility_id = $value; }
			}
		}
		else {
			throw new \Exception('mdtsEncounter::_construct - no encounter record with id ('.$id.').');
		}

		// Default facility to 3 if not set
		if (!$this->facility_id) $this->facility_id= 3;
		
		// Preformat commonly used data elements	
		$signing_user = '';
		$this->signed_by = xl('No Signature on File','r');
		if ($this->provider_id) {
    		$this->signed_by = xl('Digitally Signed By ' , 'r') . $this->provider_name;
  		}
		if ($this->student_id) {
			$this->student_by = xl('Student: ', 'r') . $this->student_name;
			$this->student_by .= '<br>The service was supervised and directed by '.
				'the provider during the key and critical portions of the service, '.
				'including the management of the patient.';
		}
	
	}	
		
	/**
	 * Inserts data from object into the database.
	 */
	public function store() {
		$insert = false;

		if (!$this->id) {
			$insert = true;

			// get facility name from id
			$fres = sqlQuery("SELECT `name` FROM `facility` WHERE `id` = ?", array($this->facility_id));
			$this->facility = $fres['name'];
	
			// create basic encounter
			$this->encounter = generate_id(); // in sql.inc
			
			// verify dates (strtotime returns false on invalid date)
			if (! strtotime($this->date)) $this->date = date('Y-m-d');
			if (! strtotime($this->onset_date)) $this->onset_date = $this->date;
		}
		
		// build sql from object
		if (empty($this->activity)) $this->activity = 1;
		if (empty($this->created)) $this->created = date('Y-m-d H:i:s');
		if (empty($this->user)) $this->user = $_SESSION['authUser'];
		if (empty($this->authorized)) $this->authorized = $_SESSION['authorized'];
		if (empty($this->groupname)) $this->groupname = $_SESSION['authProvider'];
		if (empty($this->referral_source)) $this->referral_source = '';
			
		// get table fields
		$fields = self::listFields();
		
		// selective updates
		foreach ($this AS $key => $value) {
			if ($key == 'id') continue;
			if ($value == 'YYYY-MM-DD' || $value == "_blank") $value = "";

			// both object and database
			if (array_search($key, $fields) !== false) {
				$sql .= ($sql)? ", `$key` = ? " : "`$key` = ? ";
				if (is_array($value)) $value = implode('|', $value);
				$binds[] = ($value == 'null')? "" : $value;
			}
		}
		
		// run the statement
		if ($insert) { // do insert
			// insert into form table
			$this->id = sqlInsert("INSERT INTO `form_encounter` SET $sql", $binds);

		} else { // do update
			$binds[] = $this->id;		
			sqlStatement("UPDATE `form_encounter` SET $sql WHERE `id` = ?",	$binds);
		}
		
		// replace into form index
		$sql = "REPLACE INTO `forms` ";
		$sql .= "(date, encounter, form_name, form_id, pid, user, groupname, authorized, formdir) ";
		$sql .= "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

		$binds = array();
		$binds[] = $this->date;
		$binds[] = $this->encounter;
		$binds[] = $this->form_title;
		$binds[] = $this->id;
		$binds[] = $this->pid;
		$binds[] = $this->user;
		$binds[] = ($this->groupname)? $this->groupname : 'Default';
		$binds[] = ($this->authorized)? 1 : 0;
		$binds[] = 'newpatient';
		
		// run the insert
		sqlInsert($sql, $binds);
		
		return $this->id;
	}

	/**
	 * Retrieve the diagnoses list for this encounter.
	 * 
	 * @return array of diagnosis data
	 */
	public function getEncDiag() {
		if (!$this->encounter)
			throw new \Exception ("mdtsEncounter::getEncDiag - no encounter identifier in current record.");
		
		$query = "SELECT lis.diagnosis, lis.title FROM `issue_encounter` ise ";
		$query .= "LEFT JOIN `lists` lis ON ise.list_id = lis.id AND lis.type LIKE 'medical_problem' ";
		$query .= "WHERE ise.encounter = ?";
		$result = sqlStatement($query,array($this->encounter));
		
		$data = array();
		while ($record = sqlFetchArray($result)) {
			$data[$record['diagnosis']] = $record['title'];
		}
			
		return $data;
	}
	
	/**
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public static function listPidEncounters($pid) {
		if (!$pid) return FALSE;

		$query = "SELECT fe.encounter, fe.id FROM form_encounter fe ";
		$query .= "LEFT JOIN issue_encounter ie ON fe.id = ie.list_id ";
		$query .= "LEFT JOIN lists l ON ie.list_id = l.id ";
		$query .= "WHERE fe.pid = ? AND l.enddate IS NULL ";
		$query .= "ORDER BY fe.date, fe.encounter";

		$results = sqlStatement($query,array($pid));
	
		$txList = array();
		while ($data = sqlFetchArray($results)) {
			$txList[] = array('id' => $data['id'], 'encounter' => $data['encounter']);
		}
		
		return $txList;
	}

	/**
	 * Retrieve the encounter record by encounter number.
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public static function getEncounter($encounter) {
		if (!$encounter)
			throw new \Exception ("mdtsEncounter::getEncounter - no encounter identifier provided.");
		
		$query = "SELECT `id` FROM `form_encounter` WHERE `encounter` = ?";
		$data = sqlQuery($query,array($encounter));
		
		if (!$data || !$data['id'])
			throw new \Exception ("mdtsEncounter::getEncounter - no encounter for provided identifier.");
			
		return new Encounter($data['id']);
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

		$columns = sqlListFields('form_encounter');
		foreach ($columns AS $property) {
			if (property_exists($this, $property)) $fields[] = $property;
		}
		
		return $fields;
	}
}
?>