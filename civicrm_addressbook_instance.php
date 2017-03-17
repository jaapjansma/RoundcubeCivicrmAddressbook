<?php

class civicrm_addressbook_instance extends rcube_addressbook {

	public $primary_key = 'id';
    public $groups        = false;
    public $readonly      = true;
    public $ready         = false;
    public $list_page     = 1;
    public $page_size     = 50;
    public $sort_col      = 'name';
    public $sort_order    = 'ASC';
    public $date_cols     = array();
    public $coltypes      = array(
        'name'      => array('limit'=>1),
        'firstname'      => array('limit'=>1),
        'surname'      => array('limit'=>1),
        'email'     => array('limit'=>1),
    );

	private $filter;

	private $result;

	/**
     * Returns addressbook name (e.g. for addressbooks listing)
     */
    function get_name() {
		return 'civicrm';
	}

    /**
     * Save a search string for future listings
     *
     * @param mixed Search params to use in listing method, obtained by get_search_set()
     */
    function set_search_set($filter) {
		$this->filter = $filter;
	}

    /**
     * Getter for saved search properties
     *
     * @return mixed Search properties used by this class
     */
    function get_search_set() {
		return $this->filter;
	}

    /**
     * Reset saved results and search parameters
     */
    function reset() {
		$this->result = null;
		$this->filter = null;
	}

	/**
     * List the current set of contact records
     *
     * @param  array  List of cols to show
     * @param  int    Only return this number of records, use negative values for tail
     * @return array  Indexed list of contact records, each a hash array
     */
    function list_records($cols=null, $subset=0) {
		$offset = $this->page_size * ($this->list_page-1);
		$clause = $this->filter ? (' (' . $this->filter . ') AND '):' ';

        $result = $this->count();
		$db = $this->getDb();
		$db->query("
			SELECT civicrm_contact.id as id, civicrm_contact.display_name, civicrm_contact.first_name, civicrm_contact.last_name, civicrm_email.email, civicrm_phone.phone
			FROM civicrm_contact 
			INNER JOIN civicrm_email ON civicrm_contact.id = civicrm_email.contact_id AND civicrm_email.is_primary = 1
			LEFT JOIN civicrm_phone ON civicrm_contact.id = civicrm_phone.contact_id AND civicrm_phone.is_primary = 1
			WHERE {$clause} 
			civicrm_contact.is_deleted = 0 AND civicrm_contact.is_deceased = 0 
			ORDER BY civicrm_contact.display_name, civicrm_email.email
			LIMIT {$offset}, {$this->page_size}
			");
		while ($sql_arr = $db->fetch_assoc()) {		
			$record = array(
				'ID' => $sql_arr['id'],
				'name' => $sql_arr['display_name'],
				'surname' => $sql_arr['last_name'],
				'firstname' => $sql_arr['first_name'],
				'email:home' => $sql_arr['email'],
				'phone:home' => $sql_arr['phone'],
			);
			$result->add($record);
		}
 
		return $result;
	}

    /**
     * Search records
     *
     * @param array   List of fields to search in
     * @param string  Search value
     * @param int     Search mode. Sum of self::SEARCH_*.
     * @param boolean True if results are requested, False if count only
     * @param boolean True to skip the count query (select only)
     * @param array   List of fields that cannot be empty
     *
     * @return object rcube_result_set List of contact records and 'count' value
     */
    function search($fields, $value, $mode=0, $select=true, $nocount=false, $required=array()) {
		$db = $this->getDb();
		if ($fields == '*') {
			$this->set_search_set($db->ilike('civicrm_contact.display_name', $value . '%')." OR ".$db->ilike('civicrm_email.email', $value . '%'));
		} else {
			$wheres = array();
			foreach($fields as $fieldName) {
				switch($fieldName) {
					case 'name':
						$wheres[] = $db->ilike('civicrm_contact.display_name', $value . '%');
						break;
					case 'firstname':
						$wheres[] = $db->ilike('civicrm_contact.first_name', $value . '%');
						break;
					case 'surname':
						$wheres[] = $db->ilike('civicrm_contact.last_name', $value . '%');
						break;
					case 'email':
						$wheres[] = $db->ilike('civicrm_email.email', $value . '%');
						break;
				}
			}
			if (count($wheres)) {
				$this->set_search_set(join(' OR ', $wheres));
			}
		}
		return $this->list_records();
	}

    /**
     * Count number of available contacts in database
     *
     * @return rcube_result_set Result set with values for 'count' and 'first'
     */
    function count() {
		$clause = $this->filter ? (' (' . $this->filter . ') AND '):' ';

		$db = $this->getDb();
		$db->query("
			SELECT COUNT(*) as total
			FROM civicrm_contact 
			INNER JOIN civicrm_email ON civicrm_contact.id = civicrm_email.contact_id AND civicrm_email.is_primary = 1
			LEFT JOIN civicrm_phone ON civicrm_contact.id = civicrm_phone.contact_id AND civicrm_phone.is_primary = 1
			WHERE {$clause} 
			civicrm_contact.is_deleted = 0 AND civicrm_contact.is_deceased = 0 
			ORDER BY civicrm_contact.display_name, civicrm_email.email
			");
		$count = 0;
		if ($sql_arr = $db->fetch_assoc()) {
			$count = $sql_arr['total'];
		}
		return new rcube_result_set($count, ($this->list_page-1) * $this->page_size);
	}

    /**
     * Return the last result set
     *
     * @return rcube_result_set Current result set or NULL if nothing selected yet
     */
    function get_result() {
		return $this->result;
	}

    /**
     * Get a specific contact record
     *
     * @param mixed   Record identifier(s)
     * @param boolean True to return record as associative array, otherwise a result set is returned
     *
     * @return rcube_result_set|array Result object with all record fields
     */
    function get_record($id, $assoc=false) {
	    $this->result = new rcube_result_set(1);
	    	$record = false;
		$db = $this->getDb();
		$db->query("
			SELECT civicrm_contact.id as id, civicrm_contact.display_name, civicrm_contact.last_name, civicrm_contact.first_name, civicrm_email.email, civicrm_phone.phone
			FROM civicrm_contact 
			INNER JOIN civicrm_email ON civicrm_contact.id = civicrm_email.contact_id AND civicrm_email.is_primary = 1
			LEFT JOIN civicrm_phone ON civicrm_contact.id = civicrm_phone.contact_id AND civicrm_phone.is_primary = 1
			WHERE civicrm_contact.is_deleted = 0 AND civicrm_contact.is_deceased = 0 AND civicrm_contact.id=? ", $id);
		if ($sql_arr = $db->fetch_assoc()) {		
			$record = array(
				'ID' => $sql_arr['id'],
				'name' => $sql_arr['display_name'],
				'surname' => $sql_arr['last_name'],
				'firstname' => $sql_arr['first_name'],
				'email:home' => $sql_arr['email'],
				'phone:home' => $sql_arr['phone'],
			);
				$this->result->add($record);
		}
		return $assoc && $record ? $record : $this->result;
	}

	private function getDb() {
		$rcmail = rcmail::get_instance();
		$dsn = $rcmail->config->get('civicrm_db_dsn');
        $db = rcube_db::factory($dsn, '', false);
		return $db;
}

}

?>
