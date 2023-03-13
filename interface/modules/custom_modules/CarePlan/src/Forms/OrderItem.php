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
 * Provides standardized processing for procedure order detail form elements.
 *
 * @package mdts
 * @subpackage objects
 */
class OrderItem {
	public $procedure_order_id;
	public $procedure_order_seq;
	public $procedure_code; 
	public $procedure_name; 
	public $procedure_source; // 1=original order, 2=added after order sent
	public $procedure_type; // S=single, P=profile
	public $lab_id; // associated provider
	public $diagnoses; // array() diagnoses and maybe other coding (e.g. ICD9:111.11)
	public $do_not_send; // 0 = normal, 1 = do not transmit to lab
	
	/**
	 * Constructor for the 'form' class which retrieves the requested
	 * information from the database or creates an empty object.
	 *
	 * @param int $id record identifier
	 * @return object instance of form class
	 */
	public function __construct($proc_order_id=false, $proc_order_seq=false) {
		// create empty record with no id
		if (!$proc_order_id || !$proc_order_seq) return false;
		
		// retrieve data
		$query = "SELECT * FROM procedure_order_code poc WHERE procedure_order_id = ? AND procedure_order_seq = ?";
		$results = sqlStatement($query, array($proc_order_id, $proc_order_seq));

		if ($data = sqlFetchArray($results)) {
			// load everything returned into object
			foreach ($data as $key => $value) {
				$this->$key = ($update)? formDataCore($value) : $value;
			}
		}
		else {
			throw new \Exception('mdtsOrderItem::_construct - no procedure order item record with key ('.$proc_order_id.' '.$proc_order_seq.').');
		}

		return;
	}

	/**
	 * Inserts data from a form object into the database.
	 *
	 * @param mdtsForm $object
	 * @return int $id identifier for new object
	 */
	public function store() {
		// create record
		$sql = '';
		$binds = array();
		$this->activity = 1;
		$fields = $this->listFields();
		
		// selective updates
		foreach ($this AS $key => $value) {
			if ($key == 'id') continue;
			if ($value == 'YYYY-MM-DD' || $value == "_blank") $value = "";

			// both object and database
			if (array_search($key, $fields) !== false) {
				$sql .= ($sql)? ", `$key` = ? " : "`$key` = ? ";
				$binds[] = ($value == 'NULL')? "" : $value;
			}
		}
		
		// run the child insert
		sqlInsert("REPLACE `procedure_order_code` SET $sql",$binds);
				
		return $this;
	}


	/**
	 * Returns an array list of procedure order items along with CPT4 and pricing data
	 * used with the automatic billing process.
	 *
	 * @static
	 * @param int $proc_order_id Procedure order identifier (parent order)
	 * @return array $objectList list of selected objects
	 */
	public function getBillFee($pricelevel = 'standard') {
		if (!$this->procedure_order_id) 
			throw new \Exception('mdtsOrderItem::_fetchCharges - no procedure order item record active.');

		// Retrieve the procedure record
		$binds = array($this->procedure_code, $this->lab_id);
		$sql = "SELECT `standard_code` FROM `procedure_type` WHERE ";
		$sql .= "`procedure_code` = ? AND `lab_id` = ? AND (`procedure_type` = 'ord' OR `procedure_type` = 'pro')";
		$res = sqlQuery($sql,$binds);

		// Format the cpt4 codes if found
		if (!$res || empty($res['standard_code'])) return false;
		$cpt4_string = str_ireplace("cpt4:", "", $res['standard_code']); // remove CPT4:
		$cpt4_string = preg_replace("/(.*?)[\,|\s](.*)/", "$1", $cpt4_string); // retain up to , or sp
		$cpt4_array = explode('-', $cpt4_string);
		$cpt4_code = $cpt4_array[0];
		$cpt4_mod = $cpt4_array[1];
			
		// Retrieve pricing information
		$binds = array($pricelevel, $cpt4_code);
		$sql =  "SELECT ct.`ct_id`, co.`id` AS code_id, pr1.`pr_price` AS std_price, pr2.`pr_price` AS the_price, pr2.`pr_id` ";
		$sql .= "FROM `code_types` ct, `codes` co ";
		$sql .= "LEFT JOIN `prices` pr1 ON co.`id` = pr1.`pr_id` AND pr1.`pr_level` LIKE 'standard' ";
		$sql .= "LEFT JOIN `prices` pr2 ON co.`id` = pr2.`pr_id` AND pr2.`pr_level` LIKE ? ";
		$sql .= "WHERE ct.`ct_key` LIKE 'CPT4' AND ct.`ct_id` = co.`code_type` AND co.`code` LIKE ?";
		if ($cpt4_mod) {
			array_push($binds,$cpt4_mod);
			$sql .= " AND co.`modifier` LIKE ?";
		}
		$res = sqlQuery($sql,$binds);

		// Determine price
		$fee = 0; // default
		if ($res && $res['ct_id'] && $res['code_id']) {
			$fee = $res['std_price']; // assume standard
			if ($res['pr_id']) $fee = $res['the_price']; // found custom price
		}
		
		// Update object data
		$this->bill_cpt4 = $cpt4_code;
		$this->bill_cpt4_mod = $cpt4_mod;
		$this->bill_fee = $fee;
		
		return;
	}
	
