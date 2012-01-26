<?php
require_once 'Table.php';

class WERealtime_Model_Log extends ML_Model_Table {
	protected static $_instance = null;
	
	public static function getInstance() {
		if (self::$_instance === null) {
			self::$_instance = new self ();
		}
		return self::$_instance;
	}
	
	protected $properties = array (
			'Id' => 'id',
			'Serial' =>	'serial',
			'Type' => 'type',
			'Name' => 'name',
			'Value' => 'value',
			'Time' => 'time',
	);
	public function getTable() {
		return 'logs';
	}
	public function getDatabase() {
		return 'realtime-tool';
	}
	
	public function getLastCommand() {
		$options = array(
			'WHERE' => "`type` = 'command'",
			'ORDERBY' => "`time` DESC",
			'LIMIT' => "1",
		);

		$objs = $this->getRecordList($options);
		return array_pop($objs);
	}
	public function isStartIngesting() {
		return $this->Name == 'start ingesting' && $this->Value == 1;
	}
	public function isStopIngesting() {
		return $this->Name == 'stop ingesting' && $this->Value == 1;
	}
	
	public function getIngestLog($name = '') {
		if ($name) {
			$sql = "
				SELECT	" . $this->getPropertiesString() . "
				FROM	`" . $this->getTable() . "`
				WHERE	`type` = 'ingest'
					AND	`name` = '".addslashes($name)."'
				ORDER BY
						`time` DESC
				LIMIT	1
			";
		} else {
			$sql = "
				SELECT	" . $this->getPropertiesString() . "
				FROM	`" . $this->getTable() . "`
				WHERE	`type` = 'ingest'
				ORDER BY
						`time` DESC
				LIMIT	1
			";
		}
		
		$row = $this->connect()->fetch($sql);
		return $row ? $this->object($row) : false;
	}
	public function addIngestLog($name, $value = '', $serial = 0) {
		$serial = intval($serial);
		
		if (!$serial) {
			$sql = "
				SELECT MAX(`serial`)
				FROM logs
			";
			$serial = 1 + $this->connect()->fetchOne($sql);
		}
		
		$sql = "
			INSERT INTO
					`" . $this->getTable() . "`
			SET	`type` = 'ingest'
				, `serial` = $serial
				, `time` = NOW()
				, `name` = '".addslashes($name)."'
				, `value` = '".addslashes($value)."'
			";
		$this->connect()->query($sql);
		
		return $serial;
	}
	function updateIngestLog($name, $value = '', $serial) {
		$serial = intval($serial);
	
		$sql = "
			SELECT	id
			FROM	`" . $this->getTable() . "`
			WHERE	`type` = 'ingest'
				AND	`name` = '".addslashes($name)."'
				AND	`serial` = $serial
		";
		$id = $this->connect()->fetchOne($sql);
	
		if ($id) {
			$sql = "
				UPDATE `" . $this->getTable() . "`
				SET	`value` = '".addslashes($value)."'
					, `time` = NOW()
				WHERE	`type` = 'ingest'
					AND	`name` = '".addslashes($name)."'
					AND	`serial` = $serial
			";
		} else {
			$sql = "
				INSERT INTO
						`" . $this->getTable() . "`
				SET	`type` = 'ingest'
					, `serial` = $serial
					, `time` = NOW()
					, `name` = '".addslashes($name)."'
					, `value` = '".addslashes($value)."'
			";
		}
		$this->connect()->query($sql);
	}
	public function debug($message) {
		
	}
	
	function getUpdatingStations() {
		$sql = "
			SELECT	s.basin_id
					, s.infotype_id
					, s.html_version	AS station_version
					, s.station_strid
					, s.descriptor		AS station_descriptor
					, t.version			AS text_version
					, t.records_num		-- Stations Details page use
					, t.inserted_num
			FROM	station	AS s
			LEFT JOIN (	-- 先选出来再 left join 效率会高很多
				SELECT	t.basin_id
						, t.infotype_id
						, t.station_strid
						, t.version
						, t.records_num
						, t.inserted_num
				FROM	textdata AS t
				WHERE	t.status = 'current'
				--	GROUP BY	-- 避免出现多个版本都是 current
				--			t.basin_id, t.infotype_id, t.station_strid
			) t
				ON	t.basin_id		= s.basin_id
				AND	t.infotype_id	= s.infotype_id
				AND	t.station_strid = s.station_strid
			WHERE
					s.status = 'current'
			ORDER BY
					s.basin_id, s.infotype_id, s.station_strid
		";
	
		return $this->connect()->fetchAll($sql);
	}
	
	public function getVersionList() {
		$sql = "
			SELECT	a.value AS version
					, b.*
			FROM	(
				SELECT	value, MAX(serial) serial
				FROM	`logs`
				WHERE	name = 'start ingesting'
				GROUP BY
				 		value			-- this can make only show the first serial
				ORDER BY
				 		0+value DESC	-- convert from string to number
				LIMIT 10
			) AS a
			JOIN	logs AS b
				ON	a.serial = b.serial
			ORDER BY
				b.serial DESC
		";
		$rows = $this->connect('realtime')->fetchAll($sql);
		
		$logs = array();
		foreach ($rows as $row) {
			$logs[$row['version']][$row['serial']][$row['name']] = $row['value'];
			$logs[$row['version']][$row['serial']][$row['name'] . '_time'] = $row['time'];
		}
		return $logs;
	}
	function sync() {
		
	}
}
?>