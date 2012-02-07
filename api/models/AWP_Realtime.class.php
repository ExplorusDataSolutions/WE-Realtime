<?php
require_once dirname(__FILE__).DS.'mlModel.php';

class AWP_Realtime extends mlModel {
	var $html_contents = array();
	
	function get_basin_infotypes_versions_x() {
		$sql = "
			SELECT
				b.basin_id,
				b.descriptor AS basin_descriptor,
				b.version AS basin_version,
				i.infotype_id,
				i.descriptor AS infotype_descriptor,
				i.version AS infotype_version
			FROM
				basin_infotype AS i
			RIGHT JOIN
				basin AS b
			ON
					b.status != 'deleted'
				AND
					b.basin_id = i.basin_id
				AND
					b.version = i.basin_version
			";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_basin_infotypes_versions($brief = true) {
		$this->connect('realtime_tool')->query("DROP TABLE IF EXISTS `tmp_v_basin_infotype`");
		$sql = "
CREATE TABLE `tmp_v_basin_infotype` (
	`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	`basin_id` INT NOT NULL ,
	`v` INT NOT NULL DEFAULT 0, -- default 0 is needed to void error: Field *** doesn't have a default value
	INDEX ( `basin_id` ),
	INDEX ( `v` )
) ENGINE = MYISAM
SELECT
	i.basin_id,
	i.version AS v
FROM
	basin_infotype AS i
WHERE"
.($brief ? "
		i.basin_id IS NOT NULL
	AND
		i.status != 'deleted'"
: "
	TRUE")."
GROUP BY
	i.basin_id, i.version
ORDER BY
	i.basin_id, i.version;
";
		$this->connect('realtime_tool')->query($sql);
		//pre(microtime(true) - $t0);
		
		$sql = "
SELECT
	i2.id AS id2,
	i2.basin_id AS basin2,
	b.descriptor AS basin_descriptor,
	i2.basin_version AS basin_version,
	i2.infotype_id AS infotype2,
	i2.descriptor AS descriptor2,
	i2.version AS v2,
	i2.status AS status2,
	v1.v,
	i1.id AS id1,
	i1.basin_id AS basin1,
	i1.infotype_id AS infotype1,
	i1.descriptor AS descriptor1,
	i1.version AS v1,
	i1.status AS status1,
	IF (i1.id IS NULL, '+', IF (i1.descriptor = i2.descriptor, '=', '*')) AS status
FROM
	basin_infotype AS i2
JOIN
	basin AS b
ON
		i2.basin_id = b.basin_id
	AND
		i2.basin_version = b.version
	AND
		b.status != 'deleted'
JOIN
	tmp_v_basin_infotype AS v2
ON
		i2.basin_id = v2.basin_id
	AND
		i2.version = v2.v
	AND
		i2.basin_id IS NOT NULL
	AND
		i2.status != 'deleted'
LEFT JOIN
	tmp_v_basin_infotype AS v1
ON
		v1.id = v2.id - 1
	AND
		v1.basin_id = v2.basin_id
LEFT JOIN
	basin_infotype AS i1
ON
		i1.basin_id = i2.basin_id
	AND
		i1.infotype_id = i2.infotype_id	
	AND
		i1.version = v1.v
	AND
		i1.basin_id IS NOT NULL
	AND
		i1.status != 'deleted'
WHERE"
	.($brief ? "
		i1.descriptor != i2.descriptor
	OR
		i1.id IS NULL
	" : "
	TRUE")."
			
UNION
			
SELECT
	i2.id AS id2,
	i2.basin_id AS basin2,
	b.descriptor AS basin_descriptor,
	i1.basin_version AS basin_version,
	i2.infotype_id AS infotype2,
	i2.descriptor AS descriptor2,
	i2.version AS v2,
	i2.status AS status2,
	v2.v,
	i1.id AS id1,
	i1.basin_id AS basin1,
	i1.infotype_id AS infotype1,
	i1.descriptor AS descriptor1,
	i1.version AS v1,
	i1.status AS status1,
	'-' AS status
FROM
	basin_infotype AS i1
JOIN
	basin AS b


ON
		i1.basin_id = b.basin_id
	AND
		i1.basin_version = b.version
	AND
		b.status != 'deleted'
JOIN
	tmp_v_basin_infotype AS v1
ON
		i1.basin_id = v1.basin_id
	AND
		i1.version = v1.v
	AND
		i1.status != 'deleted'
LEFT JOIN
	tmp_v_basin_infotype AS v2
ON
		v1.id = v2.id - 1
	AND
		v1.basin_id = v2.basin_id
LEFT JOIN
	basin_infotype AS i2
ON
		i1.basin_id = i2.basin_id
	AND
		i1.infotype_id = i2.infotype_id	
	AND
		i2.version = v2.v
	AND
		i2.status != 'deleted'
WHERE
	i2.id IS NULL";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_basin_infotypes_versions2() {
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
			WHERE	i.status != 'deleted'
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
			FROM
				basin AS b
			LEFT JOIN
				basin_infotype AS i2
			ON
					b.basin_id = i2.basin_id
				AND
					b.version = i2.basin_version
			LEFT JOIN
				tmp_v_basin_infotype AS v2
			ON
					i2.basin_id = v2.basin_id
				AND
					i2.version = v2.v
			LEFT JOIN
				tmp_v_basin_infotype AS v1
			ON
					v1.id = v2.id - 1
				AND
					v1.basin_id = v2.basin_id
			LEFT JOIN
				basin_infotype AS i1
			ON
					i1.basin_id = i2.basin_id
				AND
					i1.infotype_id = i2.infotype_id	
				AND
					i1.version = v1.v
			WHERE
					b.status != 'deleted'
				AND (
						i1.id IS NULL
					OR
						i1.status != 'deleted'
				)
				AND (
						i2.id IS NULL
					OR
						i2.status != 'deleted'
				)
			
			UNION
			
			SELECT
				b.basin_id,
				b.descriptor AS basin_descriptor,
				b.version AS basin_version,
				i2.id AS id_2,
				i1.infotype_id AS infotype_id,
				i1.descriptor AS infotype_descriptor,
				UNIX_TIMESTAMP(i1.update_time) AS infotype_update_time,
				IF (v2.id IS NULL, v1.v, v2.v) AS infotype_version,
				IF (v2.id IS NULL, '=', '-') AS status
			FROM
				basin AS b
			LEFT JOIN
				basin_infotype AS i1
			ON
					b.basin_id = i1.basin_id
				AND
					b.version = i1.basin_version
			LEFT JOIN
				tmp_v_basin_infotype AS v1
			ON
					i1.basin_id = v1.basin_id
				AND
					i1.version = v1.v
			LEFT JOIN
				tmp_v_basin_infotype AS v2
			ON
					v1.id = v2.id - 1
				AND
					v1.basin_id = v2.basin_id
			LEFT JOIN
				basin_infotype AS i2
			ON
					i1.basin_id = i2.basin_id
				AND
					i1.infotype_id = i2.infotype_id	
				AND
					i2.version = v2.v
			WHERE
					b.status != 'deleted'
				AND
					i1.status != 'deleted'
				AND
					i2.id IS NULL";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_basin_infotypes_latest_version() {
		$sql = "
			SELECT
				MAX(version)
			FROM
				basin_infotype
			WHERE
				1";
		return intval($this->connect('realtime_tool')->fetchColumn($sql));
	}
	function get_basin_infotypes_latest_version2($basin_id) {
		$basin_id = intval($basin_id);
		$sql = "
			SELECT
				MAX(IF (basin_id = $basin_id, version + 1,version))
			FROM
				basin_infotype
			WHERE
				1";
		return max(1, intval($this->connect('realtime_tool')->fetchColumn($sql)));
	}
	function get_basin_infotypes_by_latest2() {
		"SELECT
			bh.id AS basin_version
			, b.basin_id
			, i.version AS infotype_version
			, i.infotype_id
			, h.version AS station_version
			, s.station_strid
			, s.descriptor AS station_descriptor
			, t_max.version AS text_version
		FROM	basins_html			AS bh
		JOIN	basin				AS b
			ON	bh.id = b.version
			AND bh.id = (SELECT MAX(id) FROM basins_html) -- This can update SQL's performance, below is the same
		JOIN	basin_infotype		AS i
			ON	b.basin_id = i.basin_id
			AND	b.version = i.basin_version
		--	AND i.version = (SELECT MAX(version) FROM basin_infotype WHERE b.version = basin_version)
		JOIN	stations_html AS h
			ON	b.basin_id = h.basin_id
			AND	i.infotype_id = h.infotype_id
			AND	i.version = h.infotype_version
			AND h.version = (SELECT MAX(version) FROM stations_html)
		JOIN	station AS s
			ON	b.basin_id = s.basin_id	
			AND	i.infotype_id = s.infotype_id
			AND	h.version = s.html_version";
		//$t0 = microtime(true);
		$sql = "
			SELECT
				i.version AS infotype_version
				, i.infotype_id
				, i.descriptor AS infotype_descriptor
				, sh.version AS html_version
				, i.basin_id
				, i.basin_version
			FROM	basin_infotype AS i
			JOIN (
					SELECT	MAX(version) AS version
					FROM	basin_infotype
				) AS i_v
				ON	i.version = i_v.version
			
			LEFT JOIN (
					SELECT
						basin_id
						, infotype_id
						, infotype_version
						, version
					FROM	stations_html
					WHERE	version = (SELECT MAX(version) FROM stations_html)
				) AS sh
				ON	i.basin_id = sh.basin_id
				AND	i.infotype_id = sh.infotype_id
				AND	i.version = sh.infotype_version
			ORDER BY
				i.basin_id, i.infotype_id
		";
		//$rows = $this->connect('realtime_tool')->fetchAll($sql);
		//pre(microtime(true) - $t0);
		//pre($rows,1);
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_basin_infotypes_by_latest() {
		$sql = "
			SELECT
				b.basin_id,
				b.version AS basin_version,
				b.descriptor AS basin_descriptor,
				i.infotype_id,
				i.descriptor AS infotype_descriptor,
				i.version AS infotype_version,
				sh_v.version AS current_version,
				sh_v2.version AS latest_version
			FROM
				basin AS b
			JOIN
				basin_infotype AS i
			ON
					i.basin_id = b.basin_id
				AND
					i.basin_version = b.version
			JOIN (
				SELECT
					MAX(version) AS version
				FROM
					basin_infotype
				WHERE
					TRUE
			) AS v
			ON
				i.version = v.version
			LEFT JOIN
				stations_html AS sh
			ON
					b.basin_id = sh.basin_id
				AND
					i.infotype_id = sh.infotype_id
				AND
					v.version = sh.infotype_version
			LEFT JOIN (	-- 当一开始的时候，需要这个LEFT
				SELECT
					basin_id,
					infotype_id,
					MAX(version) AS version
				FROM
					stations_html
				WHERE
					TRUE
				GROUP BY
					basin_id, infotype_id
			) AS sh_v
			ON
					b.basin_id = sh_v.basin_id
				AND
					i.infotype_id = sh_v.infotype_id
				AND
					sh.version = sh_v.version
			LEFT JOIN (
				SELECT
					MAX(version) AS version
				FROM
					stations_html
				WHERE
					TRUE
			) AS sh_v2
			ON
				sh.version = sh_v.version
			WHERE
				TRUE
			ORDER BY
				i.basin_id, i.infotype_id
		";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_basin_infotypes_by_version($version = 0) {
		$version = intval($version);
		
		$sql = "
			SELECT
				*
			FROM
				basin_infotype
			WHERE
				version = $version
			GROUP BY
				basin_id, infotype_id
			ORDER BY
				basin_id, infotype_id
		";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_basin_info_types($cache = true, &$match) {
		global $cfg;
		if ($cache && $types = AWP_RealTime::get_cache('basin_info_types')) {
			return $types;
		} else {
			$html = AWP_RealTime::get_html($cfg['urls']['basin_info_types'], $cache);
			return $this->match_basin_info_types($html, $match);
		}
	}
	function match_basin_infotypes($html, &$match) {
		if (preg_match_all('/<select .*?<\/select>/is', $html, $m)) {
			$match = $m[0][1];
			preg_match_all('/<option .*?value="(.*?)".*?>(.*?)<\/option>/is', $m[0][1], $m);
			
			$types = array();
			foreach ($m[1] as $i => $row) {
				$types[] = array(
					'id' => $row,
					'name' => $m[2][$i]
				);
			}
			return $types;
		} else {
			return false;
		}
	}
	function save_basin_infotypes($infotypes) {
		$now = date('Y-m-d H:i:s');
		$latest_version = $this->get_basin_infotypes_latest_version();
		$current_version = intval($latest_version) + 1;
		
		foreach ($infotypes as $i => $infotype) {
			$infotype_id = intval($infotype['id']);
			$descriptor = addslashes($infotype['name']);
			
			$sql = "
				INSERT INTO
					basin_infotype
				SET
					infotype_id = $infotype_id,
					descriptor = '$descriptor',
					update_time = '$now',
					version = $current_version,
					status = ''
			";
			$this->connect('realtime_tool')->query($sql);
		}
		
		return $current_version;
	}
	function save_basin_infotypes2($infotypes, $basin_id, $basin_version) {
		$basin_id = intval($basin_id);
		$basin_version = intval($basin_version);
		
		$version = $this->get_basin_infotypes_latest_version2($basin_id);
		
		foreach ($infotypes as $infotype) {
			$infotype_id = intval($infotype['id']);
//			$sql = "
//				DELETE FROM
//					basin_infotype
//				WHERE
//						infotype_id = $infotype_id
//					AND
//						basin_id = $basin_id
//					AND
//						version = $current_version";
//			$this->connect('realtime_tool')->query($sql);
			$sql = "
				SELECT
					id
				FROM
					basin
				WHERE
						basin_id = $basin_id
					AND
						version = $basin_version
				LIMIT 1
			";
			$bid = $this->connect('realtime_tool')->fetchColumn($sql);
			
			$sql = "
				INSERT INTO
					basin_infotype
				SET
					infotype_id = $infotype_id,
					descriptor = '".addslashes($infotype['name'])."',
					bid = $bid,
					basin_id = $basin_id,
					basin_version = $basin_version,
					update_time = NOW(),
					version = $version,
					status = 'added'";pre($sql);
			$this->connect('realtime_tool')->query($sql);
		}
		return $version;
	}
	
	function get_basins_latest_version() {
		$sql = "
			SELECT	MAX(version)
			FROM	basin
			WHERE	1";
		return intval($this->connect('realtime_tool')->fetchColumn($sql));
	}
	function get_basins_by_version($version = 0) {
		$version = intval($version);
		
		if ($version == 0) $version = $this->get_basins_latest_version();
		
		$sql = "
			SELECT
				*
			FROM
				basin
			WHERE
				version = $version
			ORDER BY
				basin_id
		";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_basins_by_latest() {
		$sql = "
			SELECT
				*
			FROM
				basin AS b
			JOIN
				(SELECT
			--		b.basin_id,
					MAX(b.version) AS version
				FROM
					basin AS b
				LEFT JOIN
					basins_html AS h
				ON
					b.version = h.version
				WHERE
						b.status != 'deleted'
					AND (
							h.id IS NULL
						OR
							h.status != 'deleted'
					)
			--	GROUP BY
			--		basin_id
			) AS t
			ON
			--		b.basin_id = t.basin_id
			--	AND
					b.version = t.version
			WHERE
				b.status != 'deleted'
			ORDER BY
				b.descriptor
		";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function update_basins_status($current_version) {
		$last_basins = $this->get_basins_by_version($current_version);
		$current_basins = $this->get_basins_by_version($current_version - 1);
		
		$basin1 = array();
		foreach ($last_basins as $row) $basin1[$row['basin_id']] = $row;
		$basin2 = array();
		foreach ($current_basins as $row) $basin2[$row['basin_id']] = $row;
		
		foreach ($basin1 as $row) {
			if (!isset($basin2[$row['basin_id']])) {
				$this->connect('realtime_tool')->query("UPDATE basin SET status = '-' WHERE id = $row[id]");
			}
		}
		
		foreach ($basin2 as $row) {
			$basin_id = $row['basin_id'];
			if (!isset($basin1[$basin_id])) {
				$this->connect('realtime_tool')->query("UPDATE basin SET status = '+' WHERE id = $row[id]");
			} elseif ($basin1[$basin_id]['descriptor'] != $basin2[$basin_id]['descriptor']) {
				$this->connect('realtime_tool')->query("UPDATE basin SET status = '*' WHERE id = $row[id]");
			}
		}
	}
	function delete_basins($version) {
		$version = intval($version);
		
		$sql = "
			SELECT
				id
			FROM
				basins_html
			WHERE
				id = $version
			LIMIT
				1
		";
		$id = $this->connect('realtime_tool')->fetchColumn($sql);
		
		if ($id) {
			$sql = "
				UPDATE
					basins_html
				SET
					status = 'deleted'
				WHERE
					id = $version
			";
			$this->connect('realtime_tool')->query($sql);
			
			$sql = "
				UPDATE
					basin
				SET
					status = 'deleted'
				WHERE
					version = $version
			";
			$this->connect('realtime_tool')->query($sql);
		} else {
			$sql = "
				DELETE FROM
					basin
				WHERE
					version = $version
			";
			$this->connect('realtime_tool')->query($sql);
		}
	}
	function restore_basins($version) {
		$version = intval($version);
		
		$sql = "
			SELECT
				id
			FROM
				basins_html
			WHERE
				id = $version
			LIMIT
				1
		";
		$id = $this->connect('realtime_tool')->fetchColumn($sql);
		
		if ($id) {
			$sql = "
				UPDATE
					basins_html
				SET
					status = 'restore'
				WHERE
					id = $version
			";
			$this->connect('realtime_tool')->query($sql);
			
			$sql = "
				UPDATE
					basin
				SET
					status = 'restore'
				WHERE
					version = $version
			";
			$this->connect('realtime_tool')->query($sql);
		} else {
			$sql = "
				DELETE FROM
					basin
				WHERE
					version = $version
			";
			$this->connect('realtime_tool')->query($sql);
		}
	}
	
	function get_stations_versions($basin_id, $infotype_id, $brief = true) {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		
		$this->connect('realtime_tool')->query("DROP TABLE IF EXISTS `tmp_v_station`");
		$sql = "
			CREATE TABLE `tmp_v_station` (
				`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
				`v` INT NOT NULL ,
				INDEX ( `v` )
			) ENGINE = MYISAM
			SELECT
				DISTINCT(html_version) AS v
			FROM
				station
			WHERE
					basin_id = $basin_id
				AND
					infotype_id = $infotype_id
			ORDER BY
				html_version;
			";
		$this->connect('realtime_tool')->query($sql);
		
		$sql = "
			SELECT
				s1.id AS s1_id,
				s2.id AS s2_id,
				s2.basin_id AS basin_id,
				s2.infotype_id AS infotype_id,
				s2.station_strid AS station,
				s2.html_version AS version,
				s2.descriptor AS descriptor,
				IF (s1.id IS NULL, '+', IF (s1.descriptor = s2.descriptor, '=', '*')) AS status
			FROM
				station AS s2
			JOIN
				tmp_v_station AS v2
			ON
					s2.basin_id = $basin_id
				AND
					s2.infotype_id = $infotype_id
				AND
					s2.html_version = v2.v
			LEFT JOIN
				tmp_v_station AS v1
			ON
				v1.id = v2.id - 1
			LEFT JOIN
				station AS s1
			ON
					s1.html_version = v1.v
				AND
					s1.basin_id = s2.basin_id
				AND
					s1.infotype_id = s2.infotype_id
				AND
					s1.station_strid = s2.station_strid
			WHERE
					".($brief ? "
					s1.descriptor != s2.descriptor
				OR
					s1.id IS NULL" : "
				TRUE")."
			
			UNION
			
			SELECT
				s1.id AS s1_id,
				s2.id AS s2_id,
				s1.basin_id AS basin_id,
				s1.infotype_id AS infotype_id,
				s1.station_strid AS station,
				IF (v2.id IS NULL, s1.html_version, v2.v) AS version,
				IF (v2.id IS NULL, s1.descriptor, '') AS descriptor,
				IF (v2.id IS NULL, '$', '-') AS status
			FROM
				station AS s1
			JOIN
				tmp_v_station AS v1
			ON
					s1.html_version = v1.v
				AND
					s1.basin_id = $basin_id
				AND
					s1.infotype_id = $infotype_id
			LEFT JOIN					-- This will allow [v2.id = 1]
				tmp_v_station AS v2
			ON
				v2.id = v1.id + 1
			LEFT JOIN
				station AS s2
			ON
					s2.html_version = v2.v
				AND
					s1.basin_id = s2.basin_id
				AND
					s1.infotype_id = s2.infotype_id
				AND
					s1.station_strid = s2.station_strid
			WHERE
				s2.id IS NULL
			";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_stations_by_basin_and_infotype($basin_id, $infotype_id) {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		
		$sql = "
			SELECT
				s.*
			FROM
				station AS s
			JOIN (
				SELECT
					MAX(html_version) AS version
				FROM
					station
				WHERE
						basin_id = $basin_id
					AND
						infotype_id = $infotype_id
				) AS v
			ON
				s.html_version = v.version
			WHERE
					s.basin_id = $basin_id
				AND
					s.infotype_id = $infotype_id
		";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_stations_by_html($html_id) {
		$html_id = intval($html_id);
		
		$sql = "
			SELECT
				*
			FROM
				station
			WHERE
				html_id = $html_id
		";
		
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_stations_by_version($version) {
		$version = intval($version);
		
		$sql = "
			SELECT
				*
			FROM
				station
			WHERE
				version = $version
		";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_stations_previous_version($version) {
		$version = intval($version);
		
		$sql = "
			SELECT
				*
			FROM
				station AS t
			JOIN
				(SELECT
					MAX(id) AS id
				FROM
					station
				WHERE
					version < $version
				GROUP BY
					basin_id, infotype_id, station_strid
				) AS t_max
			ON
				t.id = t_max.id
			JOIN
				(SELECT
					basin_id, infotype_id
				FROM
					station
				WHERE
					version = $version
				GROUP BY
					basin_id, infotype_id
				) AS t_v
			ON
				t.basin_id = t_v.basin_id AND t.infotype_id = t_v.infotype_id
			WHERE
				TRUE
			ORDER BY
				t.basin_id, t.infotype_id
		";
		return $this->connect('realtime_tool')->fetchAll($sql);
		
		$sql = "
			";
	}
	
	/**
	 * 取得最新的 station 列表
	 */
	function get_stations_by_latest() {
		$sql = "
			SELECT	s.basin_id
					, b.descriptor		AS basin_descriptor	-- Stations Details page use
					, b.version			AS basin_version	-- Stations Details page use
					, s.infotype_id
					, i.descriptor		AS infotype_descriptor	-- Stations Details page use
					, i.version			AS infotype_version	-- Stations Details page use
					, s.html_version	AS station_version
					, s.station_strid
					, s.descriptor		AS station_descriptor
					, t.version			AS text_version
					, t.records_num		-- Stations Details page use
					, t.inserted_num
			FROM	station	AS s
			JOIN	basin	AS b
				ON	s.basin_id	= b.basin_id
				AND	b.status	= 'current'
			JOIN	basin_infotype AS i
				ON	i.basin_id		= s.basin_id
				AND	i.infotype_id	= s.infotype_id
				AND	i.status		= 'current'
			LEFT JOIN (	-- 先选出来再 left join 效率会高很多
				SELECT	t.basin_id
						, t.infotype_id
						, t.station_strid
						, t.version
						, t.records_num
						, t.inserted_num
				FROM	textdata AS t
				WHERE	t.status = 'current'
			--	GROUP BY	-- 避免出现多个版本都是 current
			--			t.basin_id, t.infotype_id, t.station_strid
			) t
				ON	t.basin_id		= s.basin_id
				AND	t.infotype_id	= s.infotype_id
				AND	t.station_strid = s.station_strid
			WHERE
					s.status = 'current'
			ORDER BY
				s.basin_id, s.infotype_id, s.station_strid
		";
		
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_stations_version($basin_id, $infotype_id) {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		
		$sql = "
			SELECT
				MAX(version)
			FROM
				station
			WHERE
					basin_id = $basin_id
				AND
					infotype_id = $infotype_id
		";
		return intval($this->connect('realtime_tool')->fetchColumn($sql));
	}
	function get_stations_max_version() {
		$sql = "
			SELECT
				MAX(version)
			FROM
				station
			WHERE
				TRUE
		";
		return $this->connect('realtime_tool')->fetchColumn($sql);
	}
	function get_stations($BasinID, $DataType, $cache = true) {
		global $cfg;
		$BasinID = intval($BasinID);
		$DataType = intval($DataType);
		
		if ($cache) {
			$html = AWP_RealTime::get_cache('stations_html_'.$BasinID.'_'.$DataType);
		} else {
			$url = $cfg['urls']['stations']."?Basin=$BasinID&DataType=$DataType";
			$html = AWP_RealTime::get_html($url, $cache);
		}
		$stations = $this->save_stations_html($html, $BasinID, $DataType);
		//AWP_RealTime::set_cache('stations_'.$BasinID.'_'.$DataType, $stations);
		AWP_RealTime::set_cache('stations_html_'.$BasinID.'_'.$DataType, $html);
		return $stations;
	}
	function match_stations($html) {
		if (preg_match_all('/<select .*?<\/select>/is', $html, $m)) {
			preg_match_all('/<option .*?value=".*?StationID=(.*?)".*?>([^<]*?)<\/option>/is', $m[0][0], $m);
			
			$stations = array();
			foreach ($m[1] as $i => $row) {
				if (preg_match('/(.*)\s*- Table$/', $m[2][$i], $m2)) {
					$stations[] = array(
						'id' => $row,
						'name' => $m2[1]
					);
				}
			}
			
			return $stations;
		} else {
			return false;
		}
	}
	function save_stations($stations, $basin_id, $infotype_id, $html_version) {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		$html_version = intval($html_version);
		
		//$t0 = microtime(true);
		$saved_amount = 0;
		if (is_array($stations)) {
			$station_strids = array();
			foreach ($stations as $i => $station) {
				$station_strid = trim($station['id']);
				$descriptor = trim($station['name']);
				if (isset($station_strids[$station_strid])) {
					$station_strids[$station_strid] .= '/'.$descriptor;
				} else {
					$station_strids[$station_strid] = $descriptor;
				}
			}
			foreach ($station_strids as $station_strid => $descriptor) {
				$sql = "
					SELECT	id
					FROM	stations_html
					WHERE	basin_id = $basin_id
						AND	infotype_id = $infotype_id
						AND	version = $html_version
					LIMIT	1";
				$html_id = intval($this->connect('realtime_tool')->fetchColumn($sql));
				
				$sql = "
					INSERT INTO	station
					SET	basin_id = $basin_id,
						infotype_id = $infotype_id,
						html_id = $html_id,
						html_version = $html_version,
						station_strid = '".addslashes($station_strid)."',
						descriptor = '".addslashes($descriptor)."',
						update_time = NOW(),
						status = 'added'";
				if ($this->connect('realtime_tool')->query($sql)) $saved_amount++;
			}
		}
		//pre(microtime(true) - $t0);
		return $saved_amount;
	}
	function update_stations_status($version) {
		$version = intval($version);
		
		$current_stations = $this->get_stations_by_version($version);
		$previous_stations = $this->get_stations_previous_version($version);
		//pre($current_stations,1);
		//pre($previous_stations,1);
		$stations1 = array();
		foreach ($previous_stations as $row) {
			$stations1[$row['basin_id'].'_'.$row['infotype_id'].'_'.$row['station_strid']] = $row;
		}
		$stations2 = array();
		foreach ($current_stations as $row) {
			$stations2[$row['basin_id'].'_'.$row['infotype_id'].'_'.$row['station_strid']] = $row;
		}
		
		foreach ($stations1 as $row) {
			$station_strid = $row['basin_id'].'_'.$row['infotype_id'].'_'.$row['station_strid'];
			if (!isset($stations2[$station_strid])) {
				$sql = "UPDATE station SET status = '-' WHERE id = $row[id]";
				$this->connect('realtime_tool')->query($sql);
			}
		}
		foreach ($stations2 as $row) {
			$station_strid = $row['basin_id'].'_'.$row['infotype_id'].'_'.$row['station_strid'];
			$status = '';
			if (!isset($stations1[$station_strid])) {
				$status = '+';
			} elseif ($stations1[$station_strid]['descriptor'] != $stations2[$station_strid]['descriptor']) {
				$status = '*';
			} else {
				$status = '=';
			}
			$sql = "UPDATE station SET status = '$status' WHERE id = $row[id]";
			$this->connect('realtime_tool')->query($sql);
		}
	}
	
	function get_station_htmls_versions($brief = false) {
		$this->connect('realtime_tool')->query("DROP TABLE IF EXISTS `tmp_v_stations_html`");
		
		$sql = "
			CREATE TABLE `tmp_v_stations_html` (
				`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
				`basin_id` INT NOT NULL DEFAULT 0 ,
				`infotype_id` INT NOT NULL DEFAULT 0 ,
				`v` INT NOT NULL DEFAULT 0 ,
				INDEX ( `basin_id` ) ,
				INDEX ( `infotype_id` ) ,
				INDEX ( `v` )
			) ENGINE = MYISAM
			SELECT
				basin_id,
				infotype_id,
				version AS v
			FROM
				stations_html
			WHERE
				TRUE
			GROUP BY
				basin_id, infotype_id, version
			ORDER BY
				basin_id, infotype_id, version
			";
		$this->connect('realtime_tool')->query($sql);
		
		$sql = "
			SELECT
				t.*,
				c.total
			FROM (
				SELECT
					b.basin_id AS basin_id,
					b.descriptor AS basin_descriptor,
					b.version AS basin_version,
					bi.infotype_id AS infotype_id,
					bi.descriptor AS infotype_descriptor,
					bi.version AS infotype_version,
					s2.version AS station_version,
					IF (s2.id,
						IF (s1.id IS NULL, '+', IF (s1.html_md5 = s2.html_md5, '=', '*')),
						''
					) AS status
				FROM
					basin AS b
				LEFT JOIN
					basin_infotype AS bi
				ON
						b.basin_id = bi.basin_id
					AND
						b.version = bi.basin_version
				LEFT JOIN
					stations_html AS s2
				ON
						s2.basin_id = b.basin_id
					AND
						s2.infotype_id = bi.infotype_id
					AND
						s2.infotype_version = bi.version
				LEFT JOIN
					tmp_v_stations_html AS v2
				ON
						s2.version = v2.v
					AND
						s2.basin_id = v2.basin_id
					AND
						s2.infotype_id = v2.infotype_id
				LEFT JOIN
					tmp_v_stations_html AS v1
				ON
						v1.id = v2.id - 1
					AND
						v1.basin_id = v2.basin_id
					AND
						v1.infotype_id = v2.infotype_id
				LEFT JOIN
					stations_html AS s1
				ON
						s1.basin_id = s2.basin_id
					AND
						s1.infotype_id = s2.infotype_id	
					AND
						s1.version = v1.v
				WHERE"
					.($brief ? "
						s1.html_md5 != s2.html_md5
					OR
						s1.id IS NULL
					" : "
						b.status != 'deleted'
					")."
			
				UNION
			
				SELECT
					b.basin_id AS basin_id,
					b.descriptor AS basin_descriptor,
					b.version AS basin_version,
					bi.infotype_id AS infotype_id,
					bi.descriptor AS infotype_descriptor,
					bi.version AS infotype_version,
					IF (v2.id IS NULL, v1.v, v2.v) AS station_version,
					IF (v2.id IS NULL, '$', '-') AS status -- v2 is null means it is last row
				FROM
					basin AS b
				JOIN
					basin_infotype AS bi
				ON
						b.basin_id = bi.basin_id
					AND
						b.version = bi.basin_version
				JOIN
					stations_html AS s1
				ON
						s1.basin_id = b.basin_id
					AND
						s1.infotype_id = bi.infotype_id
					AND
						s1.infotype_version = bi.version
				LEFT JOIN
					tmp_v_stations_html AS v1
				ON
						s1.version = v1.v
					AND
						s1.basin_id = v1.basin_id
					AND
						s1.infotype_id = v1.infotype_id
				LEFT JOIN
					tmp_v_stations_html AS v2
				ON
						v1.id = v2.id - 1
					AND
						v1.basin_id = v2.basin_id
					AND
						v1.infotype_id = v2.infotype_id
				LEFT JOIN
					stations_html AS s2
				ON
						s1.basin_id = s2.basin_id
					AND
						s1.infotype_id = s2.infotype_id	
					AND
						s2.version = v2.v
				WHERE
					s2.id IS NULL
				) AS t
			LEFT JOIN (
				SELECT
					s.basin_id,
					s.infotype_id,
					s.html_version,
			--		COUNT(DISTINCT(station_strid)) AS total
					COUNT(station_strid) AS total
				FROM
					station AS s
				WHERE
					TRUE
				GROUP BY
					s.basin_id, s.infotype_id, s.html_version
				) AS c
			ON
					t.basin_id = c.basin_id
				AND
					t.infotype_id = c.infotype_id
				AND
					t.station_version = c.html_version
			";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_station_html($basin_id, $infotype_id) {
		global $cfg;
		$url = $cfg['urls']['stations']."?Basin=$basin_id&DataType=$infotype_id";
		$html = AWP_RealTime::get_html($url);
		return $html;
	}
	function get_station_htmls_latest_version($basin_id, $infotype_id) {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		
		$sql = "
			SELECT
				MAX(IF (basin_id = $basin_id AND infotype_id = $infotype_id, version + 1, version))
			FROM
				stations_html
			WHERE
				1";
		return max(1, intval($this->connect('realtime_tool')->fetchColumn($sql)));
	}
	function get_station_htmls_by_version($version = 0) {
		$version = intval($version);
		if ($version == 0) $version = $this->get_station_htmls_latest_version();
		
		$sql = "
			SELECT
				id, basin_id, infotype_id,
				LENGTH(html_content) AS html_length, stations_text,
				update_time, version, status
			FROM
				stations_html
			WHERE
				version = $version
			ORDER BY
				basin_id, infotype_id
		";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_station_htmls_latest($version = 0) {
		$version = intval($version);
		$sql = "
			SELECT
				t.id,
				t.basin_id, t.basin_version,
				t.infotype_id, t.infotype_version,
				LENGTH(t.html_content) AS html_length, t.stations_text,
				t.update_time, t.version, t.status
			FROM
				stations_html AS t
			JOIN
				(SELECT
					MAX(id) AS id
				FROM
					stations_html
				WHERE
					".($version ? "
						version <= $version
					" : "
						TRUE
					")."
				GROUP BY
					basin_id,infotype_id
				) AS t_max
			ON
				t.id = t_max.id
			WHERE
				TRUE
			ORDER BY
				version DESC, basin_id, infotype_id
		";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function save_stations_html($html, $basin_id, $infotype_id, $infotype_version) {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		$infotype_version = intval($infotype_version);
		
//		$sql = "
//			DELETE FROM
//				stations_html
//			WHERE
//					basin_id = $basin_id
//				AND
//					infotype_id = $infotype_id
//				AND
//					infotype_version = $infotype_version
//			";
//		$this->connect('realtime_tool')->query($sql);
		
		$version = $this->get_station_htmls_latest_version($basin_id, $infotype_id);
		
		$sql = "
			SELECT
				id
			FROM
				basin_infotype
			WHERE
					basin_id = $basin_id
				AND
					infotype_id = $infotype_id
				AND
					version = $infotype_version
			LIMIT 1
		";
		$iid = intval($this->connect('realtime_tool')->fetchColumn($sql));
		
		$sql = "
			INSERT INTO
				stations_html
			SET
				basin_id = $basin_id,
				iid = $iid,
				infotype_id = $infotype_id,
				infotype_version = $infotype_version,
				html_md5 = '".md5($html)."',
				html_content = '".addslashes($html)."',
				update_time = NOW(),
				version = $version,
				status = 'added'
		";
		$this->connect('realtime_tool')->query($sql);
		
		return $version;
	}

	function update_textdata_content($basin_id, $infotype_id, $station_strid, $station_version, $textdata, $error, $current_version) {
		$basin_id			= intval($basin_id);
		$infotype_id		= intval($infotype_id);
		$station_version	= intval($station_version);
		$station_strid		= strval($station_strid);
		$current_version	= intval($current_version);
		
		$md5 = md5($textdata);
		
//		$sql = "
//			SELECT	MAX(IF(basin_id = $basin_id
//						AND infotype_id = $infotype_id
//						AND station_strid = '".addslashes($station_strid)."'
//					, version + 1, version))
//			FROM	textdata
//			WHERE	status = 'current'
//		";
//		$version = max(1, intval($this->connect('realtime_tool')->fetchColumn($sql)));
		
//		$sql = "
//			DELETE FROM
//					textdata
//			WHERE	version = $current_version
//				AND	basin_id = $basin_id
//				AND	infotype_id = $infotype_id
//				AND	station_strid = '".addslashes($station_strid)."'
//		";
//		$this->connect('realtime_tool')->query($sql);
//		
//		$sql = "
//			UPDATE	textdata
//			SET		status = ''
//			WHERE	basin_id = $basin_id
//				AND	infotype_id = $infotype_id
//				AND	station_strid = '".addslashes($station_strid)."'
//		";
//		$this->connect('realtime_tool')->query($sql);
		
		$sql = "
			INSERT INTO
					textdata
			SET		basin_id = $basin_id,
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
		
		$this->connect('realtime_tool')->query($sql);
		return $this->connect('realtime_tool')->insertid($sql);
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
	function update_textdata_data($text_id, $basin_id, $infotype_id, $data) {
		$text_id	= intval($text_id);
		$start_time	= isset($data['start_time'])	? $data['start_time'] : '';
		$end_time	= isset($data['end_time'])		? $data['end_time'] : '';
		
		$station = $data['station'];
		$records = $data['records'];
		$columns = $data['columns'];
		
		// 处理抓取到的内容为空的情况
		if (empty($columns)) return false;
		
		$dbname = $this->get_records_dbname($basin_id, $infotype_id, $station);
		$tbname = $this->get_records_tbname($basin_id, $infotype_id, $station);
		// 表还不存在就先创建表
		if (!$this->connect('realtime_data')->is_table_existing($tbname)) {
			$this->textdata_create_table($text_id, $columns, $dbname, $tbname, $basin_id, $infotype_id, $station);
		}
		
		// 进行一些必要的清理
		$this->textdata_clear($dbname, $tbname, $text_id, $start_time, $end_time);
		
		/**
		 * 从表结构中取得字段信息
		 */
		$sql = "DESCRIBE `$dbname`.`$tbname`";
		$rows = $this->connect('realtime_tool')->fetchAll($sql);
		$table_fields = array();
		foreach ($rows as $row) {
			$name = $row['Field'];
			$table_fields[$name] = $row;
			// 把所有数据字段设置为 null，保证插入个别字段的数据时不会出错
			if ($row['Null'] == 'NO' && $row['Type'] == 'float') {
				$sql = "ALTER TABLE `$dbname`.`$tbname` CHANGE `$name` `$name` FLOAT NULL";
				$this->connect('realtime_tool')->query($sql);
			}
		}
		
		foreach ($columns as $i => $field) {
			$name = preg_replace('/\s+/', '_', strtolower($field['field']));
			if ($name == 'data_and_time') $name = 'date_and_time';
			
			// 如果有新字段出现，那么动态添加字段
			if (!isset($table_fields[$name])) {
				$sql = "ALTER TABLE `$dbname`.`$tbname` ADD `$name` FLOAT NULL";
				$this->connect('realtime_tool')->query($sql);
		
				$table_fields[$name] = array (
						'Field' => $name,
						'Type'	=> 'float',
						'Null'	=> 'YES'
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
					AND	station_strid = '" . addslashes($station) . "'
					AND	field = '" . addslashes($name) . "'
			";
			$ids = $this->connect('realtime_tool')->fetchAll($sql);
			
			$descriptor = isset($field['descriptor']) ? $field['descriptor'] : '';
			if (empty($ids)) {
				$sql = "
					INSERT INTO
							station_field
					SET		basin_id = $basin_id
							, infotype_id = $infotype_id
							, station_strid = '" . addslashes($station) . "'
							, text_id = $text_id
							, field = '" . addslashes($name) . "'
							, field_full = '" . addslashes($field['fullname']) . "'
							, descriptor = '" . addslashes($descriptor) . "'
							, demo = '" . addslashes($field['demo']) . "'
							, unit = '" . addslashes(@$field['unit']) . "'
					";
				$this->connect('realtime_tool')->query($sql);
			} else {
				foreach ($ids as $j => $row) {
					if ($j == 0) {
						$sql = "
							UPDATE	station_field
							SET		basin_id = $basin_id
									, infotype_id = $infotype_id
									, station_strid = '" . addslashes($station) . "'
									, text_id = $text_id
									, field = '" . addslashes($name) . "'
									, field_full = '" . addslashes($field['fullname']) . "'
									, descriptor = '" . addslashes($descriptor) . "'
									, demo = '" . addslashes($field['demo']) . "'
									, unit = '" . addslashes(@$field['unit']) . "'
							WHERE	id = $row[id]
						";
						$this->connect('realtime_tool')->query($sql);
					} else {
						$sql = "DELETE FROM station_field WHERE id = $row[id]";
						$this->connect('realtime_tool')->query($sql);
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
			$layer = $this->connect('realtime_tool')->fetch($sql);
			if ($layer) {
				if ($descriptor) {
					$sql = "
						UPDATE	layer
						SET		description = '" . addslashes($descriptor) . "'
								, text_id = $text_id
						WHERE	layerid = $layer[layerid]
					";
					$this->connect('realtime_tool')->query($sql);
				}
			} else {
				$sql = "
					INSERT INTO
							layer
					SET		field = '" . addslashes($name) . "'
							, description = '" . addslashes($descriptor) . "'
							, text_id = $text_id
				";
				$this->connect('realtime_tool')->query($sql);
			}
		}
		
		/**
		 * 插入数据
		 */
		$recorded = 0;
		$fileds = '';
		$values = array();
		foreach ($records as $record) {
			$recorded++;
			$fields_values = array();
			foreach ($columns as $i => $field) {
				$name = preg_replace('/\s+/', '_', strtolower($field['field']));
				if ($name == 'data_and_time') $name = 'date_and_time';
				
				if ($table_fields[$name]['Type'] == 'float') {
					$fields_values[$name] = floatval($record[$i]);
				} elseif ($table_fields[$name]['Type'] == 'varchar(255)') {
					$fields_values[$name] = "'".addslashes($record[$i])."'";
				} elseif ($table_fields[$name]['Type'] == 'int(11)') {
					$fields_values[$name] = "'".intval($record[$i])."'";
				} elseif ($table_fields[$name]['Type'] == 'datetime') {
					$fields_values[$name] = "'".addslashes($record[$i])."'";
					$record_time = strtotime($record[$i]);
					$record_datetime = date('Y-m-d H:i:s', $record_time);
				} else {
					pre($name);
					pre($table_fields,1);
				}
			}
			
			// 拼出字段集
			if ($recorded == 1) {
				$fileds = " (
					text_id, basin_id, infotype_id, `".implode("`,`", array_keys($fields_values))."`
				) VALUES";
			}
			
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
			$last_row = $this->connect('realtime_tool')->fetch($sql);
			
			$same = true;
			foreach ($fields_values as $field_name => $field_value) {
				if (!isset($last_row[$field_name])) {
					$same = false;
					break;
				}
			}
			$last_time = strtotime($last_row['date_and_time']);
			if (!$same || !$last_time || $last_time < $record_time) {
				$values[] .= "($text_id, $basin_id, $infotype_id, ".implode(",", $fields_values).")";
			}
		}
		
		if (!empty($values)) {
			$sql = "
				INSERT INTO
						`$dbname`.`$tbname` $fileds"
				.implode(",\n", $values);
			$this->connect('realtime_tool')->query($sql);
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
		$inserted = intval($this->connect('realtime_tool')->fetchColumn($sql));
		
		if ($start_time && $end_time) {
			$sql = "
				UPDATE	textdata
				SET		start_time = '".addslashes($start_time)."',
						end_time = '".addslashes($end_time)."',
						inserted_num = $inserted,
						records_num = $recorded
				WHERE	id = $text_id";
			$this->connect('realtime_tool')->query($sql);
		}
		
		return $text_id;
	}
	
	// 从保存后的表中统计 field 的起始／结束时间
	function update_textdata_layerinfo($basin_id, $infotype_id, $station_strid) {
		$basin_id	= intval($basin_id);
		$infotype_id	= intval($infotype_id);
		
		$dbname = $this->get_records_dbname($basin_id, $infotype_id, $station_strid);
		$tbname = $this->get_records_tbname($basin_id, $infotype_id, $station_strid);
		
		// 表还不存在说明抓取内容为空，之前没有创建表
		if (!$this->connect('realtime_data')->is_table_existing($tbname)) {
			return true;
		}
		
		/**
		 * 从表结构中取得字段信息
		 */
		$sql = "DESCRIBE `$dbname`.`$tbname`";
		$rows = $this->connect('realtime_tool')->fetchAll($sql);
		$table_fields = array();
		foreach ($rows as $row) {
			$name = $row['Field'];
			if (!in_array($name, array('id', 'basin_id', 'infotype_id', 'text_id', 'station', 'date_and_time'))) {
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
				$row = $this->connect('realtime_tool')->fetch($sql);
				
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
					WHERE	basin_id = $basin_id
						AND	infotype_id = $infotype_id
						AND	station_strid = '" . addslashes($station_strid) . "'
						AND	field = '" . addslashes($name) . "'
				";
				$this->connect('realtime')->query($sql);
			}
		}
	}
	
	function textdata_create_table($text_id, $columns, $dbname, $tbname, $basin_id, $infotype_id, $station) {
		$fields = array();
		$fieldname_values = array();
		foreach ($columns as $row) {
			$field		= $row['field'];
			$name = preg_replace('/\s+/', '_', strtolower($field));
			
			$row['unit'] = isset($row['unit']) ? $row['unit'] : '';
			$row['demo'] = isset($row['demo']) ? $row['demo'] : '';
			// 有的时间列名为 'data_and_time'，把它纠正过来
			if ($name == 'date_and_time' || $name == 'data_and_time') {
				$fields[$name] = "`date_and_time` DATETIME";
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
				, '".addslashes($row['field'])."'
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
		
		$this->connect('realtime_tool')->query($sql);
	}
	function textdata_clear($dbname, $tbname, $text_id, $start_time, $end_time) {
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
		$rows = $this->connect('realtime_tool')->fetchAll($sql);
		foreach ($rows as $row) {
			$sql = "
				DELETE FROM
						`$dbname`.`$tbname`
				WHERE	text_id = $row[text_id]
			";
			$this->connect('realtime_tool')->query($sql);
		}
		
		/**
		 * 保证当前文本所含时间段内无重复数据
		 */
		$sql = "
			DELETE FROM
					`$tbname`
			WHERE	text_id >= $text_id
				AND	date_and_time >= '$start_time'
				AND	date_and_time <= '$end_time'
		";
		$this->connect('realtime_data')->query($sql);
	}
	function update_textdata_status($basin_id, $infotype_id, $station_strid, $version, $status) {
		$basin_id		= intval($basin_id);
		$infotype_id	= intval($infotype_id);
		$version		= intval($version);
		$sql = "
			UPDATE	textdata
			SET		status = '" . addslashes($status) . "'
			WHERE	basin_id		= $basin_id
				AND	infotype_id		= $infotype_id
				AND	station_strid	= '".addslashes($station_strid)."'
				AND	version	= $version
		";
		
		return $this->connect('realtime_tool')->query($sql);
	}
	function get_textdata_history($basin_id, $infotype_id, $station_strid)
	{
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		$station_strid = strval($station_strid);
		
		$sql = "
			SELECT
				id,
				basin_id,
				infotype_id,
				station_strid,
				text_md5,
				update_time,
				version
			FROM
				textdata
			WHERE
					basin_id = $basin_id
				AND
					infotype_id = $infotype_id
				AND
					station_strid = '".addslashes($station_strid)."'
			ORDER BY
				version DESC
		";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}

	function get_textdata_by_id($text_id) {
		$text_id = intval($text_id);
		
		$sql = "
			SELECT	*
			FROM	textdata
			WHERE	id = $text_id
		";
		return $this->connect('realtime_tool')->fetch($sql);
	}

	function get_textdata_by_version($basin_id, $infotype_id, $station_strid, $version) {
		$basin_id		= intval($basin_id);
		$infotype_id	= intval($infotype_id);
		$station_strid	= strval($station_strid);
		$version		= intval($version);
		
		$sql = "
			SELECT	*
			FROM	textdata
			WHERE	basin_id		= $basin_id
				AND	infotype_id		= $infotype_id
				AND	station_strid	= '".addslashes($station_strid)."'
				AND	version			= $version
		";
		return $this->connect('realtime_tool')->fetch($sql);
	}

	function get_textdata_latest($basin_id, $infotype_id, $station_strid) {
		$basin_id		= intval($basin_id);
		$infotype_id	= intval($infotype_id);
		
		$sql = "
			SELECT	*
			FROM	textdata
			WHERE	basin_id		= $basin_id
				AND	infotype_id		= $infotype_id
				AND	station_strid	= '".addslashes($station_strid)."'
			ORDER BY
					version DESC
			LIMIT	1
		";
		return $this->connect('realtime_tool')->fetch($sql);
	}

	function get_textdata_is_updated($basin_id, $infotype_id, $station_strid, $end_time, $text_md5)
	{
		$sql = "
			SELECT
				id
			FROM
				textdata
			WHERE
					basin_id = $basin_id
				AND
					infotype_id = $infotype_id
				AND
					station_strid = '".addslashes($station_strid)."'
				AND (
						end_time = '".addslashes($end_time)."'
					OR
						text_md5 = '".addslashes($text_md5)."'
				)	
			LIMIT
				1
		";
		return $this->connect('realtime_tool')->fetchColumn($sql);
	}
	function get_textdata_latest_version() {
		$sql = "
			SELECT
				MAX(version)
			FROM
				textdata
			WHERE
				1";
		return intval($this->connect('realtime_tool')->fetchColumn($sql));
	}

	/**
	* 返回 current 状态的版本号，或最大版本号 + 1
	*
	* @param    array
	* @return   void
	*/
	function get_textdata_updating_version() {
		$sql = "
				SELECT	basin_id
						, infotype_id
						, station_strid
						, version
				FROM	textdata
				WHERE	status = 'current'
		";
		//pre($this->connect('realtime_tool')->fetchAll($sql),1);
		
		$sql = "
			SELECT	s.basin_id
					, s.infotype_id
					, s.station_strid
					, s.descriptor AS station_descriptor
					, IF (t.version, t.version, 0) AS text_version
			FROM	station AS s
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
		
		return $this->connect('realtime_tool')->fetchColumn($sql);
	}
	function get_textdata_list($basin_id, $infotype_id, $station_strid) {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		$station_strid = strval($station_strid);
		
		$tbname = $this->get_records_tbname($basin_id, $infotype_id, $station_strid);
		$dbname = $this->get_records_dbname($basin_id, $infotype_id, $station_strid);
		
		if ($this->connect('realtime_tool')->is_table_existing($tbname, $dbname)) {
			$sql = "
				SELECT	MIN(r.date_and_time),
						MAX(r.date_and_time),
						COUNT(r.id) AS total,
						d.id,
						d.basin_id,
						d.infotype_id,
						d.station_strid,
						d.text_md5,
						d.update_time,
						d.version,
						d.start_time,
						d.end_time,
						d.inserted_num AS inserted,
						d.records_num AS recorded
						, d.status
				FROM	textdata AS d
				LEFT JOIN
						`$dbname`.`$tbname` AS r
					ON	r.text_id = d.id
				WHERE	d.basin_id = $basin_id
					AND	d.infotype_id = $infotype_id
					AND	d.station_strid = '".addslashes($station_strid)."'
				GROUP BY
						d.id";
		} else {
			$sql = "
				SELECT	d.id,
						d.basin_id,
						d.infotype_id,
						d.station_strid,
						d.text_md5,
						d.update_time,
						d.version,
						d.start_time,
						d.end_time,
						d.inserted_num AS inserted,
						d.records_num AS recorded
						, d.status
				FROM	textdata AS d
				WHERE	d.basin_id = $basin_id
					AND	d.infotype_id = $infotype_id
					AND	d.station_strid = '".addslashes($station_strid)."'
				";
		}
		return $this->connect('realtime_tool')->fetchAll($sql);
	}
	function get_textdata($BasinID, $DataType, $StationID) {
		global $cfg;
		$BasinID = intval($BasinID);
		$DataType = intval($DataType);
		$StationID = strval($StationID);
		
		$url = $cfg['urls']['realtime']."?Type=Table&BasinID=$BasinID&DataType=$DataType&StationID=$StationID";
		$html = AWP_RealTime::get_html($url);
		//$html = '<p><a id="ctl00_ctl00_cphContentSection_MainContentArea_ctl00_OriginalFile" href="/forecasting/data/hydro/tables/ATH-RATHATH-WL.txt">View File</a></p>';
		
		if (preg_match_all('/href="([^>]*)">view file<\/a>/is', $html, $m)) {
			$url = $cfg['urls']['realtime_text'].$m[1][0];
			return AWP_RealTime::get_html($url);
		} else {
			return false;
		}
	}
	function match_data($text) {
		$lines = preg_split('/\r\n|\r|\n/', $text);
		unset($text);
		
		$page = 0;
		$head = false;
		$fields = array();
		$field_keys = array();
		$field_units = array();
		$field_lengths = array();
		$records = array();
		
		$last_update = '';
		$update_time = '';
		$page_number = 0;
		$last_type = '';
		$station_description = '';
		
		foreach ($lines as $line) {
			if (!$last_type || '#' == $last_type) $type = $this->line_type($line);
			
			if ($last_type == 'comment_start') {
				if ($this->is_type('comment_start', $line)) $type = '';
			} elseif ($type == 'comment_start') {
			} elseif ($last_type == 'head_start') {
				if ($this->is_type('head_start', $line)) {
					$type = 'record';
				} else {
					if (!$head && preg_match_all('/\|\s*(\w+( \w+)*)\s*(\(.*?\))?\s*(?=\|)/', $line, $m)) {
						$fields = $m[1];
						foreach ($m[0] as $i => $val) $field_lengths[$i] = strlen($val);
						$head = true;
					} else {
						if ($head && preg_match_all('/\|\s*([^\|]*?)\s*(?=\|)/', $line, $m)) {
							$field_units = $m[1];
							$head = false;
						}
					}
					
					if ($fields && $field_units) {
						foreach ($field_units as $i => $unit) $field_keys[$i] = $fields[$i];
					} else {
						$field_keys = $fields;
					}
				}
			} elseif ($type == 'head_start') {
			} elseif ($last_type == 'record') {
				if ($this->is_type('comment_start', $line)) {
					$type = 'comment_start';
				} else if ($this->is_type('descriptor', $line)) {
					$type = 'record';
					
					if (preg_match_all('/\s*?(\w+( \w+)*)\s*=\s*(\w+( \w+)*)\s*/', $line, $m)) {
						//pre($m);
					}
				} else {
					$type = 'record';
					if (preg_match_all('/\s*((\S+ ?)+?)(?=\s{2,}|$)/', $line, $m)) {
						if (count($field_keys) != count($m[1])) {
							$record = array();
							$start = 0;
							foreach ($field_lengths as $i => $len) {
								$record[$field_keys[$i]] = trim(substr($line, $start, $len));
								$start += $len;
							}
						} else {
							$record = array_combine($field_keys, $m[1]);
						}
						$record['last_update'] = $last_update;
						$record['update_time'] = $update_time;
						$record['page_number'] = $page_number;
						$records[] = $record;
					}
				}
			} elseif ($type == 'station') {
				$type = '';
				if (preg_match('/^\s*?((\w+ ?)+)\s*\:\s*((\w+ ?)+)\s*$/', $line, $m)) {
					$station_description = $m[3];
				}
			} elseif ($type == 'last_update') {
				$type = '';
				if (preg_match('/^.*?Last Updated\:\s*(\d{4}-\d{2}-\d{2})\s*$/', $line, $m)) {
					$last_update = $m[1];
				}
			} elseif ($type == 'update_time') {
				$type = '';
				if (preg_match('/^.*?Time\:\s*(\d{2}\:\d{2}\:\d{2})\s*$/', $line, $m)) {
					$update_time = $m[1];
				}
			} elseif ($type == 'page_number') {
				$type = '';
				if (preg_match('/^.*?Page\:\s*(\d+)\s*$/', $line, $m)) {
					$page_number = $m[1];
				}
			}
			$last_type = $type;
		}
		return $records;
	}

	/**
	 * @author malian 2010-05-19
	 * 匹配列信息
	 */
	function match_textdata_meta($text, $station_strid) {
		$lines = preg_split('/\r\n|\r|\n/', $text);
		unset($text);

		$columns = array();

		$last_type	= '';
		$head		= false;
		$record		= '';
		foreach ($lines as $line) {
			if (trim($line) == '') continue;
			$type = $this->line_type($line, $station_strid);
			
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
			}
			
			$last_type = $type;
		}
		return $columns;
	}
	/**
	 * 匹配除列信息以外的所有相关数据
	 *
	 * @param string	$text			待分析文本
	 * @param array		$columns		列信息
	 * @param string	$station_strid
	 * @return array($station, $start_time, $end_time, $last_update, $update_time, $station_fullname, $page_number, $records, $columns)
	 */
	function match_textdata_data($text, $columns, $station_strid) {
		$lines = preg_split('/\r\n|\r|\n/', $text);
		unset($text);
		
		$info = array(
			'station' => $station_strid
		);
		$records = array();
		$last_type = '';
		foreach ($lines as $line) {
			$type = $this->line_type($line, $station_strid);
			
			if ($last_type == 'head_start') {
				if ($type == 'head_start') {
					$type = 'head_end';
				} else {
					$type = 'head_start';
				}
			} elseif ($last_type == 'head_end') {
				if ($type == 'record') {
					if (preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $line, $m)) {
						$info['end_time'] = $m[0];
						if (!isset($info['start_time'])) $info['start_time'] = $m[0];
					}
					
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
						$records[] = $record;
					}
				}
				$type = 'head_end';
			} elseif ($type == 'last_update') {
				$type = '';
				if (preg_match('/^.*?Last Updated\:\s*(\d{4}-\d{2}-\d{2})\s*$/', $line, $m)) {
					$info['last_update'] = $m[1];
				}
			} elseif ($type == 'update_time') {
				$type = '';
				if (preg_match('/^.*?Time\:\s*(\d{2}\:\d{2}\:\d{2})\s*$/', $line, $m)) {
					$info['update_time'] = $m[1];
				}
			} elseif ($type == 'station') {
				$type = '';
				if (preg_match('/^\s*?((\w+ ?)+)\s*\:\s*((\w+ ?)+)\s*$/', $line, $m)) {
					$info['station_fullname'] = $m[3];
				}
			} elseif ($type == 'page_number') {
				$type = '';
				if (preg_match('/^.*?Page\:\s*(\d+)\s*$/', $line, $m)) {
					$info['page_number'] = $m[1];
				}
			}
			$last_type = $type;
		}
		$info['records'] = $records;
		$info['columns'] = $columns;
		return $info;
	}
	function saveData($records, $meta, $info, $StationID) {
		if (is_array($records)) {
			foreach ($records as $row) {
				$this->save($row);
			}
		}
	}
	function save($row) {pre($row,1);
		$station = addslashes($row['Station']);
		$monitor_time = addslashes($row['Data and Time']);
		$water_level = floatval($row['Water Level']);
		$air_temperature = isset($row['AT']) ? floatval($row['AT']) : null;
		
		$flow = floatval($row['Flow']);
		$last_update = "$row[last_update] $row[update_time]";
		
		$sql = "
			SELECT
				COUNT(*)
			FROM
				realtime
			WHERE
					station = '$station'
				AND
					monitor_time = '$monitor_time'
			LIMIT
				1";
		$result = $this->connect('realtime_tool')->fetchColumn($sql);
		
		if (!$result) {
			$sql = "
				INSERT INTO
					realtime
				SET
					station = '$station',
					water_level = $water_level,
					flow = $flow,
					
					monitor_time = '$monitor_time',
					last_update = '$last_update',
					ingest_time = NOW()
			";
			$this->connect('realtime_tool')->query($sql);
		}
	}
	function clear_textdata($basin_id, $infotype_id) {
		$stations = $this->get_stations_by_basin_and_infotype($basin_id, $infotype_id);
		
		$tbname = array();
		foreach ($stations as $station) {
			$tbname[$station['station_strid']] = "`awp_realtime_${basin_id}_${infotype_id}_$station[station_strid]`";
		}
		
		$sql = "DROP TABLE IF EXISTS ".implode(",", $tbname);
		return $this->connect('realtime_tool')->query($sql);
	}
	
	function get_records_tbname($basin_id, $infotype_id, $station_strid) {
		$basin_id = intval($basin_id);
		$infotype_id = intval($infotype_id);
		$station_strid = strtolower(strval($station_strid));
		return "basin_${basin_id}_datatype_${infotype_id}_$station_strid";
	}
	function get_records_dbname($basin_id, $infotype_id, $station_strid) {
		global $cfg;
		
		return $cfg['dbs']['realtime_data']['database'];
	}
	function get_records_by_time($basin_id, $infotype_id, $station_strid, $start_time, $end_time) {
		$dbname = $this->get_records_dbname($basin_id, $infotype_id, $station_strid);
		$tbname = $this->get_records_tbname($basin_id, $infotype_id, $station_strid);
		
		$sql = "
			SELECT	d.*,
					t.version AS version
			FROM	`$dbname`.`$tbname` AS d
			JOIN	textdata AS t
				ON	d.text_id = t.id
			WHERE	date_and_time >= '".addslashes($start_time)."'
				AND	date_and_time <= '".addslashes($end_time)."'
			ORDER BY	version, date_and_time
		";
		return $this->connect('realtime_tool')->fetchAll($sql);
	}

	function get_html($url) 
	{
		$info = array($url);

		$ch = curl_init($url);
		$info[] = $ch;

		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$html = curl_exec($ch);
		curl_close($ch);
		$info[] = $html;

		if ($html)
		{
			return $html;
		}
		else
		{
			return false;
		}
	}

	function post_html($url, $data = '')
	{
		$info = array($url);
		
		$ch = curl_init($url);
		$info[] = $ch;
		
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		
		$html = curl_exec($ch);
		curl_close($ch);
		$info[] = $html;
		
		if ($html) {
			AWP_RealTime::set_cache($url, $html);
			return $html;
		} else {
			return false;
		}
	}
	function get_cache($id) {return false;
		static $cache = array();
		
		if (empty($cache)) {
			$cache = unserialize(file_get_contents(ROOT.'/cache.txt'));
		}
		
		if (isset($cache[$id])) {
			return $cache[$id];
		} else {
			return false;
		}
	}
	function set_cache($id, $data) {return ;
		$cache = unserialize(file_get_contents(ROOT.'/cache.txt'));
		$cache[$id] = $data;
		file_put_contents(ROOT.'/cache.txt', serialize($cache));
	}
	
	function add_log($type, $name, $value = '', $serial = 0) {
		$serial = intval($serial);
		
		if (!$serial) {
			$sql = "
				SELECT MAX(`serial`)
				FROM logs
			";
			$serial = 1 + $this->connect('realtime_tool')->fetchColumn($sql);
		}
		
		$sql = "
			INSERT INTO logs
			SET	`type` = '".addslashes($type)."'
				, `serial` = $serial
				, `time` = NOW()
				, `name` = '".addslashes($name)."'
				, `value` = '".addslashes($value)."'
		";
		$this->connect('realtime_tool')->query($sql);
		
		return $serial;
	}
	function update_log($type, $name, $value = '', $serial) {
		$serial = intval($serial);
		
		$sql = "
			SELECT	id
			FROM	logs
			WHERE	`type` = '".addslashes($type)."'
				AND	`name` = '".addslashes($name)."'
				AND	`serial` = $serial
			";
		$id = $this->connect('realtime_tool')->fetchOne($sql);
		
		if ($id) {
			$sql = "
				UPDATE logs
				SET	`value` = '".addslashes($value)."'
					, `time` = NOW()
				WHERE	`type` = '".addslashes($type)."'
					AND	`name` = '".addslashes($name)."'
					AND	`serial` = $serial
				";
		} else {
			$sql = "
				INSERT INTO logs
				SET	`type` = '".addslashes($type)."'
					, `serial` = $serial
					, `time` = NOW()
					, `name` = '".addslashes($name)."'
					, `value` = '".addslashes($value)."'
			";
		}
		$this->connect('realtime_tool')->query($sql);
	}
	/**
	* 选择最后一条日志信息
	*
	* @param    $type
	* @param    $name
	* @return   void
	*/
	function log_select($type, $name = '') {
		if ($name) {
			$sql = "
				SELECT	*
				FROM	logs
				WHERE	`type` = '".addslashes($type)."'
					AND	`name` = '".addslashes($name)."'
				ORDER BY `time` DESC
				LIMIT	1
			";
		} else {
			$sql = "
				SELECT	*
				FROM	logs
				WHERE	`type` = '".addslashes($type)."'
				ORDER BY `time` DESC
				LIMIT	1
			";
		}
		
		return $this->connect('realtime_tool')->fetch($sql);
	}
	
	function searchByKeywords($keywords, $start = 0, $limit = 10, $sort = '', $dir = '') {
		$start = intval($start);
		$limit = intval($limit);
		$result = array();
		
		$sql = "
			SELECT	COUNT(*)
			FROM	station AS s
			WHERE	s.html_version = (SELECT MAX(version) FROM stations_html)
				AND (	station_strid LIKE '%".addslashes($keywords)."%'
					OR	descriptor LIKE '%".addslashes($keywords)."%'
				)";
		$total = $this->connect('realtime_tool')->fetchColumn($sql);
		
		$dir = $dir == 'DESC' ? 'DESC' : 'ASC';
		if ($sort == 'station_strid') {
			$orderby = "s.station_strid $dir, s.basin_id, s.infotype_id";
		} elseif ($sort == 'station_descriptor') {
			$orderby = "s.descriptor $dir, s.basin_id, s.infotype_id";
		} elseif ($sort == 'basin') {
			$orderby = "b.descriptor $dir, s.infotype_id, s.descriptor";
		} elseif ($sort == 'basin_id') {
			$orderby = "s.basin_id $dir, s.infotype_id, s.descriptor";
		} elseif ($sort == 'infotype') {
			$orderby = "i.descriptor $dir, s.basin_id, s.descriptor";
		} elseif ($sort == 'infotype_id') {
			$orderby = "s.infotype_id $dir, s.basin_id, s.descriptor";
		} else {
			$orderby = "s.station_strid $dir, s.basin_id, s.infotype_id";
		}
		$sql = "
			SELECT	s.station_strid
					, s.descriptor AS station_descriptor
					, b.basin_id
					, b.descriptor AS basin_descriptor
					, i.infotype_id
					, i.descriptor AS infotype_descriptor
					, t.version AS text_version
					, t.update_time
					, t.start_time
					, t.end_time
					, t.inserted_num
					, t.records_num
					, t.text_content
					, (	SELECT	COUNT(*)
						FROM	textdata
						WHERE	basin_id = s.basin_id
							AND	infotype_id = s.infotype_id
							AND	station_strid = s.station_strid
						) AS history
			FROM	station AS s
			JOIN	stations_html AS sh
				ON	s.html_id = sh.id
				AND	s.html_version = (SELECT MAX(version) FROM stations_html)
			JOIN	basin_infotype AS i
				ON	sh.iid = i.id
			JOIN	basin AS b
				ON	i.bid = b.id
			LEFT JOIN	textdata AS t
				ON	t.basin_id = s.basin_id
				AND	t.infotype_id = s.infotype_id
				AND	t.station_strid = s.station_strid
				AND	t.version = (SELECT MAX(version) FROM textdata)
			WHERE	s.station_strid LIKE '%".addslashes($keywords)."%'
				OR	s.descriptor LIKE '%".addslashes($keywords)."%'
			ORDER BY	$orderby
			LIMIT	$start, $limit
		";
		
		$rows = $this->connect('realtime_tool')->fetchAll($sql);
		$result['total'] = $total;
		$result['list'] = $rows;
		return $result;
	}
	
	function line_type($line, $StationID = '##') {
		$types = array(
			'descriptor',
			'station',
			'comment',
			'comment_start',
			'head',
			'head_start',
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
	function is_type($type, $line, $StationID = '##') {
		switch ($type) {
			case 'comment_start':
				return preg_match('/^\|-+\|$/', $line);
			case 'comment':
				return preg_match('/^\|-*?[^\-=].*?\|$/', $line);
			case 'head_start':
				return preg_match('/^(\|=+(?=\|))+\|$/', $line);
			case 'head':
				return preg_match('/^(\|\s*[^\|]*?\s*(?=\|))+$/', $line);
			case 'record':
				return preg_match('/^\s*?'.$StationID.'\s*([^\:\s]+( \S+)*(\s{2,}|\s*$))+/', $line);
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