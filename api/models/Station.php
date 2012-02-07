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
			'Id' => 'layerid',
			'Abbrev' =>	'field',
			'Description' => 'description',
			'OriginTextId' => 'text_id',
	);
	public function getTable() {
		return 'station';
	}
	public function getDatabase() {
		return 'realtime-tool';
	}
	
	
	function getStationListByLayer($layerid, $station_descriptor) {
		$sql = "
			SELECT	s.station_strid
					, s.descriptor station
					, sl.basin_id
					, sl.infotype_id
					, sl.begintime
					, sl.endtime
			FROM	station s
			JOIN	station_layer sl	-- station_layer table is created from awp_b database, so it is more reliable
				ON	s.station_strid = sl.station_strid
				AND	s.basin_id = sl.basin_id
				AND	s.infotype_id = sl.infotype_id
		--	JOIN	layer l			-- use directly 'strid' instead of 'numberid' for now
		--		ON	sf.field = l.field
			WHERE	s.status = 'current'
				AND	sl.field = '" . addslashes($layerid) . "'
				AND	s.descriptor = '" . addslashes($station_descriptor) . "'
		";
		
		return $this->connect('realtime-tool')->fetchAll($sql);
	}
	function getStationList() {
		$sql = "
			SELECT	s.id
					, s.station_strid AS strid
					, s.descriptor AS description
					, '' AS code
			FROM	`" . $this->getTable() . "` AS s
			WHERE	s.status = 'current'
			GROUP BY
					s.station_strid	-- 669 strid with 1023 station
			ORDER BY
					description
		";
		
		$rows = $this->connect()->fetchAll($sql);
		
		foreach ($rows as $i => &$row) {
			$row['id'] = intval($row['id']);
		}
		
		return $rows;
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
		$total_in_mysql = $this->connect('realtime-tool')->fetchColumn($sql);
		
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
	public function createPgRealtimeStationList() {
		$sql = "SELECT 1 FROM pg_tables WHERE tablename = 'station_realtime'";
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
		
		$sql = "
			SELECT	COUNT(DISTINCT descriptor)	-- Note: count distinct, should be 691
			FROM	station s
			WHERE	s.status = 'current'
		";
		$total_in_mysql = $this->connect('realtime-tool')->fetchOne($sql);
		
		if ($total_in_pgsql != $total_in_mysql) {
			$sql = "
				SELECT	descriptor
				FROM	station s
				WHERE	s.status = 'current'
				GROUP BY
						descriptor	-- Note: here we use 'descriptor' other than 'station_strid', case insensitive
			";
			$rows = $this->connect('realtime-tool')->fetchAll($sql);
			
			$values = array();
			foreach ($rows as $i => $row) {
				$values[] = "(" . ($i + 1) . ", '" . str_replace("'", "''", $row['descriptor']) . "')";
			}
			if (!empty($values)) {
				$this->connect('geometry')->query("TRUNCATE TABLE station_realtime");
				
				$sql = "
					INSERT INTO
							station_realtime (
								gid,
								station_descriptor
							)
					VALUES ".implode("
					, ", $values);
				$this->connect('geometry')->query($sql);
			}
		}
		
		return 'station_realtime';
	}
}
?>
