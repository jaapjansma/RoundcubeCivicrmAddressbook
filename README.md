# RoundcubeCivicrmAddressbook

Adds a global addressbook to Roundcube with contacts from CiviCRM. Requires database access to the civicrm database.

## Installation instructions

	cd your-roundcube-folder/
	cd plugins
	git clone https://github.com/jaapjansma/RoundcubeCivicrmAddressbook.git civicrm_addressbook
	

Edit *config/config.inc.php*:

	$config['plugins'] = array(..., 'civicrm_addressbook');
	$config['civicrm_db_dsn'] = 'mysql://civicrm_db_user:civicrm_db_password@localhost/civicrm_db';



	
