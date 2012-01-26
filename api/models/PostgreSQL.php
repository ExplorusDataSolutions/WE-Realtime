<?php

class PostgreSQL {
	function PostgreSQL($hostname, $username, $password, $database) {
		$this->hostname = $hostname;
		$this->database = $database;
		$this->username = $username;
		$this->password = $password;
		
		$this->connect();
	}
	
	function connect() {
		$this->link = pg_connect("dbname=$this->database user=$this->username password=$this->password");

		if ( !$this->link ) {
			die("Can not connect $this->username@$this->hostname");
		}
	}
	
	function query($sql)
	{
		//if(!is_resource($resource)){
//			$fp = fopen('log.txt', 'a');
//			fwrite($fp, print_r($_REQUEST,1));
//			fwrite($fp, "\n".$sql."\n\n");
//			fclose($fp);
		//}
		
		$this->resource = pg_query($this->link, $sql);
		if (!$this->resource) {
			$errmsg = pg_errormessage($this->link);
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
	public function fetchOne($sql) {
		$this->query($sql);
		
		$row = pg_fetch_assoc($this->resource);
		if (is_array($row)) {
			return array_shift($row);
		} else {
			return false;
		}
	}
	
	public function fetchRow($sql) {
		$this->query($sql);
		
		return pg_fetch_assoc($this->resource);
	}
	
	public function fetchAll($sql) {
		$this->query($sql);
		
		$rows = array();
		while ($row = pg_fetch_assoc($this->resource)) {
			$rows[] = $row;
		}
	
		return $rows;
	}
	
	function insertid() {
		return pg_last_oid($this->link);
	}
	
	function affected_rows() {
		return pg_affected_rows($this->link);
	}
	
	function log($query)
	{
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

	function lastError()
	{
		return mysql_error($this->link);
	}
}
?>