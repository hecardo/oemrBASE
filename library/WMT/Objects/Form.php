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
 * Provides a representation of the generic form data record. Fields are dymanically
 * processed based on the current database definitions. 
 */
class Form {
	public $id;
	public $created;
	public $date;
	public $pid;
	public $user;
	public $provider;
	public $encounter;
	public $groupname;
	public $authorized;
	public $activity;
	public $status;
	public $priority;
	public $approved_by;
	public $approved_dt;
	
	// control elements
	public $form_name;
	public $form_table;
	public $form_title;
	
	/**
	 * Constructor for the 'form' class which retrieves the requested
	 * information from the database or creates an empty object.
	 *
	 * @param string $form_table database table
	 * @param int $id record identifier
	 * @return object instance of form class
	 */
	public function __construct($form_name, $id=false) {
		if (empty($form_name)) {
			throw new \Exception('Form::_construct - no form name provided.');
		}
		
		// store table name in object
		$this->form_name = $form_name;
		$this->form_table = 'form_'.$form_name;

		// find form title
		$record = sqlQuery("SELECT `name` FROM `registry` WHERE `directory` LIKE ?", array($form_name));
		$this->form_title = (empty($record['name']))? $form_name : $record['name'];

		// done if this is a new record
		if (!$id) return false;
		
		// retrieve record
		$query = "SELECT f.*, t.* FROM `$this->form_table` t ";
		$query .= "LEFT JOIN `forms` f ON f.`form_id` = t.`id` AND f.`formdir` = ? ";
		$query .= "WHERE t.`id` = ?";
		$data = sqlQuery($query, array($form_name, $id));
		
		if ($data && $data['id']) {
			// load properties returned into object
			foreach ($data AS $key => $value) {
				if ($key == 'form_name' || $key == 'form_table') continue;
				$this->$key = $value;
			}
		}
		else {
			throw new \Exception('Form::_construct - no record with id ('.$this->form_table.' - '.$id.').');
		}

		// preformat commonly used data elements
		$this->created = (strtotime($this->created) !== false)? date('Y-m-d H:i:s',strtotime($this->created)) : date('Y-m-d H:i:s');
		$this->date = (strtotime($this->date) !== false)? date('Y-m-d H:i:s',strtotime($this->date)) : date('Y-m-d H:i:s');

		return;
	}

	/**
	 * Stores data from a form object into the database.
	 *
	 * @return int $id identifier for object
	 */
	public function store() {
		$insert = true;
		if ($this->id) $insert = false;

		// create record
		$sql = '';
		$binds = array();
		$fields = $this->listFields();
		
		$this->date = date('Y-m-d H:i:s'); // last updated
		
		if (empty($this->created)) $this->created = date('Y-m-d H:i:s');
		if (empty($this->user)) $this->user = $_SESSION['authUser'];
		if (empty($this->authorized)) $this->authorized = $_SESSION['authorized'];
		$this->authorized = 1;
		if (empty($this->groupname)) $this->groupname = $_SESSION['authProvider'];
			
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
			$this->id = sqlInsert("INSERT INTO $this->form_table SET $sql",$binds);

			// insert into form index
			$sql = "INSERT INTO `forms` ";
			$sql .= "(date, encounter, form_name, form_id, pid, user, groupname, authorized, formdir) ";
			$sql .= "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
			
			$binds = array();
			$binds[] = $this->created;
			$binds[] = $this->encounter;
			$binds[] = $this->form_title;
			$binds[] = $this->id;
			$binds[] = $this->pid;
			$binds[] = $this->user;
			$binds[] = $this->groupname;
			$binds[] = $this->authorized;
			$binds[] = $this->form_name;
			
			// run the insert
			sqlInsert($sql, $binds);

		} else { // do update
			$binds[] = $this->id;		
			sqlStatement("UPDATE $this->form_table SET $sql WHERE id = ?",$binds);
		}
		
