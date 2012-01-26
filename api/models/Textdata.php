<?php
require_once 'Table.php';

class WERealtime_Model_Textdata extends ML_Model_Table {
	protected static $_instance = null;
	
	public static function getInstance() {
		if (self::$_instance === null) {
			self::$_instance = new self ();
		}
		return self::$_instance;
	}
	
	protected $properties = array (
			'Id' => 'id',
			'BasinId' =>	'basin_id',
			'TypeId' => 'infotype_id',
			'StationId' => 'station_strid',
			'Version' => 'version',
			'IngestTime' => 'update_time',
	);
	public function getTable() {
		return 'textdata';
	}
	public function getDatabase() {
		return 'realtime-tool';
	}
	
	const RE_HEAD = '/\|\s*((\w+( \w+)*)(\s*\(.*?\))?)\s*?(?=\|)/';
	const RE_RECORD = '/(\S+( \S+)*)(\s{2,}|\s*$)/';
	
	function updateTextdataContent($basin_id, $infotype_id, $station_strid, $station_version, $textdata, $error, $current_version) {
		$basin_id			= intval($basin_id);
		$infotype_id		= intval($infotype_id);
		$station_version	= intval($station_version);
		$station_strid		= strval($station_strid);
		$current_version	= intval($current_version);
	
		$md5 = md5($textdata);
	
		$sql = "
			INSERT INTO
					textdata
			SET	basin_id = $basin_id,
				infotype_id = $infotype_id,
				station_strid = '".addslashes($station_strid)."',
				station_version = $station_version,
				text_content = '".addslashes($textdata)."',
				text_md5 = '$md5',
				error = '$error',
				update_time = NOW(),
				version = $current_version,
				status = 'current'
		";
	
		$this->connect()->query($sql);
		return $this->connect()->lastInsertId($sql);
	}
	function getRealtimeDataDbname() {
		global $cfg;
		
		return $cfg['dbs']['realtime-data']['database'];
	}
	function getRealtimeDataTbname($basin_id, $infotype_id, $station_strid) {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		$station_strid = strtolower(strval($station_strid));
		return "basin_${basin_id}_datatype_${infotype_id}_$station_strid";
	}
	/**
	 * 保存分析后的所有数据
	 *
	 * @param int	$text_id
	 * @param int	$basin_id
	 * @param int	$infotype_id
	 * @param mixed	$data
	 * @return int	$text_id
	 */
	function updateTextdataData($text_id, $basin_id, $infotype_id, $station_strid, $data) {
		$text_id	= intval($text_id);
		//$start_time	= isset($data['start_time'])	? $data['start_time'] : '';
		//$end_time	= isset($data['end_time'])		? $data['end_time'] : '';
		
		$records = $data['records'];
		$columns = $data['columns'];
		
		// 处理抓取到的内容为空的情况
		if (empty($columns)) return false;
		
		$dbname = $this->getRealtimeDataDbname();
		$tbname = $this->getRealtimeDataTbname($basin_id, $infotype_id, $station_strid);
		// 表还不存在就先创建表
		$tableDesc = $this->connect('realtime-data')->describeTable($tbname);
		if (!$tableDesc) {
			$this->createRealtimeDataTable($text_id, $columns, $dbname, $tbname, $basin_id, $infotype_id, $station_strid);
			$tableDesc = $this->connect('realtime-data')->describeTable($tbname);
		}
		
		/**
		 * 从表结构中取得字段信息
		 */
		foreach ($tableDesc as $row) {
			$name = $row['COLUMN_NAME'];
			$table_fields[$name] = $row;
			// 把所有数据字段设置为 null，保证插入个别字段的数据时不会出错
			if ($row['IS_NULLABLE'] == 'NO' && $row['COLUMN_TYPE'] == 'float') {
				$sql = "ALTER TABLE `$dbname`.`$tbname` CHANGE `$name` `$name` FLOAT NULL";
				$this->connect()->query($sql);
			}
		}

		foreach ($columns as $i => $field) {
			$name = $field['field'];
			
			// 如果有新字段出现，那么动态添加字段
			if (!isset($table_fields[$name])) {
				$sql = "ALTER TABLE `$dbname`.`$tbname` ADD `$name` FLOAT NULL";
				$this->connect()->query($sql);
		
				$table_fields[$name] = array (
						'Field' => $name,
						'COLUMN_TYPE'	=> 'float',
						'IS_NULLABLE'	=> 'YES'
				);
				$fields_values[$name] = floatval($record[$i]);
			}
			
			if (in_array($name, array('id', 'basin_id', 'infotype_id', 'text_id', 'station', 'date_and_time'))) {
				continue;
			}
			/**
			 * 保存字段信息
			 */
			$sql = "
				SELECT	id
				FROM	station_field
				WHERE	basin_id = $basin_id
					AND	infotype_id = $infotype_id
					AND	station_strid = '" . addslashes($station_strid) . "'
					AND	field = '" . addslashes($name) . "'
			";
			$ids = $this->connect()->fetchAll($sql);
			
			$descriptor = isset($field['descriptor']) ? $field['descriptor'] : '';
			if (empty($ids)) {
				$sql = "
					INSERT INTO
							station_field
					SET		basin_id = $basin_id
							, infotype_id = $infotype_id
							, station_strid = '" . addslashes($station_strid) . "'
							, text_id = $text_id
							, field = '" . addslashes($name) . "'
							, field_full = '" . addslashes($field['fullname']) . "'
							, descriptor = '" . addslashes($descriptor) . "'
							, demo = '" . addslashes($field['demo']) . "'
							, unit = '" . addslashes(@$field['unit']) . "'
					";
				$this->connect()->query($sql);
			} else {
				foreach ($ids as $j => $row) {
					if ($j == 0) {
						$sql = "
							UPDATE	station_field
							SET		basin_id = $basin_id
									, infotype_id = $infotype_id
									, station_strid = '" . addslashes($station_strid) . "'
									, text_id = $text_id
									, field = '" . addslashes($name) . "'
									, field_full = '" . addslashes($field['fullname']) . "'
									, descriptor = '" . addslashes($descriptor) . "'
									, demo = '" . addslashes($field['demo']) . "'
									, unit = '" . addslashes(@$field['unit']) . "'
							WHERE	id = $row[id]
						";
						$this->connect()->query($sql);
					} else {
						$sql = "DELETE FROM station_field WHERE id = $row[id]";
						$this->connect()->query($sql);
					}
				}
			}
			
			/**
			 * 保存layer信息
			 */
			$sql = "
				SELECT	layerid
				FROM	layer
				WHERE	field = '" . addslashes($name) . "'
			";
			$layer = $this->connect()->fetch($sql);
			if ($layer) {
				if ($descriptor) {
					$sql = "
						UPDATE	layer
						SET		description = '" . addslashes($descriptor) . "'
								, text_id = $text_id
						WHERE	layerid = $layer[layerid]
					";
					$this->connect()->query($sql);
				}
			} else {
				$sql = "
					INSERT INTO
							layer
					SET		field = '" . addslashes($name) . "'
							, description = '" . addslashes($descriptor) . "'
							, text_id = $text_id
				";
				$this->connect()->query($sql);
			}
		}
		
		/**
		 * 插入数据
		 */
		$recorded = 0;
		$fileds = '';
		$values = array();
		$start_time = null;
		$end_time = null;
		
		/**
		 * 当当前记录与最后一次记录字段信息有变化时，或者时间较新时，才记录数据，否则认为是与上次抓取重复的数据
		 */
		$sql = "
			SELECT	*
			FROM	`$dbname`.`$tbname`
			WHERE	text_id < $text_id
			ORDER BY
					date_and_time DESC
			LIMIT	1
		";
		$last_row = $this->connect()->fetch($sql);
		
		foreach ($records as $record) {
			$recorded++;
			$fields_values = array();
			foreach ($columns as $i => $field) {
				$name = $field['field'];
				$column_type = $table_fields[$name]['COLUMN_TYPE'];
				
				if ($column_type == 'float') {
					$fields_values[$name] = floatval($record[$i]);
					
				} elseif ($column_type == 'varchar(255)') {
					$fields_values[$name] = "'".addslashes($record[$i])."'";
					
				} elseif ($column_type == 'int(11)') {
					$fields_values[$name] = "'".intval($record[$i])."'";
					
				} elseif ($column_type == 'datetime') {
					$fields_values[$name] = "'".addslashes($record[$i])."'";
					$record_time = strtotime($record[$i]);
					$record_datetime = date('Y-m-d H:i:s', $record_time);
					
					$start_time = is_null($start_time) ? $record_time : min($start_time, $record_time);
					$end_time = is_null($end_time) ? $record_time : max($end_time, $record_time);
				} else {
					throw new Exception('Textdata.php@252');
				}
			}
			
			// 拼出字段集
			if ($recorded == 1) {
				$fileds = " (
					text_id, basin_id, infotype_id, `".implode("`,`", array_keys($fields_values))."`
				) VALUES";
			}
			
			$last_time = strtotime($last_row['date_and_time']);
			if (!$last_time || $last_time < $record_time) {
				$values[] .= "($text_id, $basin_id, $infotype_id, ".implode(",", $fields_values).")";
			}
		}
		
