Atlas
=====

Twitter automation script for social media campaigns

Twitter API should be configured for Read/Write access in order to be able to send tweets

Requires local_conf.php file if not deployed on Cloudcontrol.com


	<?php
		$config = array(
			'CONFIG' => array(
				'CONFIG_VARS' => array(
				
					'en_publickey' => '',
					'en_privatekey' => '',
					'en_authtoken' => '',
					'en_authsecrettoken' => '',
					
					'es_publickey' => '',
					'es_privatekey' => '',
					'es_authtoken' => '',
					'es_authsecrettoken' => '',
					
					'fr_publickey' => '',
					'fr_privatekey' => '',
					'fr_authtoken' => '',
					'fr_authsecrettoken' => '',
					
					'ipwhitelist' => '127.0.0.1'
				)	
			),
			'MONGOLAB' => array(
				'MONGOLAB_URI' => '' // fully qualified URI, including trailing database name
			)
		);
	?>

