<?php

namespace Atlas;

class Atlas {
	
    /**
     * The current singleton instance
     */
	private static $_instance = null;
	
	/**
	 * MongoDB connection
	 */
	private static $_db = null;
	
	/**
	 * MongoDB collections prefix
	 */
	private static $_prefix = null;
	
	/**
	 * HTTP error codes
	 */
	private static $_http_codes = array(
		200 => 'OK',
		400 => 'Twitter error',
		403 => 'Forbidden',
		423 => 'Locked',
		429 => 'Too many requests',
		500 => 'Service unavailable',
		555 => 'Winter is coming'			
	);
	
	/**
	 * Lock timeout in DateInterval format, 1 hour by default
	 */
	private static $_lock_timeout = 'PT60M';
	
	/**
	 * Debugging mode, whether to dump raw error output to browser
	 */
	private static $_debug = true;
	
	/**
     * Returns singleton class instance
     * Always use this method unless you're working with multiple authenticated users at once
     *
     * @return Codebird The instance
     */
    public static function getInstance()
    {
        if (self::$_instance == null) {
            self::$_instance = new self;
        }
        return self::$_instance;
    }
    
    /**
     *  Initiate DB connection
     *  
     *  @param string $uri Fully qualified MongoDB URI including db name
     *  
     *  @return \MongoClient 
     */
    public static function getConnection($uri=null, $prefix='') {
    	if (self::$_db == null) {
    		$con = new \MongoClient($uri);
    		$db = explode('/', $uri);
    		self::$_db = $con->selectDB(end($db));
    		self::$_prefix = $prefix;
    	}
    	return self::$_db;
    }
    
    
    /**
     * End request end return HTTP standard code & message
     * 
     * @param int $error_code HTTP error code to display
     * @param string $message Optional message to log/display
     * 
     * @return void
     */
    public static function terminate($error_code = 500, $message = '') {
    	
    	if (!isset(self::$_http_codes[$error_code])) {
    		$error_code = 500;
    	}
    	
    	$text = ($message != '') ? $message : self::$_http_codes[$error_code];    	
    	
    	if ($error_code > 200) {
    		header('HTTP/1.1 ' . $error_code . ' ' . self::$_http_codes[$error_code], true, $error_code);
    	}
    	
    	self::log($error_code . ': ' . $text, 'error');
    	
    	if (self::$_debug) {
    		exit();
    	} else {
    		exit($error_code . ': ' . $text);
    	}
    	
    	
    }
    
    /**
     * Graceful shutdown of teh process
     * unlocks database and returns geven HTTP error code
     * 
     * @param int $error_code HTTP error code to display
     * @param string $message Optional message to log/display
     * 
     * @return void
     */
    public function shutdown($error_code, $message = null) {
    	self::unlock();
    	self::terminate($error_code, $message = null);
    }
    
    /**
     * Unlock script
     * 
     * @return void
     */
    protected static function unlock() {
    	
    	$db = self::getConnection();
    	$col = $db->selectCollection(self::$_prefix . 'lock');
    	$col->drop();
    	self::log('Unlocked','info');
    	
    }
    
    /**
     * Lock check
     * 
     * @return bool 
     */
    public function isLocked() {
    	
    	$db = self::getConnection();
    	
    	// avoid dead locks
    	if (in_array(self::$_prefix . 'lock', $db->getCollectionNames())) {
    		$col = $db->selectCollection(self::$_prefix . 'lock');
    		$record = $col->findOne(array(), array('ts'));
    		$ts = new \DateTime($record['ts']['date'], new \DateTimeZone($record['ts']['timezone']));
    		if ($ts->add(new \DateInterval(self::$_lock_timeout)) < new \DateTime()) {
    			self::unlock();
    			return false;
    		}
    	} else {
    		return false;
    	}
    	
    	return true;
    }

    /**
     * Lock script, only single process allowed
     * 
     * @return void 
     */
    public function lock() {
    	
    	global $_SERVER;
    	
    	$db = self::getConnection();
    	$col = $db->selectCollection(self::$_prefix . 'lock');
    	$params = array(
    		'ts' => new \DateTime(),
    		'ip' => $_SERVER['REMOTE_ADDR']
    	); 
    	$col->insert($params);
    	
    	self::log('Locked','info');
    	
    }
    
    /**
     * get all authors to send replies to
     * 
     * @return \MongoCursor
     */
    public function getAuthors() {
    	
    	$db = self::getConnection();
    	$col = $db->selectCollection(self::$_prefix . 'authors');
    	return $col->find(array('replied' => null));
    }
    
    /**
     * Mark Author's record as successfully used for sending reply
     * 
     * @param string $author Author's Twitter handle
     * 
     * @return void
     */
    
    public function markAuthorAsSent($author) {
    	
    	$db = self::getConnection();
    	$col = $db->selectCollection(self::$_prefix . 'authors');
    	$res = $col->update(
    		array('author' => $author), 
    		array('$set' => 
    			array('replied' => new \MongoTimestamp())
    		), 
    		array('multiple' => true)
    	);
    	
    }
    
    /**
     * get last search meta data
     * 
     * @return array
     */
    public function getLastSearchInfo() {
    	
    	$db = self::getConnection(); 
    	$col = $db->selectCollection(self::$_prefix . 'search');
    	return $col->findOne();

    }
    
    /**
     * save new tweets' authors for future send round
     * 
     * @param Array $authors Associative array with Twitter handles as keys and ID of the latest tweet as value
     * 
     * @return void
     */
    public function saveAuthors($authors) {
    	
    	$db = self::getConnection();
    	$col = $db->selectCollection(self::$_prefix . 'authors');
    	
    	// check if we have same handles already recorded - we don't want to send more then one reply
    	
    	$old = $col->find(array('author' => array('$in' => array_keys($authors))), array('author' => true));
    	
    	foreach ($old as $record) {
    		
    		unset($authors[$record['author']]);
    		
    	}
    	
    	// save authors only if there any ;-)
    	if (count($authors) > 0) {
    		
	    	$params = array();
	    	
	    	foreach ($authors as $handle => $tweet) {
	    		
	    		$params[] = array(
	    			'author' => $handle,
	    			'status_id' => $tweet,
	    			'created' => new \DateTime()
	    		);
	    		
	    	}
    	
    		$col->batchInsert($params, array('continueOnError' => true));
    	}
    	
    	
    }
    
    /**
     * Save latest search meta data
     * 
     * @param string $since_id ID of the last tweet we found in the last round
     * 
     * @return void
     */
    
    public function saveSearchInfo($since_id) {
    	
    	$db = self::getConnection();  
    	$col = $db->selectCollection(self::$_prefix . 'search');
        $col->update(array(), array('since_id' => $since_id, 'ts' => new \DateTime()) , array('upsert' => true));
    	
    }
    
    /**
     * Logging facility
     * 
     * @param string $message Message to log
     * @param string $type Type of message to log
     * @param mixed $dump What to show to the developer
     * 
     * @return void
     */
    
    public static function log($message='', $type='') {
    	
    	if (self::$_debug) {
    		echo '<p><b>' . $type . '</b> &rArr; ' . $message .'</p>';
    	} else if ($type == 'error') { 
	    	$db = self::getConnection(); 
	    	$db->log->insert(array('ts' => new \DateTime(), 'message' => $message, 'type' => $type, 'prefix' => self::$_prefix));    		
    	}
    	
    }
}

?>