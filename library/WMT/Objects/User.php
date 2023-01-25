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
 * Provides standardized processing for user records including providers, clinicians, and
 * address book entries.
 */
class User {
	public $id;
	public $username;
	public $password;  // deprecated
	public $authorized;
	public $info;
	public $source;
	public $fname;
	public $mname;
	public $lname;
	public $prefix;
	public $federaltaxid;
	public $federaldrugid;
	public $upic;
	public $facility;
	public $facility_id;
	public $see_auth;
	public $active;
	public $npi;
	public $title;
	public $specialty;
	public $billname;
	public $email;
	public $url;
	public $assistant;
	public $organization;
	public $valedictory;
	public $street;
	public $streetb;
	public $city;
	public $state;
	public $zip;
	public $street2;
	public $streetb2;
	public $city2;
	public $state2;
	public $zip2;
	public $phone;
	public $fax;
	public $phonew1;
	public $phonew2;
	public $phonecell;
	public $notes;
	public $cal_ui;
	public $taxonomy;
	public $ssi_relayhealth;
	public $calendar;
	public $abook_type;
	public $pwd_expiration_date;
	public $pwd_history1;
	public $pwd_history2;
	public $default_warehouse;
	public $impool;
	public $state_license_number;
	public $newcrop_user_role;
	public $cal_name;
	public $cal_fixed;
	public $add2veradigm = 0;

	// generated values
	public $format_name;

	/**
	 * Constructor for the 'User' class which retrieves the requested
	 * information from the database or creates an empty object.
	 *
	 * @param int $id record identifier
	 * @return object instance of mdtsUser class
	 */
	public function __construct($id = false) {
		// create empty record or retrieve
		if (!$id) return false;

		// system capability check
		$check = sqlQuery("SHOW TABLES LIKE 'provider_supervisor'");
		if ($check) {
			// retrieve data including supervisor
			$query = "
				SELECT u.*, s.`username` AS 'supervisor_username', ps.`supervisor_id`,
					s.`fname` AS 'supervisor_fname', s.`lname` AS 'supervisor_lname' 
				FROM `users` u
				LEFT JOIN `provider_supervisor` ps ON u.`id` = ps.`provider_id`
				LEFT JOIN `users` s ON ps.`supervisor_id` = s.`id`
				WHERE u.`id` = ?";
			$binds = array($id);
			$data = sqlQuery($query,$binds);
		} else {
			// retrieve data without supervisor
			$query = "
				SELECT u.* FROM `users` u
				WHERE u.`id` = ?";
			$binds = array($id);
			$data = sqlQuery($query,$binds);
		}
		
		// self supervisor
		if (strpos(strtoupper($data['specialty']), 'SUPERVISOR') !== false) {
			$data['supervisor_username'] = $data['username'];
			$data['supervisor_fname'] = $data['fname'];
			$data['supervisor_lname'] = $data['lname'];
			$data['supervisor'] = true;
		} else {
			$data['supervisor'] = false;
		}
			
		if ($data && $data['id']) {
			// load everything returned into object
			foreach ($data as $key => $value) {
				$this->$key = $value;
			}
		}
		else {
			throw new \Exception('mdtsUser::_construct - no record with user id ('.$id.').');
		}

		// preformat commonly used data elements
		$this->date = (strtotime($this->date) !== false)? date('Y-m-d H:i:s',strtotime($this->date)) : date('Y-m-d H:i:s');

		$this->format_name = ($this->title)? "$this->title " : "";
		$this->format_name .= ($this->fname)? "$this->fname " : "";
		$this->format_name .= ($this->mname)? substr($this->mname,0,1).". " : "";
		$this->format_name .= ($this->lname)? "$this->lname " : "";

		return;
	}

	public function add2veradigm() {
		sqlQuery('UPDATE `users` SET `add2veradigm` = 1 WHERE `id` = ? LIMIT 1', [$this->id]);
	}

	/**
	 * Retrieve list of user objects
	 *
	 * @static
	 * @parm string $facility - id of a specific facility
	 * @param boolean $active - active status flag
	 * @return array $list - list of provider objects
	 */
	public static function fetchUsers($active=true, $type='') {
		$query = "SELECT `id` FROM `users` WHERE `facility_id` > 0 AND `username` != '' ";
		if ($active) $query .= "AND `active` = 1 ";
		if ($type == 'provider') {
			$query .= "AND `npi` IS NOT NULL AND `npi` != '' ";
			$query .= "AND `authorized` = 1 ";
		}
		$query .= "ORDER BY lname, fname, mname";

		// collect results
		$list = array();
		$result = sqlStatementNoLog($query);
		while ($record = sqlFetchArray($result)) {
			$list[] = new User($record['id']);
		}

		return $list;
	}

