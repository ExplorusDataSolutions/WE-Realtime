<?php
require_once 'Table.php';

class WERealtime_Model_Layer extends ML_Model_Table {
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
		return 'layer';
	}
	public function getDatabase() {
		return 'realtime-tool';
	}
	
	
	public function getLayerInfoById($layerId) {
		return $this->getRecordByProperty('Id', $layerId);
	}
	
	public function getServiceInfo() {
		$sql = "
			SELECT	COUNT(*) total
					, MIN(start_time) begintime
					, MAX(end_time) endtime
			FROM	textdata t
			WHERE	TRUE
		";
		$info = (array)$this->connect('realtime-tool')->fetch($sql);
		
		$bbox = $this->getServiceBBox();
		$info['bbox'] = array(
			"upperright" => array(
				"longitude" => $bbox['right'],
				"latitude" => $bbox['top']
			),
			"bottomleft" => array(
				"longitude" => $bbox['left'],
				"latitude" => $bbox['bottom']
			)
		);
		
		return $info;
	}
	private function getServiceBBox() {
		$modelStation = $this->getModel('Station');
		$tbname = $modelStation->createPgRealtimeStationList();
		
		$sql = "
			SELECT	ST_Extent(sj.geom) AS bbox
					, ST_YMax(ST_Extent(sj.geom)) AS top
					, ST_XMax(ST_Extent(sj.geom)) AS right
					, ST_YMin(ST_Extent(sj.geom)) AS bottom
					, ST_XMin(ST_Extent(sj.geom)) AS left
			FROM	station_join5 sj
			JOIN	$tbname sr
				ON	LOWER(sj.stn_name) = LOWER(sr.station_descriptor)
			WHERE	TRUE
		";
		return $this->connect('geometry')->fetch($sql);
	}
	/**
	 * Used by API - http://www.albertawater.com/awp/api/realtime/stations
	 */
	public function getStationList() {
		$modelStation = $this->getModel('Station');
		
		// 准备 postgresql 中的 stations 表
		$tbname = $modelStation->createPgRealtimeStationList();
		
		// 联合 station_join5 表，从而获取有相同 station 名称的 station 的位置信息。注意，station_join5表中stn_name有重复
		// 而且 同一个station_strid 可能对应了不同的 descriptor
		$sql = "
			SELECT	t.station
					, ST_X(sj2.geom) AS x
					, ST_Y(sj2.geom) AS y
			FROM (
				SELECT	sr.station_descriptor AS station
						, MIN(sj.gid) AS gid
				FROM	$tbname sr
				LEFT JOIN
						station_join5 AS sj
					ON	LOWER(sj.stn_name) = LOWER(sr.station_descriptor)
				GROUP BY
						sr.station_descriptor	-- 如果将来需要station的重复数据，去掉此 group by 即可
			) t
			LEFT JOIN
					station_join5 AS sj2
				ON	t.gid = sj2.gid
			WHERE	TRUE
			ORDER BY
					t.station
		";
		return $this->connect('geometry')->fetchAll($sql);
	}
	public function getLayerInfo($layerid) {
		$layerid = intval($layerid);
		
		$sql = "
			SELECT	layerid, field, description
			FROM		layer
			WHERE		layerid = $layerid
		";
		return $this->connect('realtime')->fetch($sql);
	}
	public function getLayerUnit($basin_id, $infotype_id, $station_strid, $layerid) {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
	
		$sql = "
		SELECT	unit
		FROM	station_field
		WHERE	basin_id = $basin_id
			AND	infotype_id = $infotype_id
			AND	station_strid = '" . addslashes($station_strid) . "'
			AND	field = '" . addslashes($layerid) . "'
		";
		return $this->connect('realtime')->fetchOne($sql);
	}
	public function getLayerInfoByStation() {
		$sql = "
			SELECT	s.descriptor
					, sl.field
					, l.layerid
					, sl.begintime
					, sl.endtime
					, sl.endtime > DATE_SUB(NOW(), INTERVAL 4 DAY) last4days
					, sl.records
			FROM	station s
			LEFT JOIN		-- use 'LEFT JOIN' to include those have no fields info in table station_layer
					station_layer sl
				ON	sl.basin_id = s.basin_id
				AND	sl.infotype_id = s.infotype_id
				AND	sl.station_strid = s.station_strid
			LEFT JOIN
					layer l
				ON	sl.field = l.field
			WHERE	s.status = 'current'
			ORDER BY
					s.descriptor, IF (l.description = '', l.field, l.description)
			-- l.description and l.field controls the order on Flowchart app chart screen
		";
		$rows = $this->connect('realtime-tool')->fetchAll($sql);
		
		$list = array();
		foreach ($rows as $row) {
			$station = ucwords($row['descriptor']);	// unify lowercase and uppercase
			if (!isset($list[$station])) {
				$list[$station] = array();
			}
			$layerid = $row['layerid']; // maybe empty
			if ($layerid) {
				$list[$station][] = array(
					'layerid' => $layerid,
					'begintime' => $row['begintime'],
					'endtime' => $row['endtime'],
					'last4days' => $row['last4days'],
					'records' => $row['records']
				);
			}
		}
		
		return $list;
	}
	public function saveLayerInfo($params) {
		//return $this->saveRecordByProperty($params, array('basin_id'))
	}
	
	function getLayerList() {
		$dbconn = $this->connect('realtime-tool');
		$sql = "
			SELECT	l.layerid
					, l.field
					, l.description
					, MAX(sl.endtime) endtime
					, MIN(sl.begintime) begintime
			FROM	layer l
			JOIN	station_layer sl
				ON	l.field = sl.field
			WHERE	TRUE
			GROUP BY
					l.layerid
			ORDER BY
					IF (l.description != '', l.description, l.field)
		";
		return $this->connect('realtime-tool')->fetchAll($sql);
	}
	public function getStationLayerList($station_strid) {
		$sql = "
			SELECT	s.station_strid
					, s.basin_id
					, s.infotype_id datatype_id
					, sl.field
					, sl.begintime begintime
					, sl.endtime endtime
					, l.layerid
					, l.description
			FROM	station s
			LEFT JOIN
					station_layer sl
				ON	s.station_strid = sl.station_strid
				AND	s.basin_id = sl.basin_id
				AND	s.infotype_id = sl.infotype_id
			LEFT JOIN
					layer l
				ON	sl.field = l.field
			WHERE	s.station_strid = '" . addslashes($station_strid) . "'
				AND	s.status = 'current'
			ORDER BY
					IF (l.description != '', l.description, l.field)
		";
		$rows = $this->connect()->fetchAll($sql);
		
		foreach ($rows as &$row) {
			$row['layerid'] = intval($row['layerid']);
			$row['basin_id'] = intval($row['basin_id']);
			$row['datatype_id'] = intval($row['datatype_id']);
		}
		
		return $rows;
	}
	public function getLayerBBoxList() {
		$tbname = $this->createPgRealtimeLayerList();
		
		$sql = "
			SELECT	lr.field
					, ST_Extent(sj.geom) AS bbox
					, ST_YMax(ST_Extent(sj.geom)) AS top
					, ST_XMax(ST_Extent(sj.geom)) AS right
					, ST_YMin(ST_Extent(sj.geom)) AS bottom
					, ST_XMin(ST_Extent(sj.geom)) AS left
			FROM	station_join5 sj
			JOIN	layer_realtime lr
				ON	LOWER(sj.stn_name) = LOWER(lr.station_descriptor)
			WHERE	TRUE
			GROUP BY
					lr.field
		";
		return $this->connect('geometry')->fetchAll($sql);
	}
	/**
	 * 从 MySQL 数据库的 awp 库中提取出 station list，插入 PostgreSQL 数据库的 station_realtime 表，
	 * 方便与 station_join2 中的 stn_name 进行对比，从而取得有实时数据的 station 的信息
	 */
	public function createPgRealtimeLayerList() {
		$tbname = 'layer_realtime';
		$sql = "SELECT true FROM pg_tables WHERE tablename = '$tbname'";
		$tb_existing = $this->connect('geometry')->fetchOne($sql);
		
		if ($tb_existing != 't') {
			$sql = "
				CREATE TABLE $tbname (
					gid integer NOT NULL,
					field character varying(50),
					station_descriptor character varying(200)
				);
				CREATE SEQUENCE {$tbname}_gid_seq
					START WITH 1
					INCREMENT BY 1
					NO MINVALUE
					NO MAXVALUE
					CACHE 1;
				ALTER SEQUENCE {$tbname}_gid_seq OWNED BY $tbname.gid;
				ALTER TABLE $tbname ALTER COLUMN gid SET DEFAULT nextval('{$tbname}_gid_seq'::regclass);
				CREATE INDEX field ON $tbname USING btree (field);
				CREATE INDEX layer_station ON $tbname USING btree (station_descriptor); ";
			$this->connect('geometry')->query($sql);
		}
		
		$sql = "
			SELECT	COUNT(*)
			FROM	$tbname";
		$total_in_pgsql = $this->connect('geometry')->fetchColumn($sql);
		
		$modelStation = ML::instance('RealtimeStation');
		$stationList = $modelStation->getStationListGroupByField();
		$total_in_mysql = count($stationList);
		
		if ($total_in_pgsql != $total_in_mysql) {
			$values = array();
			foreach ($stationList as $row) {
				$values[] = "($row[id],  '" . addslashes($row['field']) . "',  '" . addslashes($row['station_descriptor']) . "')";
			}
			if (!empty($values)) {
				$this->connect('geometry')->query("TRUNCATE TABLE $tbname");
				
				$sql = "
					INSERT INTO
							$tbname (
						gid, field, station_descriptor
					) VALUES ".implode("
					, ", $values);
				$this->connect('geometry')->query($sql);
			}
		}
		
		return $tbname;
	}
	
	function getGeometryStationListByLonLat($upper, $right, $bottom, $left) {
		$box = array($left, $bottom, $right, $upper);
		$geomBBox = "ST_GeomFromText('POLYGON(($box[0] $box[1], $box[0] $box[3],"
				." $box[2] $box[3], $box[2] $box[1], $box[0] $box[1]))', 4326)";
		
		$sql = "
			SELECT	stn_name
					, ST_X(geom) AS lon
					, ST_Y(geom) AS lat
					, lat_long
			FROM	station_join5
			WHERE	ST_Within(geom, $geomBBox)
		";
		$rows = $this->connect('geometry')->fetchAll($sql);
		//foreach ($rows as &$row) {
		//	$parts = preg_split('/ +/', $row['lat_long']);
		//	$row['lat'] = $parts[0] + $parts[1] / 60 + $parts[2] / 3600;
		//	$row['lon'] = $parts[3] + $parts[4] / 60 + $parts[5] / 3600;
		//}
		
		return $rows;
	}
	function getStationFieldsByStationDescription($station_descriptor) {
		$sql = "
			SELECT	sf.basin_id
					, sf.infotype_id
					, sf.station_strid
					, sf.field
					, sf.field_full
					, sf.descriptor
					, sf.unit
					, sf.demo
			FROM	station_field sf
			JOIN	station s
				ON	sf.basin_id = s.basin_id
				AND	sf.infotype_id = s.infotype_id
				AND	sf.station_strid = s.station_strid
			WHERE	s.descriptor = '" .  addslashes($station_descriptor) . "'
		";
		return $this->connect('realtime-tool')->fetchAll($sql);
	}
	function filterStationListByLayer($stationList, $field) {
		$sql = "
			CREATE TEMPORARY TABLE `tmp_layer_stations` (
			  `id` int(11) NOT NULL AUTO_INCREMENT,
			  `stn_name` varchar(255) NOT NULL,
			  `lat` double NOT NULL,
			  `lon` double NOT NULL,
			  PRIMARY KEY (`id`),
			  KEY `stn_name` (`stn_name`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8";
		$this->connect('realtime-tool')->query($sql);
		
		$values = array();
		foreach ($stationList as $row) {
			$values[] = "('" . addslashes($row['stn_name']) . "', $row[lat], $row[lon])";
		}
		if (!empty($values)) {
			$sql = "
				INSERT INTO	tmp_layer_stations (
					stn_name, lat, lon
				) VALUES ".implode("
				, ", $values);
			$this->connect('realtime-tool')->query($sql);
		}
		
		$sql = "
			SELECT	sf.basin_id
					, sf.infotype_id
					, sf.station_strid
					, sf.field
					, s.descriptor stn_name
					, ls.lat
					, ls.lon
			FROM	tmp_layer_stations ls
			-- LEFT JOIN	-- empty readings not be displayed
			JOIN
					station s
				ON	s.descriptor = ls.stn_name	-- MySQL is case insensitive
				AND	s.status = 'current'
			LEFT JOIN	station_field sf
				ON	sf.basin_id = s.basin_id
				AND	sf.infotype_id = s.infotype_id
				AND	sf.station_strid = s.station_strid
				AND	sf.field = '" . addslashes($field) . "'
			WHERE	TRUE
		";
		return $this->connect('realtime-tool')->fetchAll($sql);
	}
	function getDataSeries($basin_id, $infotype_id, $station_strid, $field, $begintime = '', $endtime = '') {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		$station_strid = preg_replace('/[^0-9a-zA-Z]/', '', $station_strid);
		
		$tbname = 'basin_'.$basin_id.'_datatype_'.$infotype_id.'_'.strtolower($station_strid);
		
		$sql = "SHOW TABLES LIKE '$tbname'";
		$tableList = $this->connect('realtime-data')->fetchAll($sql);
		
		$result = array();
		if (!empty($tableList) && $field) {
			global $cfg;
			
			$sql = "
				SELECT	COUNT(*)
				FROM	`".addslashes($tbname)."`
				WHERE	TRUE"
					.($begintime ? "
					AND	date_and_time >= '$begintime'" : "")
					.($endtime ? "
					AND	date_and_time <= '$endtime'" : "")."
					AND	!(`$field` > -1000 AND `$field` <= -999)
			";
			$total = $this->connect('realtime-data')->fetchColumn($sql);
			
			$sql = "
				SELECT	date_and_time time
						, `$field` value
				FROM	`".addslashes($tbname)."`
				WHERE	TRUE"
					. ($begintime ? "
					AND	date_and_time >= '$begintime'" : "")
					. ($endtime ? "
					AND	date_and_time <= '$endtime'" : "") . "
					AND	!(`$field` > -1000 AND `$field` <= -999)
				ORDER BY
						date_and_time DESC
			";
			$result = $this->connect('realtime-data')->fetchAll($sql);
		}
		
		return $result;
	}
	function getLayerDataList($basin_id, $infotype_id, $station_strid, $field, $begintime = '', $endtime = '') {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		$station_strid = preg_replace('/[^0-9a-zA-Z]/', '', $station_strid);
	
		$tbname = 'basin_'.$basin_id.'_datatype_'.$infotype_id.'_'.strtolower($station_strid);
	
		$sql = "SHOW TABLES LIKE '$tbname'";
		$tableList = $this->connect('realtime-data')->fetchAll($sql);
	
		$result = array();
		if (!empty($tableList) && $field) {
			global $cfg;
	
			$sql = "
				SELECT	COUNT(*)
				FROM	`".addslashes($tbname)."`
				WHERE	TRUE"
				.($begintime ? "
					AND	date_and_time >= '$begintime'" : "")
					.($endtime ? "
							AND	date_and_time <= '$endtime'" : "")."
							AND	!(`$field` > -1000 AND `$field` <= -999)
							";
			$total = $this->connect('realtime-data')->fetchColumn($sql);
	
			$sql = "
				SELECT	date_and_time time
						, `$field` value
						, text_id
				FROM	`".addslashes($tbname)."`
				WHERE	TRUE"
					. ($begintime ? "
					AND	date_and_time >= '$begintime'" : "")
					. ($endtime ? "
					AND	date_and_time <= '$endtime'" : "") . "
					AND	!(`$field` > -1000 AND `$field` <= -999)
				ORDER BY
						date_and_time DESC
			";
			$result = $this->connect('realtime-data')->fetchAll($sql);
		}
	
		return $result;
	}
}
