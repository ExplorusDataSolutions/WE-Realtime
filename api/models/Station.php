<?php
require_once 'Table.php';

class WERealtime_Model_Station extends ML_Model_Table {
	protected static $_instance = null;
	
	public static function getInstance() {
		if (self::$_instance === null) {
			self::$_instance = new self ();
		}
		return self::$_instance;
	}
	
	protected $properties = array (
			'Id' => 'station_strid',
			'Code' => 'code',
			'Description' => 'descriptor',
			'BasinId' => 'basin_id',
			'DatatypeId' => 'infotype_id',
			'Version' => 'version',
			'UpdateTime' => 'update_time',
	);
	public function getTable() {
		return 'station';
	}
	public function getDatabase() {
		return 'realtime-tool';
	}
	public function getDatatypeTable() {
		return 'datatype';
	}
	
	
	public function getStationByDescription($description) {
		return $this->getRecordByProperty('Description', $description);
	}
	
	public function getLatestVersion() {
		$sql = "
			SELECT	MAX(`" . $this->getPropertyField('Version') . "`)
			FROM	`" . $this->getTable() . "`
			WHERE	TRUE
		";
		return $this->connect()->fetchOne($sql);
	}
	public function getVersionList() {
		$sql = "
			SELECT	`" . $this->getPropertyField('Version') . "`
					, COUNT(*) station_total
					, `" . $this->getPropertyField('UpdateTime') . "`
			FROM	`" . $this->getTable() . "`
			GROUP BY
					`" . $this->getPropertyField('Version') . "`
		";
		return $this->connect()->fetchAll($sql);
	}
	public function getStationList($version = null, $distinct = false) {
		if (is_null($version)) {
			$version = $this->getLatestVersion();
		}
		$version = intval($version);
		
		$options = array(
				'WHERE' => "`" . $this->getPropertyField('Version') . "` = '$version'",
				'ORDERBY' => $this->getPropertyField('Description'),
				'GROUPBY' => $distinct ? 'station_strid' : '',
		);
		
		$objs = $this->getRecordList($options);
	
		foreach ($objs as $obj) {
			$obj->BasinId = intval($obj->BasinId);
			$obj->DatatypeId = intval($obj->DatatypeId);
			// Id2
			$obj->Id2 = $obj->Id . "_" . $obj->BasinId . "_" . $obj->DatatypeId;
		}
		
		return $objs;
	}
	public function getStationListWithStatus($version = null) {
		if ($version == null) {
			$version = $this->getLatestVersion();
		}
		$version = intval($version);
		
		$list2 = $this->getStationList($version);
		$list1 = $this->getStationList($version - 1);
		
		$objMap = array();
		foreach ($list1 as $obj) {
			$obj->Status = 'deleted';
			$objMap[$obj->Id2] = $obj;
		}
		
		foreach ($list2 as $i => $obj) {
			$Id2 = $obj->Id2;
			if (isset($objMap[$Id2])) {
				$o = $objMap[$Id2];
				if ($obj->Description == $o->Description) {
					$obj->Status = 'same';
				} else {
					$obj->Status = 'changed';
					$obj->oldDescription = $o->Description;
				}
				
				unset($objMap[$Id2]);
			} else {
				$obj->Status = 'new';
			}
		}
		
		return array_values(array_merge($objMap, $list2));
	}
	function getListByBasinInfotypeId($basin_id, $infotype_id) {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		
		$sql = "
			SELECT	b.basin_id
					, b.descriptor basin_descriptor
					, i.infotype_id
					, i.descriptor infotype_descriptor
					, s.station_strid
					, s.descriptor station_descriptor
					, s.html_version
			FROM	station s
			JOIN	stations_html sh
			 	ON	s.html_id = sh.id
			JOIN	basin_infotype i
			 	ON	sh.iid = i.id
			JOIN	basin b
				ON	i.bid = b.id
			WHERE	b.basin_id = $basin_id
				AND	i.infotype_id = $infotype_id
				AND	s.status = 'current'
		";
		return $this->connect('realtime-tool')->fetchAll($sql);
	}
	function getStationListGroupByField() {
		$sql = "
			SELECT	s.id
					, sf.field
					, s.station_strid
					, s.descriptor station_descriptor
					, s.html_version
			FROM	station s
			JOIN	station_field sf
				ON	s.station_strid = sf.station_strid
			WHERE	s.status = 'current'
			GROUP BY
					s.station_strid, sf.field
		";
		return $this->connect('realtime-tool')->fetchAll($sql);
	}
	function getStation($basin_id, $infotype_id, $station_strid) {
		$basin_id		= intval($basin_id);
		$infotype_id	= intval($infotype_id);
		$station_strid	= strval($station_strid);
		
		$sql = "
			SELECT	b.basin_id
					, b.descriptor basin_descriptor
					, i.infotype_id
					, i.descriptor infotype_descriptor
					, s.station_strid
					, s.descriptor station_descriptor
					, s.html_version
			FROM	station s
			JOIN	stations_html sh
			 	ON	s.html_id = sh.id
			JOIN	basin_infotype i
			 	ON	sh.iid = i.id
			JOIN	basin b
				ON	i.bid = b.id
			WHERE	b.basin_id = $basin_id
				AND	i.infotype_id = $infotype_id
				AND	s.station_strid = '" . addslashes($station_strid) . "'
				AND	s.status = 'current'
		";
		return $this->connect('realtime-tool')->fetch($sql);
	}
	function getFieldList($tbname) {
		$sql = "SHOW TABLES LIKE '".addslashes($tbname)."'";
		$tables = $this->connect('realtime_data')->fetchAll($sql);
		
		$fields = array();
		if (!empty($tables)) {
			$sql = "DESCRIBE `".addslashes($tbname)."`";
			$rows = $this->connect('realtime_data')->fetchAll($sql);
			
			foreach ($rows as $row) {
				if ($row['Type'] == 'float') $fields[] = $row['Field'];
			}
		}
		
		return $fields;
	}