		// 进行一些必要的清理
		$this->clearRealtimeDataRecords($dbname, $tbname, $text_id, $start_time, $end_time);
		if (!empty($values)) {
			$sql = "
				INSERT INTO
						`$dbname`.`$tbname` $fileds"
				.implode(",\n", $values);
			$this->connect()->query($sql);
		}
		
		/**
		 * 统计必要的数据
		 */
		$sql = "
			SELECT	COUNT(*)
			FROM	textdata AS t
			JOIN	`$dbname`.`$tbname` AS d
				ON	t.id = d.text_id
			WHERE	t.id = $text_id";
		$inserted = intval($this->connect()->fetchOne($sql));
		
		if ($start_time && $end_time) {
			$sql = "
				UPDATE	textdata
				SET		start_time = '".addslashes($start_time)."',
						end_time = '".addslashes($end_time)."',
						inserted_num = $inserted,
						records_num = $recorded
				WHERE	id = $text_id";
			$this->connect()->query($sql);
		}
		
		return $text_id;
	}
	
	// 从保存后的表中统计 field 的起始／结束时间
	function updateTextdataLayerInfo($basin_id, $infotype_id, $station_strid) {
		$basin_id	= intval($basin_id);
		$infotype_id	= intval($infotype_id);
		
		$dbname = $this->getRealtimeDataDbname();
		$tbname = $this->getRealtimeDataTbname($basin_id, $infotype_id, $station_strid);
		// 表还不存在说明抓取内容为空，之前没有创建表
		$tableDesc = $this->connect('realtime-data')->describeTable($tbname);
		if (!$tableDesc) {
			return true;
		}
		
		/**
		 * 从表结构中取得字段信息
		 */
		$modelLayer = $this->getModel('Layer');
		$table_fields = array();
		foreach ($tableDesc as $row) {
			$name = $row['COLUMN_NAME'];
			if (in_array($name, array('id', 'basin_id', 'infotype_id', 'text_id', 'station', 'date_and_time'))) {
				continue;
			}
			
			$sql = "
				SELECT	`$name`
						, MAX(date_and_time) endtime
						, MIN(date_and_time) begintime
						, SUM(IF(`$name` > -1000 AND `$name` < -999, 0, 1)) records
				FROM	`$dbname`.`$tbname`
				WHERE	`$name` IS NOT NULL
					AND	`$name` != 0.0
					AND	!(`$name` > -1000 AND `$name` < -999)
			";
			$row = $this->connect()->fetch($sql);
			
			$sql = "
				SELECT	id
				FROM	station_layer
				WHERE	basin_id = $basin_id
					AND	infotype_id = $infotype_id
					AND	station_strid = '" . addslashes($station_strid) . "'
					AND	field = '" . addslashes($name) . "'
			";
			$id = $this->connect()->fetchOne($sql);
			if ($id) {
				$sql = "
					UPDATE	station_layer
					SET	id = id"
						. ($row['begintime'] ? "
							, begintime = '" . $row['begintime'] . "'" : "")
						. ($row['endtime'] ? "
							, endtime = '" . $row['endtime'] . "'" : "")
						. ($row['records'] ? "
							, records = " . intval($row['records']) : "")
						. "
					WHERE	id = $id
				";
				$this->connect()->query($sql);
			} else {
				$sql = "
					INSERT INTO
							station_layer
					SET	basin_id = $basin_id
						, infotype_id = $infotype_id
						, station_strid = '" . addslashes($station_strid) . "'
						, field = '" . addslashes($name) . "'"
							. ($row['begintime'] ? "
						, begintime = '" . $row['begintime'] . "'" : "")
							. ($row['endtime'] ? "
						, endtime = '" . $row['endtime'] . "'" : "")
							. ($row['records'] ? "
						, records = " . intval($row['records']) : "");
				$this->connect()->query($sql);
			}
		}
	}
	
	function createRealtimeDataTable($text_id, $columns, $dbname, $tbname, $basin_id, $infotype_id, $station) {
		$fields = array();
		$fieldname_values = array();
		foreach ($columns as $row) {
			$name = $row['field'];
			
			$row['unit'] = isset($row['unit']) ? $row['unit'] : '';
			$row['demo'] = isset($row['demo']) ? $row['demo'] : '';
			// 有的时间列名为 'data_and_time'，把它纠正过来
			if ($name == 'date_and_time') {
				$fields[$name] = "`$name` DATETIME";
			} elseif ($name == 'station') {
				$fields[$name] = "`$name` VARCHAR(255)";
			} else {
				// 把除时间列和station列以外的所有列都当做数据
				$fields[$name] = "`$name` float";
			}
			$descriptor = isset($row['descriptor']) ? $row['descriptor'] : '';
			$fieldname_values[] = "(
				$basin_id
				, $infotype_id
				, '".addslashes($station)."'
				, $text_id
				, '".addslashes($name)."'
				, '".addslashes($row['fullname'])."'
				, '".addslashes($descriptor)."'
				, '".addslashes($row['demo'])."'
				, '".addslashes($row['unit'])."'
			)";
		}
		
		$sql = "CREATE TABLE IF NOT EXISTS `$dbname`.`$tbname` (
			`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`basin_id` INT NOT NULL,
			`infotype_id` INT NOT NULL,
			`text_id` INT NOT NULL"
			.(empty($fields) ? "" : ",
			".implode(" NULL,
			", $fields))." NULL,
			KEY `station` (`station`),
			KEY `basin_id` (`basin_id`),
			KEY `infotype_id` (`infotype_id`)
		) ENGINE = MYISAM ;";
		
		$this->connect()->query($sql);
	}
	function clearRealtimeDataRecords($dbname, $tbname, $text_id, $start_time, $end_time) {
		/**
		 * 删除不是原始文本的数据记录
		 */
		$sql = "
			SELECT	d.text_id
			FROM	`$dbname`.`$tbname` AS d
			LEFT JOIN
					textdata AS t
				ON	d.text_id = t.id
				AND	d.basin_id = t.basin_id
				AND	d.infotype_id = t.infotype_id
				AND	d.station = t.station_strid
			WHERE	t.id IS NULL
			GROUP BY
					d.text_id
		";
		$rows = $this->connect()->fetchAll($sql);
		foreach ($rows as $row) {
			$sql = "
				DELETE FROM
						`$dbname`.`$tbname`
				WHERE	text_id = $row[text_id]
			";
			$this->connect()->query($sql);
		}
		
		/**
		 * 保证当前文本所含时间段内无重复数据
		 */
		$sql = "
			DELETE FROM
					`$dbname`.`$tbname`
			WHERE	text_id >= $text_id
				AND	date_and_time >= '$start_time'
				AND	date_and_time <= '$end_time'
		";
		$this->connect()->query($sql);
	}
	
	public function getUpdatingVersion() {
		$modelStation = $this->getModel('Station');
		
		$sql = "
				SELECT	basin_id
						, infotype_id
						, station_strid
						, version
				FROM	`" . $this->getTable() . "`
				WHERE	status = 'current'
		";
		//pre($this->connect('realtime_tool')->fetchAll($sql),1);
		
		$sql = "
			SELECT	s.basin_id
					, s.infotype_id
					, s.station_strid
					, s.descriptor AS station_descriptor
					, IF (t.version, t.version, 0) AS text_version
			FROM	`" . $modelStation->getTable() . "` AS s
			LEFT JOIN (
				$sql
			) AS t
				ON	s.basin_id		= t.basin_id
				AND	s.infotype_id	= t.infotype_id
				AND	s.station_strid	= t.station_strid
			WHERE	s.status = 'current'
			GROUP BY
					t.version
		";
		// 取消注释以方便查看当前 current 状态下都有哪些版本
		//pre($this->connect('realtime_tool')->fetchAll($sql),1);
		
		$sql = "
			SELECT
				IF (COUNT(*) > 1, MAX(text_version), MAX(text_version) + 1)
			FROM (
				$sql
			) AS t
		";
		
		return $this->connect()->fetchOne($sql);
	}
	public function getTextdataByVersion($basin_id, $infotype_id, $station_strid, $version) {
		$basin_id		= intval($basin_id);
		$infotype_id	= intval($infotype_id);
		$station_strid	= strval($station_strid);
		$version		= intval($version);
		
		$sql = "
			SELECT	" . $this->getPropertiesString() . "
			FROM	`" . $this->getTable() . "`
			WHERE	basin_id		= $basin_id
				AND	infotype_id		= $infotype_id
				AND	station_strid	= '".addslashes($station_strid)."'
				AND	version			= $version
		";
		return $this->connect()->fetch($sql);
	}
	
	public function setStatus($basin_id, $infotype_id, $station_strid, $version, $status) {
		$basin_id		= intval($basin_id);
		$infotype_id	= intval($infotype_id);
		$version		= intval($version);
		$sql = "
			UPDATE	`" . $this->getTable() . "`
			SET		status = '" . addslashes($status) . "'
			WHERE	basin_id		= $basin_id
				AND	infotype_id		= $infotype_id
				AND	station_strid	= '".addslashes($station_strid)."'
				AND	version	= $version
		";
		
		return $this->connect()->query($sql);
	}
	
	protected function ingestHtml($url) {
		$info = array($url);
	
		$ch = curl_init($url);
		$info[] = $ch;
	
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
		$html = curl_exec($ch);
		curl_close($ch);
		$info[] = $html;
	
		if ($html) {
			return $html;
		} else {
			return false;
		}
	}
	public function ingestTextdata($BasinID, $DataType, $StationID, $StationDescriptor, &$StationCode) {
		global $cfg;
		$BasinID = intval($BasinID);
		$DataType = intval($DataType);
		$StationID = strval($StationID);
		
		$url = $cfg['urls']['realtime']."?Type=Table&BasinID=$BasinID&DataType=$DataType&StationID=$StationID";
		$html = $this->ingestHtml($url);
		//$html = '<p><a id="ctl00_ctl00_cphContentSection_MainContentArea_ctl00_OriginalFile" href="/forecasting/data/hydro/tables/ATH-RATHATH-WL.txt">View File</a></p>';
		
		/**
		 * @date 2012-01-25 21:27 初三
		 * @todo parse 070C001 in below title
		 *   Chinchaga River near High Level(07OC001) - Table
		 */
		if (preg_match("/<h1>$StationDescriptor.*?\((.*?)\)/is", $html, $m)) {
			$StationCode = $m[1];
		}
		
		if (preg_match_all('/href="([^>]*)">view file<\/a>/is', $html, $m)) {
			$url = $cfg['urls']['realtime_text'].$m[1][0];
			return $this->ingestHtml($url);
		} else {
			return false;
		}
	}
	
	/**
	 * @author malian 2010-05-19
	 * 匹配列信息
	 */
	public function parseContent($text, $station_strid) {
		$lines = preg_split('/\r\n|\r|\n/', $text);
		unset($text);
	
		$columns = array();
		$records = array();
	
		$last_type	= '';
		$head		= false;
		$record		= '';
		foreach ($lines as $line) {
			if (trim($line) == '') continue;
			$type = $this->line_type($line, $station_strid);
			//pre(array($last_type, $type, $line));
			
			if ($last_type == 'head_start') {
				if ($type == 'head_start') {
					$type = 'head_end';
				} else {
					$type = 'head_start';
					//AWP_RE_MATCH_HEAD = '/\|\s*((\w+( \w+)*)(\s*\(.*?\))?)\s*?(?=\|)/'
					if (!$head && preg_match_all(AWP_RE_MATCH_HEAD, $line, $m)) {
						foreach ($m[2] as $i => $val) {
							$columns[$i]['field'] = $val;
							$columns[$i]['fullname'] = $m[1][$i];
							$columns[$i]['length'] = strlen($m[0][$i]);
						}
			
						$head = true;
					} else {
						if ($head && preg_match_all('/\|\s*([^\|]*?)\s*(?=\|)/', $line, $m)) {
							foreach ($m[1] as $i => $val) $columns[$i]['unit'] = $val;
							$head = false;
						}
					}
				}
			} elseif ($last_type == 'head_end') {
				if ($type == 'descriptor') {
					$type = 'head_end';
			
					if (preg_match_all('/\s*?(\w+( \w+)*)\s*=\s*(\w+( \(?\w+\)?)*)\s*/', $line, $m)) {
						foreach ($m[1] as $i => $field) {
							foreach ($columns as $j => $column) {
								if ($column['field'] == $field) $columns[$j]['descriptor'] = $m[3][$i];
							}
						}
					}
				} elseif ($type == 'record') {
					$type = 'head_end';
					if (preg_match_all('/(\S+( \S+)*)(\s{2,}|\s*$)/', $line, $m)) {
						if (count($columns) != count($m[1])) {
							$record = array();
							$start = 0;
							foreach ($columns as $column) {
								$record[] = trim(substr($line, $start, $column['length']));
								$start += $column['length'];
							}
						} else {
							$record = $m[1];
						}
						foreach ($columns as $j => $column) {
							if ($record[$j] !== '') $columns[$j]['demo'] = $record[$j];
						}
					}
				} else {
					$type = '';
				}
			} elseif (substr($last_type, 0, 7) == 'head-v2' || $type == 'head-v2') {
				if ($type == 'head-v2') {
					$parts = explode(',', $line);
					foreach ($parts as $i => $val) {
						//$columns[$i]['field'] = $val;
						$columns[$i]['fullname'] = $val;
					}
					$type = 'head-v2-start';
				} elseif ($last_type == 'head-v2-start') {
					$type = 'head-v2-unit';
					$parts = explode(',', $line);
					foreach ($parts as $i => $val) {
						//$columns[$i]['field'] = $val;
						$columns[$i]['unit'] = $val;
					}
				} elseif ($last_type == 'head-v2-unit') {
					if ($type == 'record-v2') {
						$type = 'head-v2-unit';
						// 07OC001,2012-01-21 00:00:00,0.734
						$parts = explode(',', $line);
						if (count($columns) != count($parts)) {
							pre('Oops?',1);
						} else {
							$records[] = $parts;
						}
						//foreach ($columns as $j => $column) {
						//	if ($record[$j] !== '') $columns[$j]['demo'] = $record[$j];
						//}
					}
				}
			}
	
			$last_type = $type;
		}
		
		foreach ($columns as &$row) {
			if (!isset($row['field']) && isset($row['fullname'])) {
				$field = $row['fullname'];
			} elseif ($row['field']) {
				$field = $row['field'];
			} else {
				throw new Exception('Could not found "field" information');
			}
			
			$name = preg_replace('/\s+/', '_', strtolower($field));
		
			$row['unit'] = isset($row['unit']) ? $row['unit'] : '';
			$row['demo'] = isset($row['demo']) ? $row['demo'] : '';
			// 有的时间列名为 'data_and_time'，把它纠正过来
			if ($name == 'date_and_time'
					|| $name == 'data_and_time'
					|| $name == 'date_&_time_in_mst') {
				$name = 'date_and_time';
			} elseif ($name == 'station' || $name == 'station_no.') {
				$name = 'station';
			} else {
				// 把除时间列和station列以外的所有列都当做数据
				$fields[$name] = "`$name` float";
			}
			
			$row['field'] = $name;
		}
		
		return array('columns' => $columns, 'records' => $records);
	}
	protected function line_type($line, $StationID = '##') {
		$types = array(
				'descriptor',
				'station',
				'comment',
				'comment_start',
				'head-v2',
				'head',
				'head_start',
				'record-v2',
				'record',
				'last_update',
				'update_time',
				'page_number',
		);
		foreach ($types as $type) {
			if ($this->is_type($type, $line, $StationID)) return $type;
		}
		return '#';
	}
	/**
	 * 
|----------------------------------------------------------------------------------------|
|                                                                                        |
| Data provided at this site are provisional and preliminary in nature and are not       |
| intended for use by the general public. They are automatically generated by remote     |
| equipment that may not be under Alberta Government control and have not been reviewed  |
| or edited for accuracy. These data may be subject to significant change when manually  |
| reviewed and corrected. Please exercise caution and carefully consider the provisional |
| nature of the information provided.                                                    |
|                                                                                        |
| The Government of Alberta assumes no responsibility for the accuracy or completeness   |
| of these data and any use of them is entirely at your own risk.                        |
|                                                                                        |
|----------------------------------------------------------------------------------------|

             Alberta Environment                          Page: 1
         REAL TIME HYDROMETRIC REPORT    Last Updated: 2009-11-14
                                                 Time:   20:19:12
RBIRALIC : Birch River below Alice Creek
|=============|======================|=============|=============|
| Station     | Data and Time        | Water Level |    Flow     |
|             | (Mountain Standard)  |     (m)     |   (m3/s)    |
|=============|======================|=============|=============|
  RBIRALIC      2009-11-12 08:30:00          4.489      -999.00
	 */
	protected function is_type($type, $line, $StationID = '##') {
		switch ($type) {
			case 'comment_start':
				return preg_match('/^\|-+\|$/', $line);
			case 'comment':
				return preg_match('/^\|-*?[^\-=].*?\|$/', $line);
			case 'head_start':
				return preg_match('/^(\|=+(?=\|))+\|$/', $line);
			case 'head':
				return preg_match('/^(\|\s*[^\|]*?\s*(?=\|))+$/', $line);
			case 'head-v2':
				// Station No.,Date & Time in MST,Water Level
				return preg_match('/^Station No\.,.+$/', $line);
			case 'record':
				//   RBIRALIC      2009-11-12 08:30:00          4.489      -999.00
				return preg_match('/^\s*?'.$StationID.'\s*([^\:\s]+( \S+)*(\s{2,}|\s*$))+/', $line);
			case 'record-v2':
				// 07OC001,2012-01-21 00:00:00,0.734
				return preg_match('/^'.$StationID.'(,[^,]*)+/', $line);
			case 'last_update':
				return preg_match('/^.*?Last Updated\:\s*(\d{4}-\d{2}-\d{2})\s*$/', $line);
			case 'update_time':
				return preg_match('/^.*?Time\:\s*(\d{2}\:\d{2}\:\d{2})\s*$/', $line);
			case 'page_number':
				return preg_match('/^.*?Page\:\s*(\d+)\s*$/', $line);
			case 'station':
				return preg_match('/^\s*?'.$StationID.'\s*\:\s*((\w+ ?)+)\s*$/', $line);
			case 'descriptor':
				return preg_match('/^(\s*(\w+ ?)+\s*=\s*(\w+ ?)(\(?\w+\)? ?)+\s*)+$/', $line);
			default:
				return false;
		}
	}
}
?>