<?php
//TODO: add support for database prefixes
require_once './base.php';
require_once W2P_BASE_DIR . '/includes/main_functions.php';
if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
    echo 'web2Project requires PHP '.MIN_PHP_VERSION.'+. Please upgrade!';
    die();
}
require_once W2P_BASE_DIR . '/lib/adodb/adodb.inc.php';

class dbConvertToUTF8 { 
	private $tblColTypes = array(
		'char' => 'binary',
		'text' => 'blob',
		'tinytext' => 'tinyblob',
		'mediumtext' => 'mediumblob',
		'longtext' => 'longblob',
		'varchar' => 'varbinary'
	);
	/* tables with char/text columns excluded from conversion */
	private $excludedTables = array(
		'config_list',
        'event_queue',
        'gacl_aco',
        'gacl_aco_map',
        'gacl_aco_sections',
        'gacl_axo_map',
        'gacl_phpgacl',
        'sessions',
        'user_access_log',
        'user_feeds',
        'w2pversion');
	private $configFile;
	private $configOptions = array();
	private $oldCharset = ''; // system wide database connection charset
	public $convertSQL = '';
	
	public function __construct() {
		$this->configFile = W2P_BASE_DIR . '/includes/config.php';
        if (!file_exists($this->configFile) || filesize($this->configFile) == 0) 
        	exit('# ERROR: No config file found.');
		require_once $this->configFile;
		if (isset($w2Pconfig)) $this->configOptions = $w2Pconfig;	
	}

    public function getSQL() {
    	$this->convertSQL = '';
    	$dbConn = $this->_openDBConnection();
    	if ($dbConn) {
    		if (!$this->_precheck($dbConn)) die;
			$sql = "SHOW TABLES;";
        	$res = $dbConn->Execute($sql);
        	$tables=array();
    	  	foreach($res as $k => $row) {
    	  		$tbl=$row[0];
    	  		if (!in_array ($tbl, $this->excludedTables)) {
    	  			$metaCols = $dbConn->MetaColumns($tbl);
    	  			foreach ($metaCols as $col) {
	    	  			if (array_key_exists($col->type, $this->tblColTypes)) {
	    	  				$tables[$tbl]=true;
	    	  				$type = $col->type;
	    	  				$temp_type = $this->tblColTypes[$type];
	    	  				if ($type == 'varchar') {
	    	  					$type .= '('.$col->max_length.')';
	    	  					$temp_type .= '('.$col->max_length.')';
	    	  				}
	    	  				/* set old charset */
	    	  				$this->convertSQL .= 
	    	  				sprintf('ALTER TABLE `%s` CHANGE `%s` `%s` `%s` CHARACTER SET `%s`;'."\n",
	    	  				$tbl, $col->name, $col->name, $type, $this->oldCharset); 
	    	  				/* convert to corresponding binary type */ 
	    	  				$this->convertSQL .= 
	    	  				sprintf('ALTER TABLE `%s` CHANGE `%s` `%s` `%s`;'."\n", 
	    	  				$tbl, $col->name, $col->name, $temp_type);
	    	  				/* conver back to original type with utf8 charset */ 
	    	  				$this->convertSQL .= 
	    	  				sprintf('ALTER TABLE `%s` CHANGE `%s` `%s` `%s` CHARACTER SET utf8;'."\n",
	    	  				$tbl, $col->name, $col->name, $type);
	    	  				$this->convertSQL .= "\n";
	    	  				 
	    	  			}
    	  			}
    	  		}  		
  			}
      		/* rebuild table indexes */
  			/* TODO: this works only with MySQL MyISAM tables */
  			foreach(array_keys($tables,tru) as $tbl) {
	      		$this->convertSQL .= sprintf('REPAR TABLE `%s` QUICK;'."\n", $tbl);
  			} 
  			
    	}
    }
    
    private function _precheck($dbConn) {
    	if (!isset($this->configOptions['dbcharset'])) {
    		echo "# Warning: \$dbcharset not set in config file. Please apply patch and add \$dbcharset='' to web2project config file first!\n";
    		die;
    	}
    	if ($this->configOptions['dbcharset'] == 'utf8') {
    		echo "# \$dbcharset is already set to 'utf8' in web2project config file. Database is probably already converted.\n";
    		die;
    	}
    	if ($this->configOptions['dbcharset'] <> '') {
    		echo "# Error: \$dbcharset in web2project config file is already set but not to expected 'utf8'! Only utf8 is supported!.\n";
    		die;
    	}
    	$this->oldCharset = mysql_client_encoding($dbConn->_connectionID);
    	echo '# Current database connection charset is '.$this->oldCharset,"\n";
    	if ($this->oldCharset == 'utf8') {
    		echo "# Current database connection charset is already utf8. No database conversion needed. Please set \$dbcharset config file value to 'utf8' and you are done.\n";
    		die;	
    	}
    	echo "# SQL statements for database conversion to utf8 will be printed. Run these SQL statements (i.e. within phpmyadmin) and \n";
    	echo "# then set \$dbcharset config file value to 'utf8'.\n";
    	echo "# WARNING: Script will convert all tables in the database. Tables' prefix is not supported (because it is not fully supported by current web2Project
version).\n";
    	echo "# IMPORTANT WARNING: Make sure tables content will not be updated until you finish database conversion and set new \$dbcharset value!\n";
    }
	
	private function _openDBConnection() {
        $db = false;

        try {
            $db = NewADOConnection($this->configOptions['dbtype']);
            if(!empty($db)) {
              $dbConnection = $db->Connect($this->configOptions['dbhost'], $this->configOptions['dbuser'], $this->configOptions['dbpass']);
              if ($dbConnection) {
              	$existing_db = $db->SelectDB($this->configOptions['dbname']);
                if (!$existing_db) {
                  $db->_errorMsg = '# ERROR: This database user does not have rights to the database.';
                }
              }
            } else {
                $dbConnection = false;
            }
        } catch (Exception $exc) {
            echo '# ERROR: Your database credentials do not work.';
        }
        return $db;
    }

}

$convertor = new dbConvertToUTF8;
$convertor->getSQL();
echo $convertor->convertSQL;