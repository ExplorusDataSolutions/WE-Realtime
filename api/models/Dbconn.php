<?php
class ML_Model_Dbconn {
	private $db;
	public function connect($conn = null) {
		//if (!$this->db) {
			/*require_once 'Zend/Db.php';
			
			$params = array (
				'host'     => '127.0.0.1',
				'username' => 'axure',
				'password' => 'axure',
				'dbname'   => 'dtsusaworks',
				// use our own ML_Model_Mysql to enable ":" in sql
				'adapterNamespace' => 'ML',
				// enable "set names 'utf8'"
				'charset' => 'utf8',
			);
			$this->db = Zend_Db::factory('Model_MYSQL', $params);*/
			
			global $cfg;
			if (empty($cfg['dbs'][$conn])) {
				pre($conn,1);
			}
			
			$this->db = $this->dbconn($cfg['dbs'][$conn]);
		//}
		
		return $this;
	}
	public function dbconn($options = array()) {
		static $conns = array();
		
		$driver = array_key_exists('driver', $options) ? $options['driver']	: 'MySQL';
		$hostname = array_key_exists('hostname', $options) ? $options['hostname'] : 'localhost';
		$username = array_key_exists('username', $options) ? $options['username'] : '';
		$password = array_key_exists('password', $options) ? $options['password'] : '';
		$database = array_key_exists('database', $options) ? $options['database'] : '';
		$new = array_key_exists('new', $options) ? $options['new'] : true;
	
		$signature = serialize("$driver://$username:$password@$hostname/$database");
		if (empty($conns[$signature])) {
			$driver = preg_replace('/[^A-Z0-9_\.-]/i', '', $driver);
			$path = dirname(__FILE__) . DIRECTORY_SEPARATOR . "$driver.php";
			
			if (file_exists($path)) {
				require_once($path);
			} else {
				die("$path does not exist.");
			}
			
			if ($driver == 'MySQL') {
				$conn = new MySQL($hostname, $username, $password, $database, $new);
			} elseif ($driver == 'PostgreSQL') {
				$conn = new PostgreSQL($hostname, $username, $password, $database);
			} else {
				die("Database $driver is not supported yet.");
			}
			$conns[$signature] =& $conn;
		}
	
		return $conns[$signature];
	}
	
	public function query($sql) {
		try {
			$this->db->query($sql);
		} catch (Exception $e) {
			pre($sql . $e->getMessage(),1);
		}
		return $this;
	}
	public function fetch($sql) {
		try {
			return $this->db->fetchRow($sql);
		} catch (Exception $e) {
			pre($sql . $e->getMessage(),1);
		}
	}
	public function fetchOne($sql) {
		try {
			return $this->db->fetchOne($sql);
		} catch (Exception $e) {
			pre($sql . $e->getMessage(),1);
		}
	}
	public function fetchAll($sql) {
		try {
		return $this->db->fetchAll($sql);
		} catch (Exception $e) {
			pre($sql . $e->getMessage(),1);
		}
	}
	public function lastInsertId() {
		return $this->db->lastInsertId();
	}
	public function affectedRows() {
		return $this->db->affectedRows();
	}
	
	public function describeTable($tableName, $databaseName) {
		return $this->db->describeTable($tableName, $databaseName);
	}
}
?>
