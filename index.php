<?php
// allow ajax to cross domain
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$phpInput = file_get_contents('php://input');
	if (get_magic_quotes_gpc()) {
		$phpInput = stripslashes($phpInput);
	}
	
	$request = json_decode($phpInput);
	if ($request->request) {
		require_once 'conf.php';
		require_once 'api/WERealtimeAPIController.php';
		$controller = new WERealtimeAPIController();
		
		$action = $request->request;
		if (method_exists($controller, $action)) {
			$result = $controller->$action();
		} else {
			echo "Wrong action: $action";
			exit(0);
		}
		
		
		if (!is_null($result)) {
			$modelOutput = $controller->getModel('Output');
			$modelOutput->output($result, $format);
		}
		exit(0);
	}
}


require_once 'public/index.php';
exit(0);


/**
 * Get REQUEST_URI from Apache Rewrite in .htaccess
 *  example: [REQUEST_URI] => /awp/api/realtime/services
 *  example: [SCRIPT_NAME] => /awp/api/realtime/index.php
 * Need to handle case that $_SERVER['SCRIPT_NAME'] = '/xxx'
 */
$prefix = dirname($_SERVER['SCRIPT_NAME']);
if ($prefix != '/' && false !== strpos($_SERVER['REQUEST_URI'], $prefix)) {
	$uri = substr($_SERVER['REQUEST_URI'], strlen($prefix));
} else {
	$uri = $_SERVER['REQUEST_URI'];
}
$path = parse_url($uri, PHP_URL_PATH);
$query = parse_url($uri, PHP_URL_QUERY);
$format = $query == 'XML' ? 'XML' : 'JSON';

require_once 'conf.php';
require_once 'api/WERealtimeAPIController.php';
$controller = new WERealtimeAPIController();

/**
 * debug output area
 */
//pre(array($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI']));
//pre(array($uri, $path));


/**
 * Determine $action from $request_uri
 */
if ($path == '/') {
	$phpInput = file_get_contents('php://input');
	$request = json_decode($phpInput);
	
	if ($request->request == 'station-list') {
		$action = 'getStationList';
	} else {
		$action = 'index';
	}
} elseif (preg_match('/\/stations$/', $path)) {
	$action = 'getStationList';
} elseif (preg_match('/\/station$/', $path)) {
	$action = 'getDataByStation';
} elseif (preg_match('/\/services$/', $path)) {
	$action = 'getServiceList';
} elseif (preg_match('/\/service\/1$/', $path)) {
	$action = 'getLayerList';
} elseif (preg_match('/\/postjsondata\/$/', $path)) {
	$rawdata = file_get_contents('php://input');
	$rawdata = urldecode(substr($rawdata, 9));
	//$request = json_decode($rawdata);
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	//添加变量
	curl_setopt($ch, CURLOPT_POSTFIELDS, $rawdata);
	$response = curl_exec($ch);
	curl_close($ch);
	
	echo $response;
	exit(0);
} else {
	$action = 'index';
}


if (method_exists($controller, $action)) {
	$result = $controller->$action();
} else {
	echo "Wrong action: $action";
	exit(0);
}


if (!is_null($result)) {
	$modelOutput = $controller->getModel('Output');
	$modelOutput->output($result, $format);
}
?>