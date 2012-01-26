<?php

require_once 'Dbconn.php';

abstract class ML_Model_Table extends ML_Model_Dbconn {
	public function __get($name) {
		if (isset($this->$name)) {
			return $this->$name;
		} else {
			return '';
		}
	}
	public function __call($method, $params) {
		if (preg_match('/^service(.+)$/', $method, $m)) {
			$realMethod = $m[1];
			return call_user_func_array(array($this, $realMethod), $params);
		}
	}
	
	
	abstract public function getDatabase();
	public function connect($conn = null) {
		if (is_null($conn)) {
			$conn = $this->getDatabase();
		}
		return parent::connect($conn);
	}
	
	protected function getModel($name) {
		$name = preg_replace('/[^a-zA-Z]/', '', $name);
		$filename = dirname(__FILE__) . DIRECTORY_SEPARATOR . "$name.php";
		if (file_exists($filename)) {
			require_once $filename;
		}
		
		$modelName = 'WERealtime_Model_' . $name;
		return call_user_func_array(array($modelName, 'getInstance'), array());
	}
	
	protected $properties = array(
		'Id' => 'id'
	);
	public function getPropertiesString($prefix = '', $glue = ', ') {
		$prefix = $prefix ? $prefix . "." : '';
		return "$prefix`" . implode("`$glue$prefix`", $this->properties) . "`";
	}
	public function getTable() {
		return 'test';
	}
	public function getPropertyField($propertyName) {
		return $this->properties[$propertyName];
	}
	protected function object($array = array()) {
		$class = get_class($this);
		$obj = new $class();
		foreach ($this->properties as $name => $field) {
			$obj->$name = isset($array[$field]) ? $array[$field] : null;
		}
	
		return $obj;
	}
	public function getRecordByProperty($propertyName, $propertyValue = null) {
		if (is_array($propertyName) && !empty($propertyName)) {
			$array = $propertyName;
			$sql = '';
			foreach ($array as $propertyName => $propertyValue) {
				if ($sql == '') {
					$sql = "
						SELECT	" . $this->getPropertiesString() . "
						FROM	" . $this->getTable() . "
						WHERE	" . $this->getPropertyField($propertyName) . " = '" . addslashes($propertyValue) . "'
							";
				} else {
					$sql .= "
							AND	" . $this->getPropertyField($propertyName) . " = '" . addslashes($propertyValue) . "'";
				}
			}
		} else {
			$sql = "
				SELECT	" . $this->getPropertiesString() . "
				FROM	" . $this->getTable() . "
				WHERE	" . $this->getPropertyField($propertyName) . " = '" . addslashes($propertyValue) . "'
			";
		}
		
		$row = $this->connect()->fetch($sql);
		return $row ? $this->object($row) : false;
	}
	protected function getRecordList($options = array()) {
		$sql = "
			SELECT	" . $this->getPropertiesString() . "
			FROM	`" . $this->getTable() . "`
			WHERE	" . (empty($options['WHERE']) ? 'TRUE' : $options['WHERE'])
				. (empty($options['ORDERBY']) ? '' : '
			ORDER BY
					' . $options['ORDERBY'])
				. (empty($options['LIMIT']) ? '' : '
			LIMIT	' . $options['LIMIT']) . "
		";
		$rows = $this->connect()->fetchAll($sql);
		
		$objs = array();
		foreach ($rows as $row) {
			$objs[] = $this->object($row);
		}
		
		return $objs;
	}
	protected function getInsertString($params) {
		$array = array();
		foreach ($this->properties as $name => $field) {
			if (isset($params[$name])) {
				$array[] = "`" . $field . "` = '" . addslashes($params[$name]) . "'";
			}
		}
		return implode(",\n", $array);
	}
	protected function saveRecordByProperty($params, $propertyName = 'Id') {
		$array = array();
		if (is_array($propertyName) && !empty($propertyName)) {
			$propertyValues = $propertyName;
			foreach ($propertyValues as $propertyName) {
				if (isset($params[$propertyName])) {
					$array[$propertyName] = $params[$propertyName];
				}
			}
			$record = $this->getRecordByProperty($array);
		} elseif (isset($params[$propertyName])) {
			$array = array($propertyName => strval($params[$propertyName]));
			$record = $this->getRecordByProperty($array);
		} else {
			$record = false;
		}
		
		$insertString = $this->getInsertString($params);
		if (!$insertString) {
			return false;
		} else {
			if ($record) {
				$fieldName = $this->getPropertyField($propertyName);
				$sql = '';
				foreach ($array as $propertyName => $propertyValue) {
					if ($sql == '') {
						$sql = "
							UPDATE	" . $this->getTable() . "
							SET		" . $this->getInsertString($params) . "
							WHERE	`$fieldName` = '" . addslashes($propertyValue) . "'
								";
					} else {
						$sql .= "
								AND	`$fieldName` = '" . addslashes($propertyValue) . "'";
					}
				}
				return $this->connect()->query($sql);
			} else {
				$sql = "
					INSERT INTO
							" . $this->getTable() . "
					SET		" . $this->getInsertString($params) . "
				";
				return $this->connect()->query($sql)->lastInsertId();
			}
		}
	}
	protected function setProperty($propertyName, $propertyValue, $byProperty = 'Id') {
		$propertyFieldName = $this->getPropertyField($propertyName);
		$idFieldName = $this->getPropertyField($byProperty);
		
		$sql = "
			UPDATE	`" . $this->getTable() . "`
			SET		`$propertyFieldName` = '" . addslashes($propertyValue) . "'
			WHERE	`$idFieldName` = '" . addslashes($this->$byProperty) . "'
		";
		return $this->connect()->query($sql);
	}
	public function deleteRecordByProperty($propertyName, $propertyValue = null) {
		if (is_array($propertyName)) {
			$array = $propertyName;
			
			$sql = '';
			foreach ($array as $propertyName => $propertyValue) {
				if ($sql == '') {
					$sql = "
						DELETE FROM
								" . $this->getTable() . "
						WHERE	`" . $this->getPropertyField($propertyName) . "` = '" . addslashes($propertyValue) . "'";
				} else {
					$sql .= "
							AND	`" . $this->getPropertyField($propertyName) . "` = '" . addslashes($propertyValue) . "'";
				}
			}
			return $this->connect()->query($sql);
		} else {
			$sql = "
				DELETE FROM
						" . $this->getTable() . "
				WHERE	" . $this->getPropertyField($propertyName) . " = '" . addslashes($propertyValue) . "'
			";
			return $this->connect()->query($sql);
		}
	}
	
	protected function saveRecordList($list) {
		
	}
}

?>