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
	
	function getBasinList() {
		$model = ML::instance('RealtimeBasin');
		return $model->getList();
	}
	
	function getDataTypeListByBasin($basin = '') {
		$basin = isset($_REQUEST['basin']) ? $_REQUEST['basin'] : '';
		
		$model = ML::instance('RealtimeInfotype');
		return $model->getInfotypeListByBasin($basin);
	}
	
	function getStationListByBasinAndDatatype($basin = '', $datatype = '') {
		$basin		= isset($_REQUEST['basin'])		? $_REQUEST['basin'] : '';
		$datatype	= isset($_REQUEST['datatype'])	? $_REQUEST['datatype'] : '';
		
		$model = ML::instance('RealtimeStation');
		return $model->getListByBasinInfotypeId($basin, $datatype);
	}
	
	function getData($basin = '', $datatype = '', $station = '', $ps = 1, $pl = 100) {
		$basin		= isset($_REQUEST['basin'])		? $_REQUEST['basin'] : '';
		$datatype	= isset($_REQUEST['datatype'])	? $_REQUEST['datatype'] : '';
		$station	= isset($_REQUEST['station'])	? $_REQUEST['station'] : '';
		
		$model = ML::instance('RealtimeText');
		return $model->getRecord($basin, $datatype, $station, $ps, $pl);
	}
	
	function getUpdatingStatus() {
		$RealtimeText = ML::instance('RealtimeText');
		return $RealtimeText->getUpdatingStatus();
	}
	
	function index() {
		$path = dirname($_SERVER['SCRIPT_NAME']);
		$baseUrl = 'http://' . $_SERVER['HTTP_HOST'] . ($path == '/' ? '' : $path);
		
		$s = $this->getServiceList();
		$s = $s['servicelist'];
?>
<!DOCTYPE>
<html>
<head>
<title>Real-time Data API Calls - Alberta Water Portal</title>
<style type="text/css">
body {
	font-size: 12px
}
dl dt {
	font-size: 24px
}
input, dl dd {
	font-size: 16px
}
textarea {
	font-size: 12px
}
</style>
</head>

<body>
<dl>
<dt>Stations Call</dt>
<dd><a href="<?php echo $baseUrl?>/stations"><?php echo $baseUrl?>/stations</a>
	(<a href="<?php echo $baseUrl?>/stations?XML">XML</a>)</dd>
<dt>Station Layer Data Call:</dt>
<dd>
<form method="post" action="<?php echo $baseUrl?>/postjsondata/" enctype="text/xml">
<input type="submit" value="Post" /> below data to <?php echo $baseUrl?>/station
(<a href="javascript:void(0)" onclick="var fm=document.getElementsByTagName('form')[1];fm.action+='?XML';fm.submit()">XML</a>)<br />
<textarea name="jsondata" cols="80" rows="9"><?php echo '{
    "request" : "getdata",
    "serviceid" : 2,
    "layerid" : "value",
    "time" : {
        "begintime" : "' . $s['time']['begintime'] . '",
        "endtime" : "' . $s['time']['endtime'] . '" 
    },
    "station" : "Abee AGDM"
}'?></textarea></form>
</dd>
</dl>
</body>
</html>
<?php
	}
	function old_index() {
		$rc = new ReflectionClass(get_class($this));
		//pre(Reflection::export(new ReflectionClass('ReflectionClass'),1));
		$rms = $rc->getMethods();
		
		$method_rps = array();
		foreach ($rms as $rm) {
			$methodName = $rm->getName();
			$method_rps[$methodName] = array();
			
			$rps = $rm->getParameters();
			foreach ($rps as $rp) {
				$paramName = $rp->getName();
				$isDefaultValueAvailable = $rp->isDefaultValueAvailable();
				$method_rps[$methodName][$paramName]['isDefaultValueAvailable'] = $isDefaultValueAvailable;
				if ($isDefaultValueAvailable) {
					$method_rps[$methodName][$paramName]['defaultValue'] = $rp->getDefaultValue();
				}
			}
		}
		
		$modelBasin = ML::instance('RealtimeBasin');
		$basinList = $modelBasin->getList();
		//$basinVersionList = $modelBasin->getVersionList();
		//pre($basinList,1);
		
		$modelDatatype = ML::instance('RealtimeInfotype');
		if (isset($basinList[0])) {
			$datatypeList = $modelDatatype->getInfotypeListByBasin($basinList[0]['basin_id']);
		} else {
			$datatypeList = array();
		}
		//$datatypeVersionList = $modelDatatype->getVersionList();
		//pre($datatypeList,1);
		
		$modelStation = ML::instance('RealtimeStation');
		if (isset($basinList[0]) && isset($datatypeList[0])) {
			$stationList = $modelStation->getListByBasinInfotypeId($basinList[0]['basin_id'], $datatypeList[0]['infotype_id']);
		} else {
			$stationList = array();
		}
		
		$paramValues = array();
		$paramValues['basin'] = addslashes(join('', ML::html_select($basinList, array(
			'valueKey'	=> 'basin_id',
			'textKey'	=> 'descriptor',
			'attributes' => 'id="sel_basin" onchange="onBasinChanged(this)"'
		))));
		$paramValues['datatype'] = addslashes(join('', ML::html_select($datatypeList, array(
			'valueKey'	=> 'infotype_id',
			'textKey'	=> 'infotype_descriptor',
			'attributes' => 'id="sel_datatype" onchange="onDatatypeChanged(this)"'
		))));
		$paramValues['station'] = addslashes(join('', ML::html_select($stationList, array(
			'valueKey'	=> 'station_strid',
			'textKey'	=> 'station_descriptor',
			'attributes' => 'id="sel_station" onchange="getAPI()"'
		))));
		
		$paramValues['request'] = addslashes(str_replace("\n", '', '<textarea rows="10" cols="80" onkeyup="getAPI()">{
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
		}</textarea>'));
		
		$paramValues['ps'] = addslashes('<input type="text" value="1" />');
		$paramValues['pl'] = addslashes('<input type="text" value="100" />');
		//$paramValues['getBasinList::version'] = addslashes(join('', ML::html_select($basinVersionList, array(
		//	'valueKey'	=> 'version',
		//	'textKey'	=> 'version',
		//	'attributes' => 'onchange="getAPI()"'
		//))));
		//$paramValues['getDataTypeList::version'] = addslashes(join('', ML::html_select($datatypeVersionList, array(
		//	'valueKey'	=> 'version',
		//	'textKey'	=> 'version',
		//	'attributes' => 'onchange="getAPI()"'
		//))));
		//pre($paramValues, 1);
		
		ML::head(array('title' => 'Real-time Data Ingesting Tool - Alberta Water Portal'));
?>
<link type="text/css" rel="stylesheet" href="../../themes/default/realtime.css"/>
<script type="text/javascript" src="../../js/firefly.js"></script>
<script type="text/javascript">
var parameters = eval('(<?php echo ML::json_encode($method_rps)?>)');
var paramvalues = eval('(<?php echo ML::json_encode($paramValues)?>)');
</script>
</head>

<body>

<h1>API demo</h1>
<table id="tb" border="1" width="100%" cellpadding="3" cellspacing="1">
	<tr>
		<td width="100">API name</td>
		<td><select id="sel_actions" onchange="selectParameters()">
		<?php foreach ($rms as $rm):?>
		<option><?php echo $rm->getName()?></option>
		<?php endforeach;?>
		</select></td>
	</tr>
	<tr>
		<td>Parameters</td>
		<td><table id="tb_parameters" border="0" width="100%" cellpadding="3" cellspacing="1" bgcolor="Silver"></table></td>
	</tr>
	<tr>
		<td>Returned format</td>
		<td><select id="sel_format" onchange="getAPI()">
		<option>XML</option>
		<option>JSON</option>
		<option>RSS</option>
		<option>PHP Serialized</option>
		<option>TAB</option>
		</select></td>
	</tr>
	<tr>
		<td>API URL</td>
		<td><a id="api" target="ifm_api"></a></td>
	</tr>
</table>
<iframe id="ifm_api" name="ifm_api" width="100%" frameborder="0"></iframe>
<script type="text/javascript">
var apiURL = document.location.href;
function selectParameters() {
	var sel_action = document.getElementById('sel_actions');
	
	var tb_parameters = document.getElementById('tb_parameters');
	for (var i = tb_parameters.rows.length - 1; i >= 0; i--) {
		tb_parameters.deleteRow(i);
	}
	
	var params = parameters[sel_action.value];
	var pairs = [];
	for (var paramName in params) {
		var tr		= tb_parameters.insertRow(-1);
		tr.bgColor	= 'white';
		
		var td_paramName	= tr.insertCell(-1);
		var td_paramValue	= tr.insertCell(-1);
		
		td_paramName.style.width = '100px';
		td_paramName.innerHTML = paramName;
		
		if (paramvalues[sel_action.value + '::' + paramName]) {
			td_paramValue.innerHTML = paramvalues[sel_action.value + '::' + paramName];
		}
		if (paramvalues[paramName]) {
			td_paramValue.innerHTML = paramvalues[paramName];
		}
		
		if (td_paramValue.firstChild) {
			pairs.push(paramName + '=' + td_paramValue.firstChild.value)
		}
	}
	
	getAPI();
}
function getAPI() {
	var sel_action = document.getElementById('sel_actions');
	
	var tb_parameters = document.getElementById('tb_parameters');
	var pairs = [];
	for (var i = 0; tr = tb_parameters.rows[i]; i++) {
		var cells = tr.cells;
		if (cells[1].firstChild) {
			pairs.push(cells[0].innerHTML + '=' + cells[1].firstChild.value);
		}
	}
	
	var api = document.getElementById('api');
	var s = apiURL + '?action=' + sel_action.value
		+ (pairs.length ? '&' + pairs.join('&') : '')
		+ '&format=' + document.getElementById('sel_format').value;
	api.href = api.innerHTML = s;
}
selectParameters();
function onBasinChanged(sel) {
	var sel_datatype = document.getElementById('sel_datatype');
	if (!sel_datatype) {
		getAPI();
		return;
	}
	
	var ajax = ML.Ajax();
	ajax.onready = function(json) {
		for (var i = sel_datatype.options.length - 1; i >= 0; i--) {
			sel_datatype.remove(i);
		}
		
		for (var i = 0, len = json.length; i < len; i++) {
			var option = document.createElement('option');
			option.text		= json[i].infotype_descriptor;
			option.value	= json[i].infotype_id;
			sel_datatype.options[sel_datatype.options.length] = option;
		}
		
		onDatatypeChanged();
	}
	ajax.open('GET', apiURL + '?action=getDataTypeListByBasin&basin=' + sel.value + '&format=json');
	ajax.send(null);
}
function onDatatypeChanged(sel) {
	var sel_station = document.getElementById('sel_station');
	if (!sel_station) {
		getAPI();
		return;
	}
	
	var ajax = ML.Ajax();
	ajax.onready = function(json) {
		for (var i = sel_station.options.length - 1; i >= 0; i--) {
			sel_station.remove(i);
		}
		
		for (var i = 0, len = json.length; i < len; i++) {
			var option = document.createElement('option');
			option.text		= json[i].station_descriptor;
			option.value	= json[i].station_strid;
			sel_station.options[sel_station.options.length] = option;
		}
		
		getAPI();
	}
	ajax.open('GET', apiURL + '?action=getStationListByStationAndDatatype'
		+ '&basin=' + document.getElementById('sel_basin').value
		+ '&datatype=' + document.getElementById('sel_datatype').value
		+ '&format=json');
	ajax.send(null);
}

var ifm_api = document.getElementById('ifm_api');
var tb = document.getElementById('tb');
ifm_api.style.height = document.body.offsetHeight - tb.offsetTop - tb.offsetHeight - 20 + 'px';
</script>
</body>
</html>
<?php
	}
}
?>
