<?php
/**
 * This controller is independent because we want as less files involved as possible
 * @author mlhch
 *
 */
class WERealtimeAPIController {
	public function getModel($name) {
		$name = preg_replace('/[^a-zA-Z]/', '', $name);
		if (file_exists("api/models/$name.php")) {
			require_once "api/models/$name.php";
		}
		
		$modelName = 'WERealtime_Model_' . $name;
		return call_user_func_array(array($modelName, 'getInstance'), array());
	}
	
	public function getServiceList() {
		$modelLayer = $this->getModel('Layer');
		$serviceInfo = $modelLayer->getServiceInfo();
		
		$result = array(
			"servicelist" => array(
				"id" => 1,
				"time" => array(
					"endtime" => $serviceInfo ? $serviceInfo['endtime'] : "",
					"begintime" => $serviceInfo ? $serviceInfo['begintime'] : "",
				),
				"title" => "Alberta Water Portal Real-time Station Data",
				"keywords" => "Real-time, Water, Basin",
				"providername" => "Alberta Water Portal",
				"website" => "http://www.albertawater.com",
				"bbox" => $serviceInfo['bbox'],
				"description" => "Real-time station report data from Alberta stations",
				"authorname" => "Alberta Environment",
				"type" => "Observation Dataset",
				"contact" => ""
			)
		);
		return $result;
	}
	/**
	 * for API http://www.albertawater.com/awp/api/realtime/stations
	 */
	public function getStationListWithLayerNames() {
		$modelLayer = $this->getModel('Layer');
		
		// 691 total, 601 total v2, [{$station,$x,$y},...]
		$stationListWithGeom = $modelLayer->getStationList();
		// 691 total, {$station_description:[{$layerid,$begintime,$endtime,$last4dyas,$records},...],...}
		$stationLayerInfoMap = $modelLayer->getLayerInfoByStation();
		// field name information
		$layerList = $modelLayer->getLayerList();
		
		$result = array(); // for less json data
		foreach ($stationListWithGeom as $row) {
			$station = ucwords($row['station']);	// ucwords is important
			
			$layers = $stationLayerInfoMap[$station];
			$last4days = array();
			foreach ($layers as $layer) {
				$last4days[] = array(
						intval($layer['layerid']),
						intval($layer['last4days']),
						intval($layer['records'])
						);
			}
			
			$result[] = array(
				$station,
				$row['x'] == null ? null : floatval($row['x']),
				$row['y'] == null ? null : floatval($row['y']),
				$last4days
			);
		}
		
		$layerNames = array();
		foreach ($layerList as $row) {
			$layerNames[] = array(
				intval($row['layerid']),
				$row['field'],
				$row['description'] ? $row['description'] : $row['field']
			);
		}
		
		return array('layerNames' => $layerNames, 'result' => $result);
	}
	public function getLayerList() {die('xx');
		$modelLayer = $this->getModel('Layer');
		$layerList = $modelLayer->getLayerList();
		die('xx');
		$rows = $modelLayer->getLayerBBoxList();
		$bboxList = array();
		foreach ($rows as $row) {
			$bboxList[$row['field']] = $row;
		}
		
		foreach ($layerList as &$layer) {
			$bbox = $bboxList[$layer['field']];
			$layer['bbox'] = array(
				"upperright" => array(
					"longitude" => $bbox['right'],
					"latitude" => $bbox['top']
				),
				"bottomleft" => array(
					"longitude" => $bbox['left'],
					"latitude" => $bbox['bottom']
				)
			);
		}
		
		return $layerList;
	}
	/*public function getLayerData($request) {
		$modelLayer = $this->getModel('Layer');
		$layerId = $request->layerid;
		$layerInfo = $modelLayer->getLayerInfoById($layerId);pre($layerInfo,1);
		
		$result = array(
			'layerid' => $layerId,
			'serviceid' => $request->serviceid,
			'data' => array()
		);
		
		if ($layerInfo) {
			$upper = $request->bbox->upperright->latitude;
			$right = $request->bbox->upperright->longitude;
			$bottom = $request->bbox->bottomleft->latitude;
			$left = $request->bbox->bottomleft->longitude;
			$stationList = $modelLayer->getGeometryStationListByLonLat($upper, $right, $bottom, $left);
			$stationList = $modelLayer->filterStationListByLayer($stationList, $layerInfo['field']);
			
			$last4days = strtotime($request->time->endtime) - 3600 * 24 * 4;
			$begintime = date('Y-m-d H:i:s', max($last4days, strtotime($request->time->begintime)));
			foreach ($stationList as $i => $row) {
				if (!empty($row['field'])) {
					$dataList = $modelLayer->getDataSeries(
						$row['basin_id'], $row['infotype_id'], $row['station_strid'], $row['field'],
						$begintime, $request->time->endtime
					);
				} else {
					$dataList = array();
				}
				
				$result['data'][] = array(
					'id' => $i,
					'station' => $row['stn_name'],
					'lon' => $row['lon'],
					'lat' => $row['lat'],
					'readings' => $dataList
				);
			}
		}
		
		return $result;
	}*/
	public function getLayerData($request) {
		$layerId = $request->layerId;
		$stationDescription = $request->station;
		
		$modelStation = $this->getModel('Station');
		$stationObj = $modelStation->getStationByDescription($stationDescription);
		
		$result = array(
			'layerId' => $layerId,
			'station' => $stationObj->Id,
			'data' => array(),
		);
		
		$modelLayer = $this->getModel('Layer');
		$layerInfo = $modelLayer->getLayerInfo($layerId);
		
		if ($layerInfo) {
			$layers = $modelLayer->getLayerInfoByStation($stationObj->Id);
			foreach ($layers as $row) {
				if ($row['layerid'] != $layerId) continue;
				
				//$unit = $modelLayer->getLayerUnit($row['basin_id'], $row['infotype_id'], $row['station_strid'], $layerField);
				
				$endtime = empty($request->time->endtime) ? $row['endtime'] : $request->time->endtime;
				$last4days = @strtotime($endtime) - 3600 * 24 * 4;
				$begintime = @date('Y-m-d H:i:s', max($last4days, @strtotime($request->time->begintime)));
				
				$dataList = $modelLayer->getDataSeries(
					$row['basin_id'], $row['datatype_id'], $stationObj->Id, $layerInfo->Field,
					$begintime, $endtime
				);
				
				$result['data'] = $dataList;
			}
		}
		
		return $result;
	}
	