	/**
	 * Returns a user object for the given user name.
	 *
	 * @static
	 * @param string $name username
	 * @param bool $active active items only flag
	 * @return array $object single user object
	 */
	public static function fetchUserName($name=false, $active=false) {
		if (!$name) {
			throw new \Exception('mdtsUser::fetchUserName - missing user name parameter');
		}
		
		$query = "SELECT `id` FROM `users` WHERE `username` LIKE ? ";
		if ($active) $query .= "AND `active` = 1 ";
		$binds = array($name);

		// run the query
		$data = sqlQuery($query, $binds);

		// validate
		if (!$data || !$data['id']) {
			throw new \Exception('mdtsUser::fetchUserName - no record with user name ('.$name.').');
		}
		
		// create the object
		return new User($data['id']);
	}

	/**
	 * Returns a user object for the given npi number.
	 *
	 * @static
	 * @param string $npi provider npi number
	 * @param bool $active active items only flag
	 * @return array $object single user object
	 */
	public static function fetchUserNPI($npi=false, $active=false) {
		if (!$npi) {
			throw new \Exception('mdtsUser::fetchUserNPI - missing user NPI number parameter');
		}
		
		$query = "SELECT `id` FROM `users` WHERE `npi` LIKE ? ";
		if ($active) $query .= "AND `active` = 1 ";
		$binds = array($name);

		// run the query
		$data = sqlQuery($query, $binds);

		// validate
		if (!$data || !$data['id']) return false;
		
		// create the object
		return new User($data['id']);
	}

	/**
	 * Build selection list from table data.
	 *
	 * @param int $id - current entry id
	 */
	public static function getOptions($username, $default='', $active=true) {
		$result = '';

		// create default if needed
		if ($default) {
			$result .= "<option value='' ";
			$result .= (!$username || $username == '')? "selected='selected'" : "";
			$result .= ">".$default."</option>\n";
		}

		// get clinicians
		$list = self::fetchUsers($active);

		// build options
		foreach ($list AS $user) {
			$result .= "<option value='" . $user->username . "' ";
			if ($username == $user->username)
				$result .= "selected=selected ";
			$result .= ">" . $user->format_name ."</option>\n";
		}

		return $result;
	}

	/**
	 * Build selection list from table data.
	 *
	 * @param int $id - current entry id
	 */
	public static function showProvOpts($id, $default='', $active=true) {
		$result = '';

		// create default if needed
		if ($default) {
			$result .= "<option value='' ";
			$result .= (!$id || $id == '')? "selected='selected'" : "";
			$result .= ">".$default."</option>\n";
		}

		// get clinicians
		$list = self::fetchUsers($active, 'provider');

		// build options
		foreach ($list AS $user) {
			$result .= "<option value='" . $user->id . "' ";
			if ($id == $user->id)
				$result .= "selected=selected ";
			$result .= ">" . $user->format_name ."</option>\n";
		}

		echo $result;
		return;
	}

	/**
	 * Echo selection option list from table data.
	 *
	 * @param id - current entry id
	 * @param result - string html option list
	 */
	public static function showOptions($username, $default='', $active=true) {
		echo self::getOptions($username, $default, $active);
	}

	/**
	 * Build selection list from table data.
	 *
	 * @param int $id - current entry id
	 */
	public static function getIdOptions($id, $default='', $active=true) {
		$result = '';

		// create default if needed
		if ($default) {
			$result .= "<option value='' ";
			$result .= (!$id || $id == '')? "selected='selected'" : "";
			$result .= ">".$default."</option>\n";
		}

		// get clinicians
		$list = self::fetchUsers();

		// build options
		foreach ($list AS $user) {
			$result .= "<option value='" . $user->id . "' ";
			if ($id == $user->id)
				$result .= "selected=selected ";
			$result .= ">" . $user->format_name ."</option>\n";
		}

		return $result;
	}

	/**
	 * Return formatted user name.
	 *
	 * @param int $id - current entry id
	 */
	public static function getUserName($id) {
		$user = new User($id);
		return $user->format_name;
	}

	/**
	 * Echo selection option list from table data.
	 *
	 * @param id - current entry id
	 * @param result - string html option list
	 */
	public static function showIdOptions($id, $default='', $active=true) {
		echo self::getIdOptions($id, $default, $active);
	}

}

?>
