<?php

class civicrm_addressbook_instance extends rcube_addressbook {

	public $primary_key = 'ID';
    public $groups        = true;
    public $readonly      = true;
    public $ready         = true;
    public $list_page     = 1;
    public $page_size     = 50;
    public $sort_col      = 'name';
    public $sort_order    = 'ASC';
    public $date_cols     = array();
    public $coltypes      = array(
        'name'      => array('limit'=>1),
        'email'     => array('limit'=>1),
		'phone'     => array('limit'=>1),
    );

	private $filter;

	private $result;

	private $gid = 0;

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
		$db = $this->getDb();
       	$result = $this->count();
		$offset = $this->page_size * ($this->list_page-1);
		$clause = $this->filter ? (' (' . $this->filter . ') '):' 1 ';

		$groupJoin = '';
		$groupClause = '';
		if (!empty($this->gid)) {
			$groupJoin = " INNER JOIN civicrm_group_contact ON civicrm_group_contact.contact_id = civicrm_contact.id ";
			$groupClause = " AND (civicrm_group_contact.status = 'Added' AND civicrm_group_contact.group_id = ".$db->escape($this->gid)." )";
		} 
		$db->query("
			SELECT civicrm_contact.id as id, civicrm_contact.display_name, civicrm_email.email, civicrm_phone.phone
			FROM civicrm_contact 
			INNER JOIN civicrm_email ON civicrm_contact.id = civicrm_email.contact_id AND civicrm_email.is_primary = 1
			{$groupJoin}
			LEFT JOIN civicrm_phone ON civicrm_contact.id = civicrm_phone.contact_id AND civicrm_phone.is_primary = 1
			WHERE {$clause}
			{$groupClause}
			AND civicrm_contact.is_deleted = 0 AND civicrm_contact.is_deceased = 0 
			ORDER BY civicrm_contact.display_name, civicrm_email.email
			LIMIT {$offset}, {$this->page_size}
			");
		while ($sql_arr = $db->fetch_assoc()) {		
			$record = array(
				'ID' => $sql_arr['id'],
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
		$db = $this->getDb();
		if ($fields == '*') {
			$this->set_search_set($db->ilike('civicrm_contact.display_name', $value . '%')." OR ".$db->ilike('civicrm_email.email', $value . '%'));
		} elseif ($fields == 'ID') {
            $ids     = !is_array($value) ? explode(self::SEPARATOR, $value) : $value;
            $ids     = $db->array2list($ids, 'integer');
			$this->set_search_set('`civicrm_contact`.`id` IN ('.$ids.')');
		} else {
			$wheres = array();
			foreach($fields as $fieldName) {
				switch($fieldName) {
					case 'name':
						$wheres[] = $db->ilike('civicrm_contact.display_name', $value . '%');
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
		$db = $this->getDb();		
		$clause = $this->filter ? (' (' . $this->filter . ') '):' 1 ';

		$groupJoin = '';
		$groupClause = '';
		if (!empty($this->gid)) {
			$groupJoin = " INNER JOIN civicrm_group_contact ON civicrm_group_contact.contact_id = civicrm_contact.id ";
			$groupClause = " AND (civicrm_group_contact.status = 'Added' AND civicrm_group_contact.group_id = ".$db->escape($this->gid)." )";
		}
		$db->query("
			SELECT COUNT(*) as total
			FROM civicrm_contact 
			INNER JOIN civicrm_email ON civicrm_contact.id = civicrm_email.contact_id AND civicrm_email.is_primary = 1
			{$groupJoin}
			LEFT JOIN civicrm_phone ON civicrm_contact.id = civicrm_phone.contact_id AND civicrm_phone.is_primary = 1
			WHERE {$clause}
			{$groupClause}
			AND civicrm_contact.is_deleted = 0 AND civicrm_contact.is_deceased = 0 
			ORDER BY civicrm_contact.display_name, civicrm_email.email
			");
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
	    $this->result = new rcube_result_set(0);
    	$record = false;
		$db = $this->getDb();
		$db->query("
			SELECT civicrm_contact.id as id, civicrm_contact.display_name, civicrm_email.email, civicrm_phone.phone
			FROM civicrm_contact 
			INNER JOIN civicrm_email ON civicrm_contact.id = civicrm_email.contact_id AND civicrm_email.is_primary = 1
			LEFT JOIN civicrm_phone ON civicrm_contact.id = civicrm_phone.contact_id AND civicrm_phone.is_primary = 1
			WHERE civicrm_contact.is_deleted = 0 AND civicrm_contact.is_deceased = 0 AND civicrm_contact.id=? ", $id);
		if ($sql_arr = $db->fetch_assoc()) {
			$this->result = new rcube_result_set(0);		
			$record = array(
				'ID' => $sql_arr['id'],
				'name' => $sql_arr['display_name'],
				'email' => $sql_arr['email'],
				'phone' => $sql_arr['phone'],
			);
				$this->result->add($record);
		}
		return $assoc && $record ? $record : $this->result;
	}

	/**
     * Setter for the current group
     * (empty, has to be re-implemented by extending class)
     */
    function set_group($gid) { 
		$this->gid = $gid;
	}

    /**
     * List all active contact groups of this source
     *
     * @param string  Optional search string to match group name
     * @param int     Search mode. Sum of self::SEARCH_*
     *
     * @return array  Indexed list of contact groups, each a hash array
     */
    function list_groups($search = null, $mode = 0)
    {
		$groups = array();
        $db = $this->getDb();
		$clause = "";
		if ($search) {
			$clause = " AND " . $db->ilike('title', $search . '%');
		}

		$db->query("SELECT * FROM civicrm_group WHERE is_active = 1 and is_hidden = 0 {$clause} ORDER BY title");
		
		while ($sql_arr = $db->fetch_assoc()) {
			$record = array(
				'ID' => $sql_arr['id'],
				'name' => $sql_arr['title']
			);
			$groups[] = $record;
		}
        return $groups;
    }

    /**
     * Get group properties such as name and email address(es)
     *
     * @param string Group identifier
     * @return array Group properties as hash array
     */
    function get_group($group_id)
    {
		$groups = array();
        $db = $this->getDb();
		$db->query("
			SELECT civicrm_contact.id as id, civicrm_contact.display_name, civicrm_email.email, civicrm_phone.phone
			FROM civicrm_contact 
			INNER JOIN civicrm_email ON civicrm_contact.id = civicrm_email.contact_id AND civicrm_email.is_primary = 1
			INNER JOIN civicrm_group_contact ON civicrm_group_contact.contact_id = civicrm_contact.id
			INNER JOIN civicrm_group ON civicrm_group.id = civicrm_group_contact.group_id
			LEFT JOIN civicrm_phone ON civicrm_contact.id = civicrm_phone.contact_id AND civicrm_phone.is_primary = 1
			WHERE civicrm_group.is_active = 1 
			AND civicrm_group_contact.status = 'Added'
			AND civicrm_group.id=? 
			ORDER BY title", $group_id);
		while ($sql_arr = $db->fetch_assoc()) {
			$record = array(
				'ID' => $sql_arr['id'],
				'name' => $sql_arr['display_name'],
				'email' => $sql_arr['email'],
				'phone' => $sql_arr['phone'],
			);
			$groups[$group_id][] = $record;
		}
        return $groups;
    }

	/**
     * Get group assignments of a specific contact record
     *
     * @param mixed Record identifier
     *
     * @return array List of assigned groups as ID=>Name pairs
     * @since 0.5-beta
     */
    function get_record_groups($id)
    {
		$groups = array();
        $db = $this->getDb();
		$db->query("
			SELECT civicrm_group.title, civicrm_group.id 
			FROM civicrm_group_contact
			INNER JOIN civicrm_group ON civicrm_group.id = civicrm_group_contact.group_id
			WHERE civicrm_group.is_active = 1 
			AND civicrm_group_contact.status = 'Added'
			AND civicrm_group_contact.contact_id=? 
			ORDER BY title", $id);
		while ($sql_arr = $db->fetch_assoc()) {
			$groups[$sql_arr['id']] = $sql_arr['title'];
		}
        return $groups;
    }

	private function getDb() {
		$rcmail = rcmail::get_instance();
		$dsn = $rcmail->config->get('civicrm_db_dsn');
        $db = rcube_db::factory($dsn, '', false);
		return $db;
	}

}

?>
