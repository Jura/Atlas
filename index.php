<?php

require_once ('conf.php');
require_once ('atlas.php');
require_once ('lib\codebird.php');

$lang = filter_input(INPUT_GET, 'lang', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
$prefix = (in_array($lang, array('en','fr','es'))) ? $lang . '_' : 'en_';

$atlas = \Atlas\Atlas::getInstance();

// Go away! Allow only whitelisted IPs to access the script
$ips = explode(',', $config['CONFIG']['CONFIG_VARS']['ipwhitelist']);
if (!in_array($_SERVER['REMOTE_ADDR'], $ips)) {
	$atlas->terminate(403, 'IP ' . $_SERVER['REMOTE_ADDR'] . ' is not allowed');
}

// configure DB connection
$atlas->getConnection($config['MONGOLAB']['MONGOLAB_URI'], $prefix);

// Only one process can run at a time
if ($atlas->isLocked()){
	$atlas->terminate(423);
} else {
	$atlas->lock();
}

// Setup Codebird
\Codebird\Codebird::setConsumerKey($config['CONFIG']['CONFIG_VARS'][$prefix . 'publickey'], $config['CONFIG']['CONFIG_VARS'][$prefix . 'privatekey']);
$cb = \Codebird\Codebird::getInstance();
$cb->setToken($config['CONFIG']['CONFIG_VARS'][$prefix . 'authtoken'], $config['CONFIG']['CONFIG_VARS'][$prefix . 'authsecrettoken']);

// get all authors records ready for sending
$authors = $atlas->getAuthors();

foreach ($authors as $author) {
	$tweet = array(
		'status' => '@' . $author['author'] . ' ' . $config['CONFIG']['CONFIG_VARS'][$prefix . 'message'],
		'in_reply_to_status_id' => $author['status_id']		
	);
	$response = $cb->statuses_update($tweet);

	if ($response->httpstatus != 200) {
		$atlas->shutdown($response->httpstatus, $response->httpstatus . ': Twitter error ' . $response->errors[0]->message);
	} else {
		$atlas->log('Tweet sent: @' . $author['author'],'info');
		$atlas->markAuthorAsSent($author['author']);
	}
}

$searchparams = array(
	'q' => $config['CONFIG']['CONFIG_VARS'][$prefix . 'searchterm'],
	'result_type' => 'recent',
	'count' => 100
);

// include last search information
$search = $atlas->getLastSearchInfo();

if ( count($search) > 0  && isset($search['since_id']) && $search['since_id'] != '') {
	$searchparams['since_id'] = $search['since_id'];
}

// make 100 rounds or until search returns < 100 reulst or hit the rate limit
// twitter search limit is 450 for every 15 minutes but we don't want to exhaust it
for ($i=0; $i<100; $i++) {

	$reply = $cb->search_tweets($searchparams, true);

	if ($reply->httpstatus == 200) {
		
		$atlas->log('Search returned ' . count($reply->statuses) . ' results','info');
		
		if (count($reply->statuses) > 0) {
			
			$authors = array();
			
			foreach ($reply->statuses as $status) {
				
				$authors[$status->user->screen_name] = $status->id_str;
				
			}		
			
			$atlas->saveAuthors($authors);
			
		}
		
		$atlas->saveSearchInfo($reply->search_metadata->max_id_str);
		
		if (count($reply->statuses) < 100) {
			
			$atlas->log('Search finished','info');
			$atlas->shutdown($reply->httpstatus);
			
		} else {
			
			$searchparams['since_id'] = $reply->search_metadata->max_id_str;
			
		}
		
	} else {
		
		$atlas->shutdown($reply->httpstatus, $reply->httpstatus . ' Cannot search Twitter' . print_r($reply, true));
		
	}
} 

// try to shutdown anyway, actually, it should never happen
$atlas->shutdown(555);

?>