<?php
set_time_limit(300);
if (getenv('CRED_FILE')) {
	
	# cloudcontrol config parse
	$creds_string = file_get_contents($_ENV['CRED_FILE'], false);
	if ($creds_string == false) {
		die('FATAL: Could not read credentials file');
	}
	
	# the file contains a JSON string, decode it and return an associative array
	$config = json_decode($creds_string, true);
	
} else {
	
	# load local configuration file
	require_once ('local_conf.php');
}

$config['languages'] = array('en','fr','es');
$config['CONFIG']['CONFIG_VARS']['en_searchterm'] = '%23povertymatch+%23donatenow+%40undp';
$config['CONFIG']['CONFIG_VARS']['en_message'] = 'Thank you for your support to the Philippines! You can make your donation on our website here: http://on.undp.org/teU0U';
$config['CONFIG']['CONFIG_VARS']['es_searchterm'] = '%23povertymatch+%23donatenow+%40pnud';
$config['CONFIG']['CONFIG_VARS']['es_message'] = 'Muchas gracias por su apoyo a las Filipinas! Puedes hacer tu donación en nuestra página web: http://on.undp.org/to4Z0';
$config['CONFIG']['CONFIG_VARS']['fr_searchterm'] = '%23povertymatch+%23donatenow+%40pnud_fr';
$config['CONFIG']['CONFIG_VARS']['fr_message'] = 'Merci pour votre soutien aux Philippines! Faites un don ici: http://on.undp.org/todQk';

?>