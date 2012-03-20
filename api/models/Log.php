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
	public function stopIngesting($version) {
		$lastSerial = $this->getLastSerial();
		return $this->updateIngestLog('stop ingesting', $version, $lastSerial['serial']);
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
				WHERE	id = $id
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
		
		return $this->connect()->affectedRows(); 
	}
	function updateCommand($name, $value = '') {
		$sql = "
			SELECT	id
			FROM	`" . $this->getTable() . "`
			WHERE	`type` = 'command'
				AND	`name` = '".addslashes($name)."'
				AND	`value` = '".addslashes($value)."'
		";
		$id = $this->connect()->fetchOne($sql);
	
		if ($id) {
			$sql = "
				UPDATE `" . $this->getTable() . "`
				SET	`value` = '".addslashes($value)."'
					, `time` = NOW()
				WHERE	`id` = $id
			";
		} else {
			$sql = "
				INSERT INTO
						`" . $this->getTable() . "`
				SET	`type` = 'command'
					, `serial` = 0
					, `time` = NOW()
					, `name` = '".addslashes($name)."'
					, `value` = '".addslashes($value)."'
			";
		}
		$this->connect()->query($sql);
		
		return $this->connect()->affectedRows(); 
	}
	public function debug($message) {
		echo @date("Y-m-d H:i:s ")
			. preg_replace('/.*\./', '.', sprintf("%.4f", round(microtime(true), 4)))
			. " - $message<br />";
	}
	
	function getUpdatingStations() {
		$modelStation = $this->getModel('Station');
		$version = $modelStation->getLatestVersion();
		$sql = "
			SELECT	s.basin_id
					, s.infotype_id
					, s.`" . $modelStation->getPropertyField('Version') . "` version
					, s.station_strid
					, s.descriptor		AS station_descriptor
					, t.version			AS text_version
					, t.records_num		-- Stations Details page use
					, t.inserted_num
					, t.text_id
			FROM	station	AS s
			LEFT JOIN (	-- 先选出来再 left join 效率会高很多
				SELECT	t.id text_id
						, t.basin_id
						, t.infotype_id
						, t.station_strid
						, t.station_version
						, t.version
						, t.records_num
						, t.inserted_num
				FROM	textdata AS t
				WHERE	t.status = 'current'
			) t
				ON	t.basin_id			= s.basin_id
				AND	t.infotype_id		= s.infotype_id
				AND	t.station_strid		= s.station_strid
				AND	t.station_version	= s.version
			WHERE
					s.`" . $modelStation->getPropertyField('Version') . "` = $version
			ORDER BY
					s.basin_id, s.infotype_id, s.station_strid
			--		s.station_strid, s.infotype_id, s.basin_id
		";
		
		return $this->connect()->fetchAll($sql);
	}
	
	public function getLastSerial() {
		$sql = "
			SELECT	a.version
					, b.serial
					, b.name
					, b.value
					, b.time
			FROM	(
				SELECT	value AS version	-- 1 ingesting version can be finished by many times(1 time is called 1 serial)
						, MAX(serial) serial
				FROM	`" . $this->getTable() . "`
				WHERE	name = 'start ingesting'
				GROUP BY
				 		value			-- this can make only show the first serial
				ORDER BY
				 		0+value DESC	-- convert from string to number
				LIMIT 1
			) AS a
			JOIN	`" . $this->getTable() . "` AS b
				ON	a.serial = b.serial	-- get other information of this serial
			ORDER BY
				b.serial DESC
		";
		$rows = $this->connect()->fetchAll($sql);
		
		$logs = array();
		$list = array();
		foreach ( $rows as $row ) {
			$v = $row ['version'];
			$s = $row ['serial'];
			if (empty ( $logs [$v] [$s] )) {
				$logs [$v] [$s] = array ('serial' => $s);
				$list [] = &$logs [$v] [$s];
			}
			$n = $row ['name'];
			switch ($n) {
				case 'start ingesting' :
					$logs [$v] [$s] ['version'] = intval ( $row ['value'] );
					$logs [$v] [$s] ['start_time'] = $row ['time'];
					break;
				case 'finish ingesting' :
					$logs [$v] [$s] ['total'] = intval ( $row ['value'] );
					$logs [$v] [$s] ['end_time'] = $row ['time'];
					break;
				case 'current ingesting' :
					$logs [$v] [$s] ['final_status'] = $row ['value'];
					$logs [$v] [$s] ['status_time'] = $row ['time'];
					break;
				case 'stop ingesting' :
					$logs [$v] [$s] ['stop'] = $row ['value'];
					$logs [$v] [$s] ['stop_time'] = $row ['time'];
					break;
				default :
					$logs [$v] [$s] [$n] = $row ['value'];
					$logs [$v] [$s] [$n . '_time'] = $row ['time'];
			}
		}
		return array_pop($list);
	}
	
	public function getVersionList($start = 0, $limit = 25) {
		$limit = intval($limit);
		$sql = "
			SELECT	a.version
					, b.serial
					, b.name
					, b.value
					, b.time
			FROM	(
				SELECT	value AS version	-- 1 ingesting version can be finished by many times(1 time is called 1 serial)
				FROM	`" . $this->getTable() . "`
				WHERE	name = 'start ingesting'
				GROUP BY
				 		value			-- this can make only show the first serial
				ORDER BY
				 		0+value DESC	-- convert from string to number
				LIMIT $start, $limit
			) AS a	-- version list
			JOIN	(
				SELECT	serial
						, value AS version
				FROM	`" . $this->getTable() . "`
				WHERE	name = 'start ingesting'
			) AS c
				ON	a.version = c.version
			JOIN	`" . $this->getTable() . "` AS b
				ON	c.serial = b.serial	-- get other information of this serial
			ORDER BY
				b.id	-- This affects 'final_status' below
						-- Also important for 'end_time' of 'finish ingesting'
		";
		$rows = $this->connect()->fetchAll($sql);
		
		$logs = array();
		$list = array();
		foreach ($rows as $row) {
			$v = $row['version'];
			$s = $row['serial'];
			if (empty($logs[$v])) {
				$logs[$v] = array();
				$list[] = &$logs[$v];
			}
			$n = $row['name'];
			if ($n == 'start ingesting') {
				$logs[$v]['version'] = intval($row['value']);
				$logs[$v]['start_time'] = $row['time'];
			} elseif ($n == 'finish ingesting') {
				if (empty($logs[$v]['total'])) {
					//$logs[$v]['total'] = intval($row['value']);
				} else {
					//$logs[$v]['total'] = intval($row['value']);
				}
				$logs[$v]['end_time'] = $row['time'];
			} elseif ($n == 'current ingesting') {
				$logs[$v]['end_time'] = '';
				
				$logs[$v]['final_status'] = $row['value'];
				$logs[$v]['status_time'] = $row['time'];
				if (preg_match('/progress: (\d+)\/(\d+)/', $row['value'], $m)) {
					if (empty($logs[$v]['total'])) {
						$logs[$v]['total'] = intval($m[1]);
					} else {
						$logs[$v]['total'] += intval($m[1]);
					}
				}
			} else {
				$logs[$v][$n] = $row['value'];
				$logs[$v][$n . '_time'] = $row['time'];
			}
		}
		return $list;
	}
	function sync() {
		
	}
}
?>