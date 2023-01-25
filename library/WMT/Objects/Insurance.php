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
 * Provides a representation of the insurance information. Fields are statically defined
 * but are stored in multiple database tables. The information is integrated  
 */
class Insurance {
	// generated values
	public $subscriber_format_name;
	public $subscriber_birth_date;
	public $subscriber_age;
	
	/**
	 * Constructor for the 'mdtsInsurance' class which retrieves the requested 
	 * patient insurance information from the database or creates an empty object.
	 * 
	 * @param int $id insurance data record identifier
	 * @return object instance of patient insurance class
	 */
	public function __construct($id = false) {
		if(!$id) return false;

		$query = "SELECT p.*, a.*, i.*, c.name AS company_name, c.id AS company_id, c.attn AS contact, c.cms_id, c.ins_type_code ";
		$query .= "FROM insurance_data i ";
		$query .= "LEFT JOIN insurance_companies c ON i.provider = c.id ";
		$query .= "LEFT JOIN addresses a ON a.foreign_id = c.id ";
		$query .= "LEFT JOIN phone_numbers p ON p.foreign_id = c.id ";
		$query .= "WHERE i.id = ? LIMIT 1 ";
		
		$data = sqlQuery($query,array($id));
		if ($data && $data['provider']) {
			// load everything returned into object
			foreach ($data as $key => $value) {
				$this->$key = $value;
			}
		}
		else {
			return false;
		}
		
		if ($this->subscriber_DOB && strtotime($this->subscriber_DOB)) { // strtotime returns FALSE on date error
			$this->subscriber_age = floor( (strtotime('today') - strtotime($this->subscriber_DOB)) / 31556926);
			$this->subscriber_birth_date = date('Y-m-d', strtotime($this->subscriber_DOB));
		}
		
		return;
	}	

	/**
	 * Retrieve a insurance object by PID value. Uses the base constructor for the 'insurance' class 
	 * to create and return the object. 
	 * 
	 * @static
	 * @param int $id patient record identifier
	 * @param string $type 'primary', 'secondary', 'tertiary'
	 * @return array object list of insurance objects
	 */
	public static function getPid($pid, $type = null) {
		$type = strtolower($type);
		
		if(! $pid)
			throw new \Exception('mdtsInsurance::getPid - no patient identifier provided.');
		
		$query = "SELECT id, type, date FROM insurance_data WHERE pid = ? ";
		if ($type) $query .= "AND type = ? ";
		$query .= "AND provider != '' AND provider IS NOT NULL AND provider != '0' ";
		$query .= "ORDER BY date DESC ";

		$list = array();
		$params = array();
		$params[] = $pid;
		if ($type) $params[] = $type;

		$results = sqlStatement($query,$params);
		while ($data = sqlFetchArray($results)) {
			if ($data['type'] == 'primary' && !$list[0]) $list[0] = new Insurance($data['id']);
			if ($data['type'] == 'secondary' && !$list[1]) $list[1] = new Insurance($data['id']);
			if ($data['type'] == 'tertiary' && !$list[2]) $list[2] = new Insurance($data['id']);
		}
		
		switch ($type) {
			case 'primary': 
				return $list[0];
				break;
			case 'secondary':
				return $list[1];
				break;
			case 'tertiary':
				return $list[2];
				break;
		}

		return $list;
	}
	
	/**
	 * Retrieve a insurance object by PID value that was active on a given date. 
	 * Uses the base constructor for the 'insurance' class 
	 * to create and return the object. 
	 * 
	 * @static
	 * @param int $pid patient record identifier
	 * @param date $date insurance as of date
	 * @param string $type 'primary', 'secondary', 'tertiary'
	 * @return array object list of insurance objects
	 */
	public static function getPidInsDate($pid, $date, $type = null) {
		if(! $pid)
			throw new \Exception('mdtsInsurance::getPidInsDate - no patient identifier provided.');

		if(!$date || strtotime($date) === false) // strtotime returns FALSE or -1 on invalid date
			throw new \Exception('mdtsInsurance::getPidInsDate - invalid date provided.');

		$query = "SELECT id, type, date FROM insurance_data WHERE pid = ? ";
		$query .= "AND provider != '' AND provider IS NOT NULL "; 
		$query .= "AND date <= ? ";
		if ($type) $query .= "AND type = ? ";
		$query .= "ORDER BY date DESC ";

		$list = array();
		$params = array();
		$params[] = $pid;
		$params[] = date('Y-m-d',strtotime($date));
		if ($type) $params[] = strtolower($type);
		
		$results = sqlStatement($query,$params);
		while ($data = sqlFetchArray($results)) {
			if ($data['type'] == 'primary' && !$list[0]) $list[0] = new Insurance($data['id']);
			if ($data['type'] == 'secondary' && !$list[1]) $list[1] = new Insurance($data['id']);
			if ($data['type'] == 'tertiary' && !$list[2]) $list[2] = new Insurance($data['id']);
		}  

		return $list;
	}
	
	/**
	 * Retrieve a single insurance company.
	 * 
	 * @static
	 * @param int $provider insurance provider identifier
	 * @return array insurance company data record
	 */
	public static function getCompany($provider) {
		if(! $provider)
			throw new \Exception('mdtsInsurance::getCompany - no insurance company provider identifier.');
		
		$record = array();
		if ($provider == 'self') {
			$record['name'] = "Self Insured";
		}
		else {
			$query = "SELECT ia.*, ip.*, ic.id AS company_id, ic.name AS company_name FROM insurance_companies ic ";
			$query .= "LEFT JOIN addresses ia ON ia.foreign_id = ic.id ";
			$query .= "LEFT JOIN phone_numbers ip ON ip.foreign_id = ic.id ";
			$query .= "WHERE ic.id = ? LIMIT 1 ";
			$record = sqlQuery($query,array($provider));
		}
				
		return $record;
	}
}



?>