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
			'Id' => 'layerid',
			'Abbrev' =>	'field',
			'Description' => 'description',
			'OriginTextId' => 'text_id',
	);
	public function getTable() {
		return 'datatype';
	}
	public function getDatabase() {
		return 'realtime-tool';
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
	
	function getBasinInfotypeList() {
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
			WHERE	b.status = 'current'
				AND	i.status = 'current'
			ORDER BY
					i.basin_id, i.infotype_id
		";
		return $this->connect('realtime')->fetchAll($sql);
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
}