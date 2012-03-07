<?php
require_once 'Table.php';

class WERealtime_Model_Datatype extends ML_Model_Table {
	protected static $_instance = null;
	
	public static function getInstance() {
		if (self::$_instance === null) {
			self::$_instance = new self ();
		}
		return self::$_instance;
	}
	
	protected $properties = array (
			'Id' => 'datatype_id',
			'Description' => 'description',
			'BasinId' => 'basin_id',
			'Version' => 'version',
	);
	public function getTable() {
		return 'datatype';
	}
	public function getDatabase() {
		return 'realtime-tool';
	}

	
	protected function getLatestVersion() {
		$sql = "
			SELECT	MAX(version)
			FROM	`" . $this->getTable() . "`
		";
		return $this->connect()->fetchOne($sql);
	}
	function getDatatypeListByIds(array $dataTypeIds) {
		foreach ($dataTypeIds as &$id) {
			$id = intval($id[0]) . ',' . intval($id[1]);
		}
		$sql = "
			SELECT	d.datatype_id
					, d.description
					, d.version
			FROM	`" . $this->getTable() . "` d
			WHERE	d.status = 'current'
				AND	CONCAT(d.basin_id, ',', d.datatype_id) IN ('" . implode("', '", $dataTypeIds) . "')
			GROUP BY
					d.datatype_id
			ORDER BY
					d.datatype_id
		";
		return $this->connect()->fetchAll($sql);
	}
	
	public function getDatatypeList($version = null) {
		if ($version == null) {
			$version = $this->getLatestVersion();
		}
		$version = intval($version);
		
		$options = array(
			'WHERE' => "`" . $this->getPropertyField('Version') . "` = $version",
			'ORDERBY' => 'description',
		);
		$objs = $this->getRecordList($options);
		
		$list = array();
		foreach ($objs as $obj) {
			$Id = intval($obj->Id);
			$BasinId = intval($obj->BasinId);
			
			if (!isset($list[$Id])) {
				$o = new stdClass();
				$o->Id = $Id;
				$o->Description = $obj->Description;
				$o->Basins = array();
				$list[$Id] = $o;
			}
			$list[$Id]->Basins[$BasinId] = $BasinId;
		}
		
		return array_values($list);
	}
	public function getDatatypeListWithStatus($version = null) {
		if ($version == null) {
			$version = $this->getLatestVersion();
		}
		$version = intval($version);
		
		$list1 = $this->getDatatypeList($version - 1);
		$list2 = $this->getDatatypeList($version);
		
		$list = array();
		foreach ($list1 as $obj) {
			$obj->Status = 'deleted';
			$list[$obj->Id] = $obj;
		}
		
		foreach ($list2 as $i => $obj) {
			if (isset($list[$obj->Id])) {
				$obj->Status = $obj->Description == $list[$obj->Id]->Description ? 'same' : 'changed';
				unset($list[$obj->Id]);
			} else {
				$obj->Status = 'new';
			}
		}
		
		return array_merge($list, $list2);
	}
	
	function getInfotypeListByBasin($basin_id) {
		$basin_id = intval($basin_id);
		
		$sql = "
			SELECT	b.basin_id
					, b.descriptor basin_descriptor
					, b.version basin_version
					, i.version infotype_version
					, i.infotype_id
					, i.descriptor AS infotype_descriptor
			FROM	basin_infotype i
			JOIN	basin b
				ON	i.bid = b.id
				AND	b.status = 'current'
			WHERE	i.status = 'current'
				AND	b.basin_id = $basin_id
			ORDER BY
					i.infotype_id
		";
		return $this->connect('realtime')->fetchAll($sql);
	}
	
	function getVersionList() {
		$sql = "
			SELECT	version
					, basin_version
					, COUNT(DISTINCT(CONCAT(basin_id, infotype_id))) basin_infotype_num
					, COUNT(DISTINCT(infotype_id)) infotype_num
					, COUNT(DISTINCT(basin_id)) basin_num
			FROM	basin_infotype b
			WHERE	TRUE
			GROUP BY
					version
		";
		return $this->connect('realtime')->fetchAll($sql);
	}
	