	/**
	 * Returns an array list of procedure order item objects associated with the
	 * given order and filtered by lab type.
	 *
	 * @static
	 * @param int $proc_order_id Procedure order identifier (parent order)
	 * @param bool $ext return only certain types of labs
	 * @return array $objectList list of selected objects
	 */
	public static function filterItemList($proc_order_id = false, $type = false) {
		if (!$proc_order_id) return false;

		$binds[] = $proc_order_id;
		$query = "SELECT `procedure_order_seq` FROM `procedure_order_code` ";
		$query .= "WHERE `procedure_order_id` = ? AND `lab_id` IN ";
		if ($type) {
			$binds[] = $type;
			$query .= "(SELECT `ppid` FROM `procedure_providers` WHERE `type` LIKE ?) ";
		}
		$query .= "ORDER BY procedure_order_seq";

		$results = sqlStatement($query, $binds);

		$objectList = array();
		while ($data = sqlFetchArray($results)) {
			$objectList[] = new OrderItem($proc_order_id,$data['procedure_order_seq']);
		}

		return $objectList;
	}

	/**
	 * Returns an array list of procedure order item objects associated with the
	 * given order.
	 *
	 * @static
	 * @param int $proc_order_id Procedure order identifier (parent order)
	 * @return array $objectList list of selected objects
	 */
	public static function fetchItemList($proc_order_id = false) {
		if (!$proc_order_id) return false;

		$query = "SELECT procedure_order_seq FROM procedure_order_code ";
		$query .= "WHERE procedure_order_id = ? ";
		$query .= "ORDER BY procedure_order_seq";

		$results = sqlStatement($query, array($proc_order_id));

		$objectList = array();
		while ($data = sqlFetchArray($results)) {
			$objectList[] = new OrderItem($proc_order_id,$data['procedure_order_seq']);
		}

		return $objectList;
	}

	/**
	 * Returns an array list of procedure order item keys (seq num) associated with the
	 * given procedure code item from an order.  Used to match results.
	 *
	 * @static
	 * @param int $proc_order_id Procedure order identifier (parent order)
	 * @return array $objectList list of selected objects
	 */
	public static function fetchOrderItems($proc_order_id = false, $all = false) {
		if (!$proc_order_id) return false;

		$query = "SELECT * FROM procedure_order_code ";
		$query .= "WHERE procedure_order_id = ? ";
		if (!$all) $query .= "AND procedure_source = 1 ";
		$query .= "ORDER BY procedure_order_seq";

		$results = sqlStatement($query, array($proc_order_id));

		$orderedList = array();
		while ($data = sqlFetchArray($results)) {
			$orderedList[$data['procedure_code']] = $data['procedure_order_seq'];
		}

		return $orderedList;
	}
		
	/**
	 * Delete old detail records for order;
	 *
	 * @static
	 * @param string procedure order number
	 */
	public static function removeItems($order_number) {
		if (empty($order_number)) return;
		sqlStatement("DELETE FROM `procedure_order_code` WHERE `procedure_order_id` = ?",array($order_number));
		sqlStatement("DELETE FROM `procedure_answers` WHERE `procedure_order_id` = ?",array($order_number));
	}
	
	/**
	 * Returns an array of valid database fields for the object.
	 *
	 * @static
	 * @return array list of database field names
	 */
	public static function listFields() {
		$fields = sqlListFields('procedure_order_code');
		return $fields;
	}
}

?>