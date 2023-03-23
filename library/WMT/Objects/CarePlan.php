<?php
/**
 * @package   WMT
 * @author    Ron Criswell <ron@medtechsvcs.com>
 * @copyright Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

/**
 * All new classes are defined in the WMT namespace
 */
namespace WMT\Objects;

/**
 *  Object definition for facility.
 *
 *  @name 		Facility
 *  @package	cda
 *  @copyright 	Medical Technology Services
 *  @author 	Ron Criswell <ron.criswell@MDTechSvcs.com>
 *  @version 	1.0.0
 *
*/
class CarePlan {
	public $id;
	public $date;
	public $pid;
	public $encounter;
	public $provider;	// ADDED: authorizing provider
	public $service; 	// ADDED: service line for this plan
	public $user;		// User entering the information
	public $groupname;
	public $authorized;
	public $activity;
	public $code;
	public $codetext;
	public $description;
	public $external_id;
	public $care_plan_type;
	public $note_related_to;
	public $date_end;
	public $reason_code;
	public $reason_description;
	public $reason_date_low;
	public $reason_date_high;
	public $reason_status;
		
	/**
	 * Create or retrieve a new care plan object.
	 * 
	 * @method		__construct
	 * @param 		int $id
	 * 
	 */
	public function __construct($id) {
		if (!$id) return;
		
		// look for existing patient record
		$record = sqlQuery("SELECT * FROM `care_plan` WHERE `id` = ?", array($id));
		if (!$record['id']) return;
		
		// retrieve data associated with this object
		foreach (array_keys(get_object_vars($this)) AS $element) {
			$this->$element = $record[$element];
		}

		return;
	}
	
	/**
	 * Retrieve care plans by patient..
	 *
	 * @method	getPlansByPid
	 * @param	string	$pid
	 * @return	array	$plans
	 * @static
	 *
	 */
	public static function getPlansByPid($pid) {
		
		// initialize empty list
		$plans = array();
		
		// retrieve records for patient
		$query = "SELECT `id` FROM `care_plan` WHERE `pid` = ?";
		$result = sqlStatement($query, [$pid]);
		
		// retrieve resulting records
		while ($record = sqlFetchArray($result)) {
			$plans[] = new CarePlan($record['id']);
		}
		
		return $plans;
	}
	
	/**
	 * Store or update the care plan information.
	 *
	 * @method		store
	 *
	 */
	public function store() {
		
		// retrieve database columns
		$fields = sqlListFields('care_plan');
		
		// create parameters associated with this object
		foreach (get_object_vars($this) AS $element => $value) {
			if (!in_array($element,$fields)) continue; // not in database
			if ($element == 'id') continue; // don't insert key
			
			if ($columns) $columns .= ', ';
			$columns .= "`".$element."` = ?";
			$values[] = $value;
		}
		
		// determine insert/update
		if ($this->id) {
			// update database record
			$values[] = $this->id;
			sqlStatement("UPDATE `care_plan` SET $columns WHERE `id` = ?", $values);
		} else {
			// insert database record
			$this->id = sqlInsert("INSERT INTO `care_plan` SET $columns", $values);
		}
		
		return;
	}
	
	/**
	 * Returns an array of valid database fields for the object. Note that this
	 * function only returns fields that are defined in the object and are
	 * columns of the specified database.
	 *
	 * @return array list of database field names
	 */
	public function listFields($full=false) {
		$fields = array();

		$columns = sqlListFields('facility');
		foreach ($columns AS $property) {
			// skip control fields unless full requested
			if ($full || property_exists($this, $property)) $fields[] = $property;
		}
		
		return $fields;
	}
}

?>