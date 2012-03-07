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
	public function getStationList() {
		$modelLayer = $this->getModel('Layer');
		
		// 691 total
		$stationListWithXY = $modelLayer->getStationList();
		// 691 total
		$layerListByStation = $modelLayer->getLayerInfoByStation();
		// field name information
		$layerList = $modelLayer->getLayerList();

		$result = array(); // for less json data
		foreach ($stationListWithXY as $row) {
			$station = ucwords($row['station']);	// ucwords is important
			
			$layers = $layerListByStation[$station];
			$last4days = array();
			foreach ($layers as $layer) {
				$last4days[] = array(intval($layer['layerid']), intval($layer['last4days']), intval($layer['records']));
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
	public function getDataByLayer($request = '') {
		$default = json_decode('
		{
		    "request" : "getdata",
		    "serviceid" : 2,
		    "layerid" : 1,
		    "time" : {
		        "begintime" : "' . date('Y-m-d H:i:s', time() - 3600 * 24 * 30) . '",
		        "endtime" : "' . date('Y-m-d H:i:s', time()) . '" 
		    },
		    "bbox" : {
		        "upperright" : {
		            "latitude": 60,
		            "longitude": -115
		        },
		        "bottomleft" : {
		            "latitude" : 55,
		            "longitude" : -116
		        } 
		    } 
		}');
		
		$data = explode('=', file_get_contents("php://input"), 2);
		$request = count($data) == 2 ? json_decode(urldecode($data[1])) : '';
		if (!$request) {
			$request = &$default;
		}
		
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
	}
	public function getDataByStation() {
		$default = json_decode('
		{
		    "request" : "getdata",
		    "serviceid" : 2,
		    "layerid" : 1,
		    "time" : {
		        "begintime" : "' . date('Y-m-d H:i:s', time() - 3600 * 24 * 30) . '",
		        "endtime" : "' . date('Y-m-d H:i:s', time()) . '" 
		    },
		    "station" : "Birch River Below Alice Creek" 
		}');
		
		$request = json_decode(file_get_contents("php://input"));
		if (!$request) {
			$request = &$default;
		}
		
		$layerId = $request->layerid;
		$modelLayer = ML::instance('RealtimeLayer');
		//$layerInfo = $modelLayer->getLayerInfo($layerId);
		
		$result = array(
			'layerid' => $layerId,
			'serviceid' => $request->serviceid,
			'data' => array()
		);
		
		$layerField = $layerInfo['field'];
		//if ($layerInfo) {
			$stationService = ML::instance('RealtimeStation');
			$stationList = $stationService->getStationListByLayer($layerId, $request->station);
			foreach ($stationList as $i => $row) {
				$unit = $modelLayer->getLayerUnit($row['basin_id'], $row['infotype_id'], $row['station_strid'], $layerField);
				
				$endtime = $request->time->endtime;
				if (!$endtime) {
					$endtime = $row['endtime'];
				}
				$last4days = strtotime($endtime) - 3600 * 24 * 4;
				$begintime = date('Y-m-d H:i:s', max($last4days, strtotime($request->time->begintime)));
			
				$dataList = $modelLayer->getDataSeries(
					$row['basin_id'], $row['infotype_id'], $row['station_strid'], $layerId,
					$begintime, $request->time->endtime
				);
				
				$result['data'][] = array(
					'layerid' => $layerId,
					'layer' => $layerId,
					'station' => $row['station'],
					'unit' => $unit,
					'readings' => $dataList
				);
			}
		//}
		
		return $result;
	}
	
	public function basinList($request) {
		$modelBasin = $this->getModel('Basin');
		$version = isset($request->version) ? intval($request->version) : false;
		return $modelBasin->getBasinListWithStatus($version);
	}
	public function datatypeList() {
		$modelDatatype = $this->getModel('Datatype');
		$objs = $modelDatatype->getDatatypeListWithStatus();
		
		return array_values($objs);
	}
	
	public function ingestingVersionList() {
		$modelLog = $this->getModel('Log');
		return $modelLog->getVersionList();
	}
	public function stationList() {
		$modelStation = $this->getModel('Station');
		$objs = $modelStation->getStationList();
		
		return $objs;
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
	
	public function stopCurrentIngesting($request) {
		$version = $request->version;
		$modelLog = $this->getModel('Log');
		return $modelLog->stopIngesting($version);
	}
	
	/**
	 * Check updates for Basins, Data types, and Stations
	 */
	public function checkBasinList() {
		$modelBasin = $this->getModel('Basin');
		$basinList = $modelBasin->parseBasinList();
		
		/*foreach ($basinList as &$row) {
			if ($row['id'] == 7) {
				$row['name'] .= ' changed';
			}
		}*/
		
		return $basinList;
	}
	public function basinVersionList() {
		$modelBasin = $this->getModel('Basin');
		$basinList = $modelBasin->getVersionList();
		
		return $basinList;
	}
	public function checkDatatypeList() {
		$modelDatatype = $this->getModel('Datatype');
		
		$cache_file = dirname(__FILE__) . '/datatypeList.txt';
		if (false && file_exists($cache_file) && $cache_data = file_get_contents($cache_file)) {
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
	public function checkStationList($request) {
		$modelStation = $this->getModel('Station');
		$basin_id = $request->basin_id;
		$datatype_id = $request->datatype_id;
		$stationList = $modelStation->parseStationsByBasinAndDatatype($basin_id, $datatype_id);
		
		return $stationList;
	}
}
?>