	/*
	 * Ingesting
	 */
	public function ingestingVersionList($request) {
		$modelLog = $this->getModel('Log');
		$start = isset($request->start) ? intval($request->start) : 0;
		$limit = isset($request->limit) ? intval($request->limit) : 25;
		return $modelLog->getVersionList($start, $limit);
	}
	public function startIngesting($request) {
		$url = $request->url;
		
		$modelLog = $this->getModel('Log');
		$lastSerialInfo = $modelLog->getLastSerial();
		if ($lastSerialInfo && !empty($lastSerialInfo['stop'])) {
			$modelLog->updateIngestLog('stop ingesting', 0, $lastSerialInfo['serial']);
		}
		
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		$response = curl_exec ( $ch );
		curl_close ( $ch );
		
		return 'ok';
	}
	public function stopCurrentIngesting($request) {
		$version = $request->version;
		$modelLog = $this->getModel('Log');
		$modelLog->stopIngesting($version);
		
		return 'ok';
	}
	
	public function stationLayerList($request) {
		$modelLayer = $this->getModel('Layer');
		$station = isset($request->station) ? $request->station : '';
		$stationLayerList = $modelLayer->getStationLayerList($station);
		
		// getting basin and data type list separately can make table 'basin' and 'datatype'
		//   independently removable
		$basinList = array();
		$dataTypeList = array();
		if (! empty ( $stationLayerList )) {
			$basinIds = array();
			$dataTypeIds = array();
			foreach ( $stationLayerList as $layer ) {
				$basin_id = $layer['basin_id'];
				if (!isset($basinIds[$basin_id])) {
					$basinIds[$basin_id] = $basin_id;
				}
				$datatype_id = $layer['datatype_id'];
				if (!isset($dataTypeIds["$basin_id,$datatype_id"])) {
					$dataTypeIds["$basin_id,$datatype_id"] = array($basin_id, $datatype_id);
				}
			}
			$modelBasin = $this->getModel('Basin');
			$basinList = $modelBasin->getBasinListByIds($basinIds);
			
			$modelDatatype = $this->getModel('Datatype');
			$dataTypeList = $modelDatatype->getDatatypeListByIds($dataTypeIds);
		}
		
		return array(
				'stationLayerList' => $stationLayerList,
				'message' => compact('basinList', 'dataTypeList')
		);
	}
	public function textdataHistoryList($request) {
		$modelTextdata = $this->getModel('Textdata');
		$basin_id = $request->basin_id;
		$datatype_id = $request->datatype_id;
		$station_strid = $request->station_strid;
		
		return $modelTextdata->getTextdataList($basin_id, $datatype_id, $station_strid);
	}
	public function singleTextdata($request) {
		$modelTextdata = $this->getModel('Textdata');
		
		if (!empty($request->text_id)) {
			$text_id = $request->text_id;
			$textdata = $modelTextdata->getTextdataById ( $text_id );
			return $textdata;
		} else {
			$basin_id = $request->basin_id;
			$datatype_id = $request->datatype_id;
			$station_strid = $request->station_strid;
			$description = $request->description;
			
			$url = $modelTextdata->ingestUrl($basin_id, $datatype_id, $station_strid);
			$textdata = $modelTextdata->ingestTextdata($url);
			return array('Url' => $url, 'Text' => $textdata);
		}
	}
	public function layerDataList($request) {
		$modelLayer = $this->getModel ( 'Layer' );
		$basin_id = $request->basin_id;
		$datatype_id = $request->datatype_id;
		$station_strid = $request->station_strid;
		$layer = $request->layer;
		
		return $dataList = $modelLayer->getLayerDataList($basin_id, $datatype_id, $station_strid, $layer);
	}
	
