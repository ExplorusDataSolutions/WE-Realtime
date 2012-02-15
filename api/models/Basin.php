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
			'Id' => 'layerid',
			'Abbrev' =>	'field',
			'Description' => 'description',
			'OriginTextId' => 'text_id',
	);
	public function getTable() {
		return 'basin';
	}
	public function getDatabase() {
		return 'realtime-tool';
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
	function getList($version = 0) {
		$version = intval($version);
		
		$sql = "
			SELECT	b.basin_id
					, b.descriptor
					, b.update_time
					, b.version
					, b.status
					, COUNT(i.id) infotype_number
			FROM	basin b
			LEFT JOIN
					basin_infotype i
				ON	b.id = i.bid
			WHERE	" . ($version ? "
					b.version = $version" : "
					b.status = 'current'") . "
			GROUP BY
					b.basin_id
			ORDER BY
					b.basin_id
		";
		return $this->connect('realtime')->fetchAll($sql);
	}
	
	function getVersionList($brief = false) {
		$this->connect('realtime_tool')->query("DROP TABLE IF EXISTS `tmp_v_basin`");
		
		$sql = "
			CREATE TABLE `tmp_v_basin` (
				`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
				`v` INT NOT NULL ,
				INDEX ( `v` )
			) ENGINE = MYISAM
			SELECT	DISTINCT(id) AS v
			FROM	basins_html
			WHERE	TRUE
			ORDER BY
					id;
		";
		$this->connect('realtime_tool')->query($sql);
		
		$sql = "
			SELECT	bh.id AS bh_id
					, b2.basin_id AS basin_id
					, b2.descriptor AS descriptor
					, b2.version AS version
					, IF (b1.id IS NULL, '+', IF (b1.descriptor = b2.descriptor, '=', '*')) AS status
			--		, b1.basin_id	AS b1_basin_id" .	/*	-- 查看对比结果�?�?*/"
			--		, b1.version	AS b1_version" .	/*	-- 查看对比结果�?�?*/"
			FROM	basins_html AS bh
			LEFT JOIN
					basin AS b2
				ON	bh.id = b2.version
			LEFT JOIN
					tmp_v_basin AS v2
				ON	bh.id = v2.v		" . /*-- 对准版本*/"
				AND	b2.status != 'deleted'
			LEFT JOIN
					tmp_v_basin AS v1
				ON	v1.id = v2.id - 1	" . /*-- v2版本跟上�?个版本v1对准*/"
			LEFT JOIN
					basin AS b1
				ON	b1.version = v1.v
				AND	b1.basin_id = b2.basin_id
				AND	b1.status != 'deleted'
			WHERE"
				.($brief ? "
					b1.descriptor != b2.descriptor
				OR
					b1.id IS NULL" : "
				TRUE")."
			
			UNION
			
			SELECT	bh.id AS bh_id
					, b1.basin_id AS basin_id
					, IF (v2.id IS NULL, b1.descriptor, '') AS descriptor
					, IF (v2.id IS NULL, b1.version, v2.v) AS version
					, IF (v2.id IS NULL, '$', '-') AS status
			--		, b2.basin_id	AS b2_basin_id" .	/*	-- 查看对比结果�?�?*/"
			--		, b2.version	AS b2_version" .	/*	-- 查看对比结果�?�?*/"
			FROM	basins_html AS bh
			JOIN	basin AS b1
				ON	bh.id = b1.version
			LEFT JOIN
					tmp_v_basin AS v1
				ON	b1.version = v1.v
				AND	b1.status != 'deleted'
			LEFT JOIN
					tmp_v_basin AS v2
				ON	v2.id = v1.id + 1" . /*	-- v1（上�?个版本）跟v2（下�?下版本）对准进行比较*/"
			LEFT JOIN
					basin AS b2
				ON	b2.version = v2.v
				AND	b1.basin_id = b2.basin_id
				AND	b2.status != 'deleted'
			WHERE
					b2.id IS NULL
		";
		$rows = $this->connect('realtime_tool')->fetchAll($sql);
		
		// 组织成版本数�?
		$basins = array();
		foreach ($rows as $i => $row) {
			$basin_id = $row['basin_id'];
			$version = $row['version'];
			
			if (!isset($basins[$version])) {
				$basins[$version] = array();
			}
			if (!isset($basins[$version][$basin_id])) {
				$basins[$version][$basin_id] = $row;
			}
		}
		
		return $basins;
	}
	
	function getHtmlInfo($version) {
		$version = intval($version);
		
		$sql = "
			SELECT	*,
					LENGTH(html) AS size
			FROM	basins_html
			WHERE	id = $version
			ORDER BY
					id DESC
		";
		return $this->connect('realtime_tool')->fetch($sql);
	}
	
	function getHtmlInfoList() {
		$sql = "
			SELECT	id,
					LENGTH(html) AS size,
					update_time,
					status
			FROM	basins_html
			WHERE	TRUE
			ORDER BY
					id DESC
		";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	
	function getHtml() {
		global $cfg;
		return $this->fetchHtml($cfg['urls']['basins']);
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
	
	function savePageHtml($page_html, $select_html) {
		$sql = "
			INSERT INTO
					basins_html
			SET		`html` = '".addslashes($page_html)."',
					`match` = '".addslashes($select_html)."',
					`update_time` = NOW(),
					`version` = 0,
					`status` = ''
		";
		$this->connect('realtime_tool')->query($sql);
		return $this->connect('realtime_tool')->insertid($sql);
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
	
	function makeVersionCurrent($version) {
		$version = intval($version);
		
		$sql = "
			UPDATE	basin
			SET		status = ''
			WHERE	status = 'current'
		";
		$this->connect('realtime_tool')->query($sql);
		
		$sql = "
			UPDATE	basin
			SET		status = 'current'
			WHERE	version = $version
		";
		$this->connect('realtime_tool')->query($sql);
		
		$sql = "
			UPDATE	basins_html
			SET		status = ''
			WHERE	status = 'current'
		";
		$this->connect('realtime_tool')->query($sql);
		
		$sql = "
			UPDATE	basins_html
			SET		status = 'current'
			WHERE	id = $version
		";
		$this->connect('realtime_tool')->query($sql);
	}
}