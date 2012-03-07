<?php
require_once 'Table.php';

class WERealtime_Model_Basin extends ML_Model_Table {
	protected static $_instance = null;
	
	public static function getInstance() {
		if (self::$_instance === null) {
			self::$_instance = new self ();
		}
		return self::$_instance;
	}
	
	protected $properties = array (
			'Id' => 'basin_id',
			'Description' => 'descriptor',
			'Version' => 'version',
			'Status' => '',
			'UpdateTime' => 'update_time',
	);
	public function getTable() {
		return 'basin';
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
	protected function updateStatus($objs) {
		foreach ($objs as $obj) {
			$sql = "
				UPDATE	`" . $this->getTable() . "`
					SET	`" . $this->getPropertyField('Status') . "` = '" . addslashes($obj->Status) . "'
				WHERE	`" . $this->getPropertyField('Id') . "` = '" . intval($obj->Id) . "'
					AND	`" . $this->getPropertyField('Version') . "` = '" . intval($obj->Version) . "'
			";
			$this->connect()->query($sql);
		}
	}
	function getBasinListByIds(array $basinIds) {
		foreach ($basinIds as &$id) {
			$id = intval($id);
		}
		$sql = "
			SELECT	b.basin_id id
					, b.descriptor description
					, b.update_time
					, b.version
			FROM	basin b
			WHERE	b.status = 'current'
				AND	b.basin_id IN (" . implode(", ", $basinIds) . ")
			ORDER BY
					b.basin_id
		";
		$rows = $this->connect()->fetchAll($sql);
		
		foreach ($rows as &$row) {
			$row['id'] = intval($row['id']);
			$row['version'] = intval($row['version']);
		}
		
		return $rows;
	}
	public function getBasinList($version = false) {
		if ($version === false) {
			$version = $this->getLatestVersion();
		}
		$version = intval($version);
		
		$options = array(
			'WHERE' => "`" . $this->getPropertyField('Version') . "` = $version",
			'ORDERBY' => $this->getPropertyField('Description'),
		);
		return $this->getRecordList($options);
	}
	public function getBasinListWithStatus($version = false) {
		if ($version === false) {
			$version = $this->getLatestVersion();
		}
		$version = intval($version);
		
		$basinList2 = $this->getBasinList($version);
		$basinList1 = $this->getBasinList($version - 1);
		
		$list1 = array();
		foreach ($basinList1 as $obj) {
			$obj->Status = 'deleted';
			$list1[$obj->Id] = $obj;
		}
		
		foreach ($basinList2 as $i => $obj) {
			if (isset($list1[$obj->Id])) {
				$obj->Status = $obj->Description == $list1[$obj->Id]->Description ? 'same' : 'changed';
				unset($list1[$obj->Id]);
			} else {
				$obj->Status = 'new';
			}
		}
	
		return array_merge($list1, $basinList2);
	}
	/**
	 * ingest and parse page html functions
	 */
	public function parseBasinList() {
		global $cfg;
		$page_html = $this->fetchHtml($cfg['urls']['basins']);
	
		$select_html = $this->matchSelectHtml($page_html);
		return $this->matchBasinList($select_html);
	}
	protected function fetchHtml($url) {
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
	protected function matchSelectHtml($html) {
		if (preg_match_all('/<select .*?<\/select>/is', $html, $m)) {
			$match = $m[0][0];
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
					'id' => intval($row),
					'name' => $m[2][$i]
			);
		}
		return $basins;
	}
	
	function saveBasinList($basins, $version) {
		$version = intval($version);
		
		foreach ($basins as $i => $basin) {
			$basin_id = intval($basin['id']);
			$descriptor = addslashes(trim($basin['name']));
			
			$sql = "
				INSERT INTO
						basin
				SET		basin_id = $basin_id,
						descriptor = '$descriptor',
						update_time = NOW(),
						version = $version,
						status = ''
			";
			$this->connect('realtime_tool')->query($sql);
		}
	}
	
	public function getVersionList() {
		$sql = "
			SELECT	`" . $this->getPropertyField('Version') . "`
					, COUNT(*) basins_number
					, `" . $this->getPropertyField('UpdateTime') . "`
			FROM	`" . $this->getTable() . "`
			GROUP BY
					`" . $this->getPropertyField('Version') . "`
		";
		return $this->connect()->fetchAll($sql);
	}
}