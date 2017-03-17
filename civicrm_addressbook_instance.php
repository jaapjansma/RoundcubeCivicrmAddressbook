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
        'email'     => array('limit'=>1)
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
        $result = new rcube_result_set();
		$db = $this->getDb();
		$db->query("
			SELECT * FROM civicrm_contact 
			INNER JOIN civicrm_email ON civicrm_contact.id = civicrm_email.contact_id AND civicrm_email.is_primary = 1
			LEFT JOIN civicrm_phone ON civicrm_contact.id = civicrm_phone.contact_id AND civicrm_phone.is_primary = 1
			WHERE civicrm_contact.is_deleted = 0 AND civicrm_contact.is_deceased = 0 AND civicrm_contact.id=? ", $id);
		while ($ret = $db->fetch_assoc()) {		
			$record = array(
				'id' => $sql_arr['id'],
				'name' => $sql_arr['display_name'],
				'email' => $sql_arr['email'],
				'phone' => $sql_arr['phone'],
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
		return $this->list_records();
	}

    /**
     * Count number of available contacts in database
     *
     * @return rcube_result_set Result set with values for 'count' and 'first'
     */
    function count() {
		return new rcube_result_set(1);
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
		$result = new rcube_result_set();
		$db = $this->getDb();
		$db->query("
			SELECT * FROM civicrm_contact 
			INNER JOIN civicrm_email ON civicrm_contact.id = civicrm_email.contact_id AND civicrm_email.is_primary = 1
			LEFT JOIN civicrm_phone ON civicrm_contact.id = civicrm_phone.contact_id AND civicrm_phone.is_primary = 1
			WHERE civicrm_contact.is_deleted = 0 AND civicrm_contact.is_deceased = 0 AND civicrm_contact.id=? ", $id);
		if ($sql_arr = $db->fetch_assoc()) {		
			$record = array(
				'id' => $sql_arr['id'],
				'name' => $sql_arr['display_name'],
				'email' => $sql_arr['email'],
				'phone' => $sql_arr['phone'],
		);
		$result->add($record);
		return $assoc ? $record : $this->result;
	}

	private function getDb() {
		$rcmail = rcmail::get_instance();
		$dsn = $rcmail->config->get('civicrm_db_dsn');
        $db = rcube_db::factory($dsn, '', false);
		return $db;
	}

}

?>
