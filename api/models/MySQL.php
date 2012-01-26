<?php

class MySQL {
	var $new = false;

	function MySQL($hostname, $username, $password, $database, $new) {
		$this->hostname = $hostname;
		$this->database = $database;
		$this->username = $username;
		$this->password = $password;
		$this->new = $new;
		
		$this->connect();
	}

	function connect() {
		$this->link = mysql_connect($this->hostname, $this->username, $this->password, $this->new);
		
		if (!$this->link) {
			die("Can not connect $this->username@$this->hostname");
		}

		if (!mysql_select_db($this->database, $this->link)) {
			die("database $this->database does not exist.");
		}

		$this->query('set names "utf8"');
	}

	function query($sql) {
		$this->resource = mysql_query($sql, $this->link);
		if (!$this->resource)	{
			$errno = mysql_errno($this->link);
			$errmsg = mysql_error($this->link);
			echo "<xmp>$errmsg<hr />\n$sql\n";
			$trace = debug_backtrace();

			foreach ($trace as $t) {
				$file = @$t['file'];
				$line = @$t['line'];
				echo "#<b>$line</b> $t[function]() $file\n";
			}
			exit(0);
		}
	}
	
	/**
	 * this method will replace fetchColumn()
	 */
	public function fetchOne($sql) {
		$this->query($sql);
		
		$row = mysql_fetch_assoc($this->resource);
		if (is_array($row)) {
			return array_shift($row);
		} else {
			return false;
		}
	}
	
	function fetchRow($sql) {
		$this->query($sql);
		return mysql_fetch_assoc($this->resource);
	}
	
	function fetchAll($sql) {
		$this->query($sql);
		
		$rows = array();
		while ($row = mysql_fetch_assoc($this->resource)) {
			$rows[] = $row;
		}
		return $rows;
	}
	
	function lastInsertId() {
		return mysql_insert_id($this->link);
	}
	
	public function describeTable($tableName) {
		$sql = "
			SELECT	1
			FROM	information_schema.TABLES
			WHERE	TABLE_SCHEMA = '" . addslashes($this->database) . "'
				AND	TABLE_NAME = '" . addslashes($tableName) . "'
		";
		$row = $this->fetchRow($sql);
		if (empty($row)) {
			return false;
		}
		
		$sql = "
			SELECT	*
			FROM	information_schema.COLUMNS
			WHERE	TABLE_SCHEMA = '" . addslashes($this->database) . "'
				AND	TABLE_NAME = '" . addslashes($tableName) . "'
		";
		return $this->fetchAll($sql);
	}
	
	function close() {
		mysql_close($this->link);
	}
	
	function affected_rows() {
		return mysql_affected_rows($this->link);
	}
	
	function log($query) {
		if ($query == 'set names "utf8"') return;
		
		$msg = $_SERVER["REMOTE_ADDR"].",";
		$msg .= (isset($_SESSION[USERINFO]['uuid'])) ? ($_SESSION[USERINFO]['uuid'].',') : ',';
		$msg .= (isset($_REQUEST["PHPSESSID"])) ? ($_REQUEST["PHPSESSID"].',') : ',';
		$msg .= (isset($_SERVER["SCRIPT_URL"])) ? ($_SERVER["SCRIPT_URL"].',') : ',';
		$msg .= date('Y-m-d').','.date('H:i:s').','.$query."";
		
		$filename = 'includes/sql.csv';
		if (is_writable($filename))
		{
			if (!$handle = fopen($filename, "a+"))
			{
				stdfehler(1006);
				exit;
			}

			if (!fwrite($handle, str_replace("\n",'<br />',$msg)."\n"))
			{
				stdfehler(1007);
				exit;
			}

			fclose($handle);
		} 
		else
		{
			stdfehler(1008);
			exit;
		}
	}
	
	function lastError() {
		return mysql_error($this->link);
	}
}
?>