	function getGeometryStationList() {
		$sql = "
			SELECT	stn_name
					, ST_X(the_geom) AS x
					, ST_Y(the_geom) AS y
			FROM	station_join2";
		return $this->connect('geometry')->fetchAll($sql);
	}

	function getListByField($layer) {
		// 从 PostgreSQL 的 station_join2 表提取 stn_name,x,y 信息生成 MySQL 的 station_join 表
		$this->prepare_station_join();
		
		$sql = "
			SELECT	sf.id
					, sf.basin_id
					, sf.infotype_id
					, sf.station_strid
				--	, sf.text_id
					, sf.field
				--	, sf.field_full
				--	, sf.descriptor
				--	, sf.demo
				--	, sf.unit
					, t.x
					, t.y
					, t.stn_name
					, b.descriptor basin_descriptor
					, s.descriptor station_descriptor
			FROM	station_field sf
			JOIN	station s
				ON	sf.station_strid = s.station_strid
				AND	sf.basin_id = s.basin_id
				AND	sf.infotype_id = s.infotype_id
			JOIN	stations_html sh
				ON	s.html_id = sh.id
				AND	sh.version = (SELECT MAX(version) FROM stations_html)
			JOIN	basin b
				ON	sf.basin_id = b.basin_id
				AND	b.version = (SELECT MAX(version) FROM basin)
			JOIN	station_join t
				ON	s.descriptor = t.stn_name
			WHERE	sf.field = '" . addslashes($layer) . "'
			GROUP BY
					sf.basin_id, sf.infotype_id, sf.station_strid";
		return $this->connect('realtime-tool')->fetchAll($sql);
	}
	
