<?php

//ini_set('display_errors', 1);
require_once(__DIR__ . '/civicrm_addressbook_instance.php');

class civicrm_addressbook extends rcube_plugin {

	public $task = 'mail|addressbook';

	public function init() {
		$this->add_hook('addressbooks_list',   array($this, 'addressbooks_list'));
		$this->add_hook('addressbook_get', array($this, 'addressbook_get'));

		$rcmail = rcmail::get_instance();
		$config = $rcmail->config;
	    $sources= (array) $config->get('autocomplete_addressbooks', array());
		$sources[] = 'civicrm';
		$config->set('autocomplete_addressbooks', $sources);
	}
	
	public function addressbook_get($addressbook) {
		if ($addressbook['id'] == 'civicrm') {
			$addressbook['instance'] = new civicrm_addressbook_instance();
		}
		return $addressbook;
	}

	public function addressbooks_list($addressbooks) {
		$addressbooks['sources']['civicrm'] = array(
			'id' => 'civicrm',
			'name' => 'CiviCRM',
			'readonly' => true,
		); 
		return $addressbooks;
	}

}

?>