	function getVersionDetailsList($brief = false) {
		$this->connect('realtime_tool')->query("DROP TABLE IF EXISTS `tmp_v_basin_infotype`");
		$sql = "
			CREATE TABLE `tmp_v_basin_infotype` (
				`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
				`basin_id` INT NOT NULL ,
				`v` INT NOT NULL DEFAULT 0, -- default 0 is needed to void error: Field *** doesn't have a default value
				INDEX ( `basin_id` ),
				INDEX ( `v` )
			) ENGINE = MYISAM
			SELECT	i.basin_id
					, i.version AS v
			FROM	basin_infotype AS i
			WHERE ".($brief ? "
					i.status != 'deleted'" : "
					TRUE")."
			GROUP BY
					i.basin_id, i.version
			ORDER BY
					i.basin_id, i.version;
		";
		$this->connect('realtime_tool')->query($sql);
		
		$sql = "
			SELECT	b.basin_id,
					b.descriptor AS basin_descriptor,
					b.version AS basin_version,
					i2.infotype_id AS infotype_id,
					i2.descriptor AS infotype_descriptor,
					UNIX_TIMESTAMP(i2.update_time) AS infotype_update_time,
					i2.version AS infotype_version,
					IF (i1.id IS NULL, '+', IF (i1.descriptor = i2.descriptor, '=', '*')) AS status
					, i2.status AS current
			FROM	basin AS b
			JOIN	basin_infotype AS i2
				ON	i2.bid = b.id
			JOIN	tmp_v_basin_infotype AS v2
				ON	i2.basin_id = v2.basin_id
				AND	i2.version = v2.v
			LEFT JOIN
					tmp_v_basin_infotype AS v1
				ON	v1.id = v2.id - 1
				AND	v1.basin_id = v2.basin_id
			LEFT JOIN
					basin_infotype AS i1
				ON	i1.basin_id = i2.basin_id
				AND	i1.infotype_id = i2.infotype_id	
				AND	i1.version = v1.v
			WHERE" . ($brief ? "
					i1.descriptor != i2.descriptor
				OR	i1.id IS NULL" : "
					TRUE") . "
			
			UNION
			
			SELECT	b.basin_id,
					b.descriptor AS basin_descriptor,
					b.version AS basin_version,
					i1.infotype_id AS infotype_id,
					IF (v2.id IS NULL, i1.descriptor, '') AS infotype_descriptor,
					UNIX_TIMESTAMP(i1.update_time) AS infotype_update_time,
					IF (v2.id IS NULL, v1.v, v2.v) AS infotype_version,
					IF (v2.id IS NULL, '=', '-') AS status
					, i1.status AS current
			FROM	basin AS b
			JOIN	basin_infotype AS i1
				ON	i1.basin_id = b.basin_id
				AND	i1.basin_version = b.version
			JOIN	tmp_v_basin_infotype AS v1
				ON	i1.basin_id = v1.basin_id
				AND	i1.version = v1.v
			LEFT JOIN
					tmp_v_basin_infotype AS v2
				ON	v1.id = v2.id - 1
				AND	v1.basin_id = v2.basin_id
			LEFT JOIN
					basin_infotype AS i2
				ON	i1.basin_id = i2.basin_id
				AND	i1.infotype_id = i2.infotype_id	
				AND	i2.version = v2.v
			WHERE	i2.id IS NULL";
		$rows = $this->connect('realtime_tool')->fetchAll($sql);
		
		/**
		 * 不同的 basin 是可以有不同数量 infotype 的版本历史的。
		 */
		$list = array();
		foreach ($rows as $i => $row) {
			$bid = $row['basin_id'];
			$iid = $row['infotype_id'];
			$iv = $row['infotype_version'];
			if (!isset($list[$bid][$iv][$iid])) {
				$list[$bid][$iv][$iid] = $row;
			}
		}
		ksort($list);
		
		return $list;
	}
	
	function makeSureLatestVersionCurrent() {
		$sql = "
			UPDATE	basin_infotype i,
			(
				SELECT	basin_id
						, MAX(version) AS current_version
				FROM	basin_infotype
				WHERE	TRUE
				GROUP BY
						basin_id
			) v
			SET		i.status = IF(i.version = v.current_version, 'current', '')
			WHERE	i.basin_id = v.basin_id
				AND (	i.status = 'current'
					OR	i.version = v.current_version
				)
		";
		$this->connect('realtime_tool')->query($sql);
	}
	
	
	/**
	 * ingest and parse page html functions
	 */
	public function parseDatatypeList() {
		global $cfg;
		$page_html = $this->fetchHtml($cfg['urls']['basins']);
		
		$__VIEWSTATE = '';
		if (preg_match('/="__VIEWSTATE" value="(.+)"/', $page_html, $m)) {
			$__VIEWSTATE = $m[1];
		}
		$__EVENTVALIDATION = '';
		if (preg_match('/="__EVENTVALIDATION" value="(.+)"/', $page_html, $m)) {
			$__EVENTVALIDATION = $m[1];
		}
		
		$basin_select_html = $this->matchBasinSelectHtml($page_html);
		$basinList = $this->matchBasinList($basin_select_html);
		
		$datatype_select_html = $this->matchDatatypeSelectHtml($page_html);
		$datatypeList = $this->matchDatatypeList($datatype_select_html);
		
		foreach ($basinList as &$row) {
			$basin_id = $row['id'];
			$post_data = "__VIEWSTATE=" . urlencode($__VIEWSTATE)
				. "&__EVENTVALIDATION=" . urlencode($__EVENTVALIDATION)
				. "&ctl00\$ctl00\$cphContentSection\$MainContentArea\$BasinList=$basin_id";
			$page_html = $this->fetchHtml($cfg['urls']['basins'], $post_data);
			$select_html = $this->matchDatatypeSelectHtml($page_html);
			$row['datatypeList'] = $this->matchDatatypeList($select_html);
		}
		
		return $basinList;
	}
	protected function fetchHtml($url, $post_data = '') {
		$info = array($url);
	
		$ch = curl_init($url);
		$info[] = $ch;
	
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if ($post_data) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
		}
	
		$html = curl_exec($ch);
		curl_close($ch);
		$info[] = $html;
	
		if ($html) {
			return $html;
		} else {
			return false;
		}
	}
	protected function matchBasinSelectHtml($html) {
		$id = 'ctl00_ctl00_cphContentSection_MainContentArea_BasinList';
		$re = '/id="' . $id . '">.*?<\/select>/is';
		if (preg_match($re, $html, $m)) {
			$match = $m[0];
			return $match;
		} else {
			return false;
		}
	}
	protected function matchBasinList($match) {
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
	protected function matchDatatypeSelectHtml($html) {
		$name = 'ctl00\\$ctl00\\$cphContentSection\\$MainContentArea\\$DataTypeList';
		$id = 'ctl00_ctl00_cphContentSection_MainContentArea_DataTypeList';
		$re = '/<select name="' . $name . '" id="' . $id . '">.*?<\/select>/is';
		if (preg_match($re, $html, $m)) {
			return $m[0];
		} else {
			return false;
		}
	}
	protected function matchDatatypeList($match) {
		preg_match_all('/<option .*?value="(.*?)".*?>(.*?)<\/option>/is', $match, $m);
	
		$datatypes = array();
		foreach ($m[1] as $i => $row) {
			$datatypes[] = array(
					'id' => $row,
					'name' => $m[2][$i]
			);
		}
		return $datatypes;
	}
}