	/**
	 * 由 basin_id, infotype_id, station_strid 获取实时数据的相关信息：
	 * $info = array(
	 *		'start_date' => '',	�?早记录的时间
	 *		'end_date' => '',	�?晚记录的时间
	 *		'total' => 0,		记录总数
	 *		'dateList' => array()	年�?�月列表
	 *	);
	 */
	function getDataInfo($basin_id, $infotype_id, $station_strid, $year = 0, $month = 0) {
		$basin_id		= intval($basin_id);
		$infotype_id	= intval($infotype_id);
		$station_strid	= preg_replace('/[^0-9a-zA-Z]/', '', $station_strid);
		
		// 2011-04-29 Ma Lian, upper case $station_strid can not match lower case tablename,
		// when mysql is running on Linux
		$tbname		= 'basin_' . $basin_id
						. '_datatype_' . $infotype_id
						. '_' . strtolower($station_strid);
		$sql		= "SHOW TABLES LIKE '$tbname'";
		$tableList	= $this->connect('realtime_data')->fetchAll($sql);
		
		if (empty($tableList)) {
			return false;
		}
		
		$sql = "
			SELECT	MIN(date_and_time) start_date
					, MAX(date_and_time) end_date
					, COUNT(*) total
			FROM	`".addslashes($tbname)."`
			WHERE	TRUE
		";
		$info = $this->connect('realtime_data')->fetch($sql);
		
		if (empty($info)) {
			$info = array(
				'start_date' => '',
				'end_date' => '',
				'total' => 0
			);
		}
		
		// 统计所有记录所属年、月
		$sql = "
			SELECT	YEAR(date_and_time) year
					, MONTH(date_and_time) month
					, COUNT(*) number
			FROM	`".addslashes($tbname)."`
			WHERE	TRUE
			GROUP BY
					YEAR(date_and_time), MONTH(date_and_time)
			ORDER BY
					YEAR(date_and_time) DESC, MONTH(date_and_time) DESC
		";
		$info['dateList'] = $this->connect('realtime_data')->fetchAll($sql);

		// 字段信息
		$RealtimeField = ML::instance('RealtimeField');
		$info['fieldList'] = $RealtimeField->getListByStation($basin_id, $infotype_id, $station_strid);
		
		return $info;
	}
	
