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
 *  @copyright 	Medical Technology Services
 *  @author 	Ron Criswell <ron.criswell@MedTechSvcs.com>
 *  @version 	1.0.0
 *
*/
class Facility {
	public $id;
	public $name;
	public $phone;
	public $fax;
	public $street;
	public $city;
	public $state;
	public $postal_code;
	public $country_code;
	public $email;
	public $attn;
	public $cda_guid;
	public $facility_npi;
	public $service_location;
	public $billing_location;
		
	/**
	 * Create or retrieve a new facility object.
	 * 
	 * @method		__construct
	 * @param 		int $id
	 * 
	 */
	public function __construct($id) {
		if (!$id) return;
		
		// look for existing patient record
		$record = sqlQuery("SELECT * FROM facility WHERE id = ?", array($id));
		if (!$record['id']) return;
		
		// retrieve data associated with this object
		foreach (array_keys(get_object_vars($this)) AS $element) {
			$this->$element = $record[$element];
		}

		return;
	}
	
	/**
	 * Create or retrieve a new facility object.
	 * 
	 * @method		getCdaGuid
	 * @param 		string $cda_guid
	 * @return		Facility
	 * @static
	 * 
	 */
	public static function getCdaGuid($cda_guid) {
		$id = null;
		
		// look for existing patient record
		if ($cda_guid) {
			$record = sqlQuery("SELECT `id` FROM `facility` WHERE `cda_guid` = ?", array($cda_guid));
			$id = $record['id'];
		}
		
		// create/retrieve object record
		$facility = new Facility($id);

		return $facility;
	}
	
	/**
	 * Retrieve default facility object.
	 *
	 * @method		getDefault
	 * @return		Facility
	 * @static
	 *
	 */
	public static function getDefault() {
		$id = null;
		
		// look for existing facility
		$sql = "SELECT f.`id` FROM `facility` f WHERE f.`id` = ";
		$sql .= "(SELECT `title` FROM `list_options` WHERE `list_id` = 'CDA_Defaults' AND `option_id` = 'facility' LIMIT 1)";
		$record = sqlQuery($sql);
		
		// alternate
		if (empty($record['id'])) 
			$record = sqlQuery("SELECT `id` FROM `facility` LIMIT 1");

		// store the id
		$id = $record['id'];
			
		// create/retrieve object record
		$facility = new Facility($id);
		
		return $facility;
	}
	
	/**
	 * Retrieve list of provider objects
	 *
	 * @static
	 * @parm string $facility - id of a specific facility
	 * @param boolean $active - active status flag
	 * @return array $list - list of provider objects
	 */
	public static function fetchFacilities($service=true,$billing=false) {
		$query = "SELECT `id` FROM `facility` WHERE 1 = 1 ";
		if ($service) $query .= "AND `service_location` = 1 ";
		if ($billing) $query .= "AND `billing_location` = 1 ";
		$query .= "ORDER BY name";
		
		$list = array();
		$result = sqlStatementNoLog($query);
		while ($record = sqlFetchArray($result)) {
			$list[$record['id']] = new Facility($record['id']);
		}
		
		return $list;
	}
	
	/**
	 * Build selection list from table data.
	 *
	 * @param int $id - current entry id
	 */
	public function getOptions($id, $default='', $service=true, $billing=false) {
		$result = '';
		
		// create default if needed
		if ($default) {
			$result .= "<option value='' ";
			$result .= (!$itemId || $itemId == '')? "selected='selected'" : "";
			$result .= ">".$default."</option>\n";
		}

		// get providers
		$list = self::fetchFacilities($service, $billing);
		
		// build options
		foreach ($list AS $facility) {
			$result .= "<option value='" . $facility->id . "' ";
			if ($id == $facility->id) 
				$result .= "selected=selected ";
			$result .= ">" . $facility->name ."</option>\n";
		}
	
		return $result;
	}
	
	/**
	 * Echo selection option list from table data.
	 *
	 * @param id - current entry id
	 * @param result - string html option list
	 */
	public function showOptions($id, $default='', $service=true, $billing=false) {
		echo self::getOptions($id, $default, $service, $billing);
	}
	
	/**
	 * Store or update the facility information.
	 * 
	 * @method		store
	 * 
	 */
	public function store() {
		$columns = null;
		$values = null;

		// mandatory defaults
		$this->service_location = 1;
		$this->billing_location = 1;
		
		// retrieve database columns
		$fields = sqlListFields('facility');
		
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
			sqlStatement("UPDATE facility SET $columns WHERE id = ?", $values);
		} else {
			// insert database record
			$this->id = sqlInsert("INSERT INTO facility SET $columns", $values);
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