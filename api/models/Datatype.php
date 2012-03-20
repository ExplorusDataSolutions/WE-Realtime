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
			'UpdateTime' =>'update_time',
	);
	public function getTable() {
		return 'datatype';
	}
	public function getDatabase() {
		return 'realtime-tool';
	}
	
		
	public function getVersionList() {
		$sql = "
			SELECT	`" . $this->getPropertyField('Version') . "`
					, COUNT(DISTINCT(datatype_id)) datatype_total
					, `" . $this->getPropertyField('UpdateTime') . "`
			FROM	`" . $this->getTable() . "`
			GROUP BY
				`" . $this->getPropertyField('Version') . "`
		";
		return $this->connect()->fetchAll($sql);
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
		if ($version === null) {
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
				$o->Version = $obj->Version;
				$o->UpdateTime = $obj->UpdateTime;
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
		
		$objMap = array();
		foreach ($list1 as $obj) {
			$obj->Status = 'deleted';
			$objMap[$obj->Id] = $obj;
		}
		
		foreach ($list2 as $i => $obj) {
			$Id = $obj->Id;
			if (isset($objMap[$Id])) {
				$o = $objMap[$Id];
				if ($obj->Description == $o->Description && !array_diff($obj->Basins, $o->Basins)) {
					$obj->Status = 'same';
				} else {
					$obj->Status = 'changed';
					if ($obj->Description != $o->Description) {
						$obj->oldDescription = $o->Description;
					}
					if (array_diff($obj->Basins, $o->Basins)) {
						$obj->oldBasins = $o->Basins;
					}
				}
				
				unset($objMap[$Id]);
			} else {
				$obj->Status = 'new';
			}
		}
		
		return array_merge($objMap, $list2);
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
	
	public function getLatestVersion() {
		$sql = "
			SELECT	MAX(`" . $this->getPropertyField('Version') . "`)
			FROM	`" . $this->getTable() . "`
			WHERE	TRUE
		";
		return $this->connect()->fetchOne($sql);
	}
	
	public function saveDatatype($dataType) {
		foreach ($dataType->Basins as $BasinId) {
			$params = array(
					'Id' => $dataType->Id,
					'Description' => $dataType->Description,
					'BasinId' => $BasinId,
					'Version' => $dataType->Version,
					'UpdateTime' => @date('Y-m-d H:i:s'),
					);
			if (!$this->saveRecordByProperty($params, array('Id', 'BasinId', 'Version'))) {
				return false;
			} 
		}
		return true;
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