		return $this->id;
	}

	/**
	 * Returns an array list objects associated with the
	 * given PATIENT and optionally a given TYPE. If no TYPE is given
	 * then all forms for the PATIENT are returned.
	 *
	 * @static
	 * @param int $pid patient identifier
	 * @param string $type type of list to select
	 * @param bool $active active items only flag
	 * @return array $objectList list of selected list objects
	 */
	public static function fetchPidList($form_name, $pid, $active=true, $order=false) {
		if (!$form_name || !$pid)
			throw new \Exception('Form::fetchPidItem - missing parameters');

		if (empty($order)) $order = 'date';
		
		$query = "SELECT form_id FROM forms ";
		$query .= "WHERE formdir = ? AND pid = ? ";
		if ($active) $query .= "AND deleted = 0 ";
		$query .= "ORDER BY $order";

		$results = sqlStatement($query, array($form_name,$pid));

		$objectList = array();
		while ($data = sqlFetchArray($results)) {
			$objectList[] = new Form($form_name,$data['form_id']);
		}

		return $objectList;
	}

	/**
	 * Returns an array list objects associated with the
	 * given ENCOUNTER and optionally a given TYPE. If no TYPE is given
	 * then all issues for the ENCOUNTER are returned.
	 *
	 * @static
	 * @param int $encounter encounter identifier
	 * @param string $type type of list to select
	 * @param bool $active active items only flag
	 * @return array $objectList list of selected list objects
	 */
	public static function fetchEncounterList($form_name, $encounter, $active=true) {
		if (!$form_name || !$encounter)
			throw new \Exception('Form::fetchEncounterItem - missing parameters');

		$query = "SELECT form_id FROM forms ";
		$query .= "WHERE formdir = ? AND encounter = ? ";
		if ($active) $query .= "AND deleted = 0 ";
		$query .= "ORDER BY date, id";

		$results = sqlStatement($query,array($form_name,$encounter));

		$objectList = array();
		while ($data = sqlFetchArray($results)) {
			$objectList[] = new Form($form_name,$data['form_id']);
		}

		return $objectList;
	}

	/**
	 * Returns the most recent form object or an empty object based
	 * on the PID provided.
	 *
	 * @static
	 * @param string $form_name form type name
	 * @param int $pid patient identifier
	 * @param bool $active active items only flag
	 * @return object $form selected object
	 */
	public static function fetchRecent($form_name, $pid, $active=true) {
		if (!$form_name || !$pid)
			throw new \Exception('Form::fetchRecent - missing parameters');

		$query = "SELECT form_id FROM forms ";
		$query .= "WHERE formdir = ? AND pid = ? ";
		if ($active) $query .= "AND deleted = 0 ";
		$query .= "ORDER BY date DESC, id DESC";

		$data = sqlQuery($query,array($form_name,$pid));
		
		return new Form($form_name,$data['form_id']);
	}

	/**
	 * Returns the most recent form object or an empty object based
	 * on the PID provided.
	 *
	 * @static
	 * @param string $form_name form type name
	 * @param int $pid patient identifier
	 * @param bool $active active items only flag
	 * @return object $form selected object
	 */
	public static function fetchEncounter($form_name, $encounter, $active=true) {
		if (!$form_name || !$encounter)
			throw new \Exception('Form::fetchEncounter - missing parameters');

		$query = "SELECT form_id FROM forms ";
		$query .= "WHERE formdir = ? AND encounter = ? ";
		if ($active) $query .= "AND deleted = 0 ";
		$query .= "ORDER BY date DESC, id DESC";

		$data = sqlQuery($query,array($form_name,$encounter));
		
		return new Form($form_name,$data['form_id']);
	}

	/**
	 * Returns an array of valid database fields for the object. Note that this
	 * function only returns fields that are defined in the object and are
	 * columns of the specified database.
	 *
	 * @return array list of database field names
	 */
	public function listFields() {
		if (!$this->form_table)
			throw new \Exception('Form::listFields - no form table name available.');
		
		$fields = array();
		
		$columns = sqlListFields($this->form_table);
		foreach ($columns AS $property) {
			$fields[] = $property;
		}
		
		return $fields;
	}
	
}
?>