	public function parseTextdata($request) {
		$text_id = intval ( $request->text_id );
		$station_strid = $request->station_strid;
		
		$modelTextdata = $this->getModel ( 'Textdata' );
		$textdata = $modelTextdata->getTextdataById ( $text_id );
		
		if ($textdata) {
			$result = $modelTextdata->parseContent ( $textdata->Text );
			
			$basin_id = $textdata->BasinId;
			$datatype_id = $textdata->TypeId;
			$station_strid = $textdata->StationId;
			$modelTextdata->updateTextdataData ( $text_id, $basin_id, $datatype_id, $station_strid, $result );
			
			// 从保存后的表中统计 field 的起始／结束时间
			$modelTextdata->updateTextdataLayerInfo ( $basin_id, $datatype_id, $station_strid );
			
			$textdata = $modelTextdata->getTextdataById ( $text_id );
			return array ('NewRecords' => $textdata->NewRecords, 'AllRecords' => $textdata->AllRecords );
		} else {
			return array ('NewRecords' => 0, 'AllRecords' => 0 );
		}
	}
	
	public function parseTextdataHistory($request) {
		$basin_id = intval ( $request->basin_id );
		$datatype_id = intval ( $request->datatype_id );
		$station_strid = $request->station_strid;
		
		$modelTextdata = $this->getModel ( 'Textdata' );
		$textdataList = $modelTextdata->getTextdataList($basin_id, $datatype_id, $station_strid);
		
		foreach ($textdataList as $row) {
			$textdata = $modelTextdata->getTextdataById ( $row->Id );
			$result = $modelTextdata->parseContent ( $textdata->Text );
			
			$modelTextdata->updateTextdataData($row->Id, $basin_id, $datatype_id, $station_strid, $result);
			// 从保存后的表中统计 field 的起始／结束时间
			$modelTextdata->updateTextdataLayerInfo($basin_id, $datatype_id, $station_strid);
		}
		
		return array('Versions' => count($textdataList));
	}
	