	/**
	* 获得记录数
	*
	* @param    array
	* @return   void
	*/
	function getDataTotal($basin_id, $infotype_id, $station_strid, $year = 0, $month = 0) {
		$basin_id		= intval($basin_id);
		$infotype_id	= intval($infotype_id);
		$station_strid	= preg_replace('/[^0-9a-zA-Z]/', '', $station_strid);
		$year	= intval($year);
		$month	= intval($month);
		
		$tbname		= 'basin_'.$basin_id.'_datatype_'.$infotype_id.'_'.strtolower($station_strid);
		$sql		= "SHOW TABLES LIKE '$tbname'";
		$tableList	= $this->connect('realtime_data')->fetchAll($sql);
		
		$result = array();
		if (!empty($tableList)) {
			global $cfg;
			
			$RealtimeField		= ML::instance('RealtimeField');
			$fieldList = $RealtimeField->getListByStation($basin_id, $infotype_id, $station_strid);
			
			$whereField	= array();
			$fNameList		= array();
			foreach ($fieldList as $row) {
				$whereField[] = "AND !(`$row[field]` > -1000 AND `$row[field]` <= -999)";
				$fNameList[] = $row['field'];
			}
			
			$sql = "
				SELECT	COUNT(*)
				FROM	`".addslashes($tbname)."`
				WHERE	TRUE"
					. ($year ? "
					AND	YEAR(date_and_time) = $year" : "")
					. ($month ? "
					AND	MONTH(date_and_time) = $month" : "")
					. (empty($whereField) ? "" : "
					" . implode("
					", $whereField));
			$total = $this->connect('realtime_data')->fetchColumn($sql);
		}
		
		return $total;
	}
	
	/**
	 * 由年、月获取实时数据记录
	 */
	function getData($basin_id, $infotype_id, $station_strid, $year = 0, $month = 0, $start = 0, $limit = 20) {
		$basin_id		= intval($basin_id);
		$infotype_id	= intval($infotype_id);
		$station_strid	= preg_replace('/[^0-9a-zA-Z]/', '', $station_strid);
		$year	= intval($year);
		$month	= intval($month);
		$start	= intval($start);
		$limit	= intval($limit);
		
		$tbname		= 'basin_'.$basin_id.'_datatype_'.$infotype_id.'_'.strtolower($station_strid);
		$sql		= "SHOW TABLES LIKE '$tbname'";
		$tableList	= $this->connect('realtime_data')->fetchAll($sql);
		
		$result = array();
		if (!empty($tableList)) {
			global $cfg;
			
			$RealtimeField		= ML::instance('RealtimeField');
			$fieldList = $RealtimeField->getListByStation($basin_id, $infotype_id, $station_strid);
			
			$whereField	= array();
			$fNameList		= array();
			foreach ($fieldList as $row) {
				$whereField[] = "CEILING(`$row[field]`) != -999";
				$fNameList[] = $row['field'];
			}
			
			$sql = "
				SELECT	id
						, date_and_time date
						, UNIX_TIMESTAMP(date_and_time) time
						, " . implode("
						, ", $fNameList) . "
				FROM	`".addslashes($tbname)."`
				WHERE	TRUE"
					.($year ? "
					AND	YEAR(date_and_time) = $year" : "")
					.($month ? "
					AND	MONTH(date_and_time) = $month" : "")
					. (empty($whereField) ? "" : "
					AND	(" . implode("
						OR	", $whereField)) . "
					)
				ORDER BY
						date_and_time DESC
				LIMIT $start, $limit
			";
			$result = $this->connect('realtime_data')->fetchAll($sql);
		}
		
		return $result;
	}
	
	/**
	 * 从 PostgreSQL 数据库的 station_join2 表中提取出 stn_name 字段，插入 MySQL 数据库的 station_join 表，
	 * 方便与实时数据中的 station_descriptor 进行对比，从而取得 x, y 坐标信息
	 */
	function prepare_station_join() {
		$sql = "
			CREATE TABLE IF NOT EXISTS `station_join` (
			  `gid` int(11) NOT NULL AUTO_INCREMENT,
			  `aenv_stati` varchar(255) DEFAULT NULL,
			  `stn_name` varchar(255) NOT NULL,
			  `x` double NOT NULL,
			  `y` double NOT NULL,
			  PRIMARY KEY (`gid`),
			  KEY `aenv_stati` (`aenv_stati`),
			  KEY `stn_name` (`stn_name`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8
			COMMENT='该表数据通过提取PostgreSQL.station_join2表的stn_name字段及 x, y而来'";
		$this->connect('realtime-tool')->query($sql);
		
		$sql = "
			SELECT	COUNT(*)
			FROM	station_join2";
		$total_in_pgsql = $this->connect('geometry')->fetchColumn($sql);
		
		$sql = "
			SELECT	COUNT(*)
			FROM	station_join";
		$total_in_mysql = $this->connect()->fetchColumn($sql);
		
		if ($total_in_pgsql != $total_in_mysql) {
			$sql = "
				SELECT	gid
						, aenv_stati
						, stn_name
						, ST_X(the_geom) AS x
						, ST_Y(the_geom) AS y
				FROM	station_join2
				WHERE	TRUE
			";
			$statiList = $this->connect('geometry')->fetchAll($sql);
			
			$values = array();
			foreach ($statiList as $row) {
				$values[] = "($row[gid]"
					.", ".($row['aenv_stati'] ? "'".addslashes($row['aenv_stati'])."'" : "NULL")
					.", '".addslashes($row['stn_name'])."'"
					.", $row[x], $row[y])";
			}
			if (!empty($values)) {
				$this->connect('realtime-tool')->query("TRUNCATE TABLE station_join");
				
				$sql = "
					INSERT INTO	station_join (
						gid, aenv_stati, stn_name, x, y
					) VALUES ".implode("
					, ", $values);
				$this->connect('realtime-tool')->query($sql);
			}
		}
	}
	function get_station_join_list($ps = 1, $pl = 20, $sort = '', $dir = '') {
		$ps = intval($ps);
		$pl = intval($pl);
		$start = $ps * $pl - $pl;
		
		$dir = $dir == 'DESC' ? 'DESC' : 'ASC';
		$sorts = array(
			'gid' => "sj.gid $dir",
			'aenv_stati' => "sj.aenv_stati $dir",
			'occurrence' => "t.occurrence $dir, sj.aenv_stati, sj.gid",
		);
		$sort = isset($sorts[$sort]) ? $sort : 'gid';
		
		$sql = "
			SELECT	sj.gid
					, sj.aenv_stati
					, sj.stn_name
					, t.occurrence
			FROM	station_join AS sj
			LEFT JOIN	(
				SELECT	aenv_stati
						, COUNT(*) AS occurrence
				FROM	station_join
				GROUP BY	aenv_stati
			) AS t
				ON	sj.aenv_stati = t.aenv_stati
			ORDER BY	$sorts[$sort]
			LIMIT	$start, $pl";
		return $this->connect('realtime-tool')->fetchAll($sql);
	}
	function get_station_join_count() {
		$sql = "
			SELECT	COUNT(gid)
			FROM	station_join";
		return $this->connect('realtime-tool')->fetchColumn($sql);
	}
	function get_textdata_history($basin_id, $infotype_id, $station_strid) {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		$station_strid = strval($station_strid);
		
		$sql = "
			SELECT	id
					, basin_id
					, infotype_id
					, station_strid
					, text_md5
					, update_time
					, version
			FROM	textdata
			WHERE	basin_id = $basin_id
				AND	infotype_id = $infotype_id
				AND	station_strid = '".addslashes($station_strid)."'
			ORDER BY
					version DESC
		";
		return $this->connect('realtime-tool')->fetchAll($sql);
	}
	
	/**
	 * 从 MySQL 数据库的 awp 库中提取出 station list，插入 PostgreSQL 数据库的 station_realtime 表，
	 * 方便与 station_join5 中的 stn_name 进行对比，从而取得既有实时数据，又有位置数据的 stations
	 */
	public function createPgRealtimeStationList($tmp_tbname = 'station_realtime') {
		$sql = "SELECT 1 FROM pg_tables WHERE tablename = '$tmp_tbname'";
		$tb_existing = $this->connect('geometry')->fetchOne($sql);
		
		if ($tb_existing != '1') {
			/**
			 * Possible error message for PostgreSQL 8.1.23 because below line, now we remove it and it works.
			 *   ERROR:  syntax error at or near "OWNED" at character 294
			 *   ALTER SEQUENCE station_realtime_gid_seq OWNED BY station_realtime.gid;
			 */
			$sql = "
				CREATE TABLE station_realtime (
					gid integer NOT NULL,
					station_descriptor character varying(200)
				);
				CREATE SEQUENCE station_realtime_gid_seq
					START WITH 1
					INCREMENT BY 1
					NO MINVALUE
					NO MAXVALUE
					CACHE 1;"
				//ALTER SEQUENCE station_realtime_gid_seq OWNED BY station_realtime.gid;
				. "
				ALTER TABLE station_realtime ALTER COLUMN gid SET DEFAULT nextval('station_realtime_gid_seq'::regclass);
				CREATE INDEX station ON station_realtime USING btree (station_descriptor) ";
			$this->connect('geometry')->query($sql);
		}
		
		$sql = "
			SELECT	COUNT(DISTINCT station_descriptor)	-- Note: count distinct
			FROM	station_realtime";
		$total_in_pgsql = $this->connect('geometry')->fetchOne($sql);
		
		$station_latest_version = $this->getLatestVersion();
		$sql = "
			SELECT	COUNT(DISTINCT descriptor)	-- Note: count distinct, should be 691
			FROM	station s
			WHERE	s.version = '$station_latest_version'
		";
		$total_in_mysql = $this->connect()->fetchOne($sql);
		
		if ($total_in_pgsql != $total_in_mysql) {
			$objs = $this->getStationList($station_latest_version, true);
			
			$values = array();
			foreach ($objs as $i => $obj) {
				$description = str_replace("'", "''", $obj->Description);
				$values[] = "(" . ($i + 1) . ", '" . $description . "')";
			}
			if (!empty($values)) {
				$this->connect('geometry')->query("TRUNCATE TABLE $tmp_tbname");
				
				$sql = "
					INSERT INTO
							$tmp_tbname (
								gid,
								station_descriptor
							)
					VALUES	" . implode("
							, ", $values);
				$this->connect('geometry')->query($sql);
			}
		}
		
		return $tmp_tbname;
	}
	
	function fetchHtml($url) {
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
	function matchSelectHtml($html) {
		if (preg_match_all('/<select .*?<\/select>/is', $html, $m)) {
			$match = $m[0][0];
			return $match;
		} else {
			return false;
		}
	}
	function matchBasinList($match) {
		preg_match_all('/<option .*?value="(.*?)".*?>(.*?)<\/option>/is', $match, $m);
	
		$basins = array();
		foreach ($m[1] as $i => $row) {
			$basins[] = array(
					'id' => $row,
					'name' => $m[2][$i]
			);
		}
		return $basins;
	}
	public function parseBasinList() {
		global $cfg;
		$page_html = $this->fetchHtml($cfg['urls']['basins']);
		
		$select_html = $this->matchSelectHtml($page_html);
		return $this->matchBasinList($select_html);
	}
	
	public function matchStations($html) {
		if (preg_match_all('/<select .*?<\/select>/is', $html, $m)) {
			preg_match_all('/<option .*?value=".*?StationID=(.*?)".*?>([^<]*?)<\/option>/is', $m[0][0], $m);
	
			$stations = array();
			foreach ($m[1] as $i => $row) {
				if (preg_match('/^(.*)\((.+)\)\s*- Table$/', $m[2][$i], $m2)) {
					$stations[] = array(
							'id' => $row,
							'name' => $m2[1],
							'code' => $m2[2],
					);
				}
			}
	
			return $stations;
		} else {
			return false;
		}
	}
	public function parseStations() {
		$sql = "
			SELECT	d.basin_id
					, d.datatype_id
			FROM	`" . $this->getDatatypeTable() . "` d
			WHERE	d.status = 'current'
			ORDER BY
					d.basin_id, d.datatype_id
		";
		$rows = $this->connect()->fetchAll($sql);
		
		global $cfg;
		foreach ($rows as $row) {
			$basin_id = $row['basin_id'];
			$datatype_id = $row['datatype_id'];
			$url = $cfg['urls']['stations']."?Basin=$basin_id&DataType=$datatype_id";
			
			$html = $this->fetchHtml($url);
			$stations = $this->matchStations($html);
			$this->saveStations($stations, $basin_id, $datatype_id);
		}
		return $html;
	}
	public function parseStationsByBasinAndDatatype($basin_id, $datatype_id) {
		$basin_id = intval($basin_id);
		$datatype_id = intval($datatype_id);
		
		global $cfg;
		$url = $cfg['urls']['stations']."?Basin=$basin_id&DataType=$datatype_id";
		
		$html = $this->fetchHtml($url);
		$stations = $this->matchStations($html);
		
		return $stations;
	}
	public function saveStations($stations, $basin_id, $infotype_id) {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		
		$saved_amount = 0;
		if (is_array($stations)) {
			$station_strids = array();
			foreach ($stations as $i => $station) {
				$station_strid = trim($station['id']);
				$description = trim($station['name']);
				$station_code = trim($station['code']);
				if (isset($station_strids[$station_strid])) {
					$station_strids[$station_strid] .= '/'.$description;
				} else {
					$station_strids[$station_strid] = $description;
				}
			}
			foreach ($station_strids as $station_strid => $description) {
				$sql = "
					INSERT INTO	station
						SET	basin_id = $basin_id,
							infotype_id = $infotype_id,
							html_id = 0,
							html_version = 0,
							station_strid = '" . addslashes($station_strid) . "',
							descriptor = '" . addslashes($description) . "',
							update_time = NOW(),
							status = 'added'";
				$this->connect()->query($sql);
			}
		}
	}
	public function saveStation($params) {
		return $this->saveRecordByProperty($params, array('Id', 'BasinId', 'DatatypeId', 'Version'));
	}
}
?>