	/**
	 * Basins
	 */
	public function basinList($request) {
		$modelBasin = $this->getModel('Basin');
		$version = isset($request->version) ? intval($request->version) : false;
		return $modelBasin->getBasinListWithStatus($version);
	}
	public function basinVersionList() {
		$modelBasin = $this->getModel('Basin');
		$basinList = $modelBasin->getVersionList();
		
		return $basinList;
	}
	public function checkBasinList() {
		$modelBasin = $this->getModel('Basin');
		
		$cache_file = dirname(__FILE__) . '/basinList.txt';
		if (file_exists($cache_file) && $cache_data = file_get_contents($cache_file)) {
			$basinList = unserialize($cache_data);
		} else {
			$basinList = $modelBasin->parseBasinList();
			file_put_contents($cache_file, serialize($basinList));
		}
		
		return $basinList;
	}
	public function saveBasinList($request) {
		$modelBasin = $this->getModel('Basin');
		$basinList = $request->basinList;
		
		$version = $modelBasin->getLatestVersion() + 1;
		$info = array('new' => 0, 'update' => 0, 'failed' => 0, 'version' => $version);
		foreach ($basinList as $basin) {
			$basin->Version = $version;
			$basin->UpdateTime = @date('Y-m-d H:i:s');
			$result = $modelBasin->saveBasin($basin);
			is_integer($result) && $info['new']++;
			$result === true && $info['update']++;
			$result === false && $info['failed']++;
		}
		return $info;
	}
	/**
	 * Data Types
	 */
	public function datatypeList($request) {
		$modelDatatype = $this->getModel('Datatype');
		$version = isset($request->version) ? intval($request->version) : false;
		$objs = $modelDatatype->getDatatypeListWithStatus($version);
		
		return array_values($objs);
	}
	public function datatypeVersionList() {
		$modelDatatype = $this->getModel('Datatype');
		$datatypeList = $modelDatatype->getVersionList();
		
		return $datatypeList;
	}
	public function checkDatatypeList() {
		$modelBasin = $this->getModel('Basin');
		$modelDatatype = $this->getModel('Datatype');
		
		$basinVersion = $modelBasin->getLatestVersion();
		$dataTypeVersion = $modelDatatype->getLatestVersion();
		
		$cache_file = dirname(__FILE__) . '/datatypeList.txt';
		if (file_exists($cache_file) && $cache_data = file_get_contents($cache_file)) {
			$basinDatatypeList = unserialize($cache_data);
		} else {
			$basinDatatypeList = $modelDatatype->parseDatatypeList();
			file_put_contents($cache_file, serialize($basinDatatypeList));
		}
		
		$datatypeList = array();
		foreach ($basinDatatypeList as $basin) {
			$basin_id = intval($basin['id']);
			foreach ($basin['datatypeList'] as $datatype) {
				$datatype_id = intval($datatype['id']);
				if (!isset($datatypeList[$datatype_id])) {
					$datatypeList[$datatype_id] = array(
							'id' => $datatype_id,
							'name' => $datatype['name'],
							'basins' => array(),
							);
				}
				$datatypeList[$datatype_id]['basins'][] = $basin_id;
			}
		}
		
		return array_values($datatypeList);
	}
	public function saveDatatypeList($request) {
		$dataTypeList = $request->dataTypeList;
		$modelDatatype = $this->getModel('Datatype');
		
		$modelBasin = $this->getModel('Basin');
		$basinVersion = $modelBasin->getLatestVersion();
		$dataTypeVersion = $modelDatatype->getLatestVersion();
		
		if ($dataTypeVersion < $basinVersion) {
			$info = array('success' => 0, 'failure' => 0);
			
			$dataTypeVersion = $basinVersion;
			foreach ($dataTypeList as $dataType) {
				$dataType->Version = $dataTypeVersion;
				$dataType->UpdateTime = @date('Y-m-d H:i:s');
				$result = $modelDatatype->saveDatatype($dataType);
				$result ? $info['success']++ : $info['failure']++;
			}
			
			return $info;
		} else {
			$message = sprintf ( 'Data Types version(%d) is not higher than Basins version(%d),'
					. ' please check updates for Basin first', $dataTypeVersion, $basinVersion );
			return array ('message' => $message );
		}
	}
	/**
	 * Stations
	 */
	public function stationList($request) {
		$modelStation = $this->getModel('Station');
		$objs = $modelStation->getStationList();
		
		return $objs;
	}
	public function stationListWithStatus($request) {
		$modelStation = $this->getModel('Station');
		
		$version = isset($request->version) ? intval($request->version) : false;
		$objs = $modelStation->getStationListWithStatus($version);
		
		return $objs;
	}
	public function stationVersionList() {
		$modelStation = $this->getModel('Station');
		$stationList = $modelStation->getVersionList();
		
		return $stationList;
	}
	public function checkStationList($request) {
		$modelStation = $this->getModel('Station');
		$basin_id = $request->basin_id;
		$datatype_id = $request->datatype_id;
		
		$cache_file = dirname(__FILE__) . "/stationList-$basin_id-$datatype_id.txt";
		if (file_exists($cache_file) && $cache_data = file_get_contents($cache_file)) {
			$stationList = unserialize($cache_data);
		} else {
			$stationList = $modelStation->parseStationsByBasinAndDatatype($basin_id, $datatype_id);
			file_put_contents($cache_file, serialize($stationList));
		}
		
		return $stationList;
	}
	public function saveStationList($request) {
		$modelStation = $this->getModel('Station');
		
		$version = $modelStation->getLatestVersion() + 1;
		$stationList = $request->stationList;
		
		$info = array('success' => 0, 'failure' => 0);
		foreach ($stationList as $station) {
			$params = (array)$station;
			$params['UpdateTime'] = @date('Y-m-d H:i:s');
			$params['Version'] = $version;
			$result = $modelStation->saveStation($params);
			$result ? $info['success']++ : $info['failure']++;
		}
			
		return $info;
	}
}
?>
