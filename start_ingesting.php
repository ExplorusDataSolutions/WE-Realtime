<?php
ignore_user_abort(true);
//set_time_limit(0);

require_once 'conf.php';
require_once 'api/WERealtimeAPIController.php';
$controller = new WERealtimeAPIController();


$modelLog = $controller->getModel('Log');

// 取得最后一次的命令
$lastSerialInfo = $modelLog->getLastSerial();

/**
 * Test scripts
 * @var unknown_type
 */
if (0) {
	$modelTextdata = $controller->getModel('Textdata');
	
	
	$basin_id = 10;
	$infotype_id = 3;
	$station_strid = 'RPAYNELK';
	$station_descriptor = 'Payne Lake Reservoir near Mountain View';
	$station_version	= 0;
	
	$url = $modelTextdata->ingestUrl($basin_id, $infotype_id, $station_strid);
	debug($url);
	
	$textdata = $modelTextdata->ingestTextdata($url);
	pre($textdata,1);
	/**
	 * 处理可能导致插入数据库出错的内容
	 */
	if (strpos($textdata, "\xC0\xE5\x12") !== false) {
		$textdata = str_replace("\xC0\xE5\x12", '######', $textdata);
		$error = '######';
	} else {
		$error = '';
	}
	
	// 匹配列信息
	$result = $modelTextdata->parseContent(
			$textdata,
			$ref_station_code ? $ref_station_code : $station_strid
	);
	$result['station'] = $station_strid;
	//pre($result,1);
	
	// 保存抓取内容
	/*$text_id = $modelTextdata->updateTextdataContent(
			$basin_id,
			$infotype_id,
			$station_strid,
			$station_version,
			$textdata,
			$error,
			0
	);*/
	
	// 保存分析后的所有数据，包括列信息
	$modelTextdata->updateTextdataData(1, $basin_id, $infotype_id, $station_strid, $result);
	
	// 从保存后的表中统计 field 的起始／结束时间
	$modelTextdata->updateTextdataLayerInfo($basin_id, $infotype_id, $station_strid);

	exit(0);
}

$output = '';
// 如果最后一次命令是 开始抓取
if (!$lastSerialInfo || empty($lastSerialInfo['stop'])) {
	// 取得 current 状态的最新版本
	$modelTextdata = $controller->getModel('Textdata');
	$updatingVersion = $modelTextdata->getUpdatingVersion();
	//pre($updatingVersion,1);
	//pre($modelLog->getUpdatingStations(),1);
	/*$current_station = '';
	$stations = $modelLog->getUpdatingStations();
	foreach ($stations as $i => $station) {
		// case 1: an existing text_id with not 'current' status leads to empty text_version
		if (empty($station['text_version']) || $station['text_version'] < $updatingVersion) {
			$current_station = $station;
			break;
		}
	}pre($current_station,1);*/
	
	$serial = $modelLog->addIngestLog('start ingesting', $updatingVersion);
	debug("start: version $updatingVersion, serial: $serial");
	
	/*
	pre($last_station,1);
	if ($last_station) {
		debug("last unfinished station: $last_station[station_strid], $last_station[infotype_id]");pre('xx',1);
		$lastIngestLog = $modelLog->getIngestLog('last ingesting');
		if ($lastIngestLog && $lastIngestLog->Serial < $serial) {
			$modelLog->updateIngestLog(
				'last ingesting',
				"basin:$last_station[basin_id], infotype: $last_station[infotype_id], station: $last_station[station_strid]",
				$serial
			);
		}
	}*/
	
	$counter = 1;
	while (true) {
		$lastSerialInfo = $modelLog->getLastSerial();
		if ($lastSerialInfo && !empty($lastSerialInfo['stop'])) {
			$output = "Ingesting is stopped by command, at $lastSerialInfo[stop_time]";
			break;
		}
		
		$stations = $modelLog->getUpdatingStations();
		
		$current_station = '';
		foreach ($stations as $i => $station) {
			// case 1: an existing text_id with not 'current' status leads to empty text_version
			if (empty($station['text_version']) || $station['text_version'] < $updatingVersion) {
				$current_station = $station;
				break;
			}
		}
		
		if (!$current_station) {
			break;
		} else {
			$station = $current_station;
			
			$basin_id			= $station['basin_id'];
			$infotype_id		= $station['infotype_id'];
			$station_strid		= $station['station_strid'];
			$station_version	= $station['version'];
			$station_descriptor = $station['station_descriptor'];
			
			$currentTextdata = $modelTextdata->getTextdataByVersion($basin_id, $infotype_id, $station_strid, $updatingVersion);
			
			// if the textdata of current version exists
			if ($currentTextdata) {
				// case 1: an existing text_id with 'current' status and previous 'version' leads to here
				if ($station['text_version'] && $station['text_version'] != $updatingVersion) {
					debug("Old textdata $station[text_id] current status cleared");
					// clear old textdata 'current' status
					$modelTextdata->setStatus($basin_id, $infotype_id, $station_strid, $station['text_version'], '');
					continue;
				}
				
				debug("Existing textdata: $station_strid, $basin_id, $infotype_id, version: $updatingVersion");
				// 把当前记录更新为 'current' 状态
				$modelTextdata->setStatus($basin_id, $infotype_id, $station_strid, $currentTextdata->Version, 'current');
				
				$textdata = $currentTextdata->Text;
				$text_id = $currentTextdata->Id;
			} else {
				// 记录当前正在抓取的 station
				$modelLog->updateIngestLog(
					'current ingesting',
					"station: $station_strid, datatype: $infotype_id, basin: $basin_id, progress: $counter/" . count($stations),
					$serial
				);
				
				debug("start ingesting... $station_strid, $infotype_id, $basin_id");
				
				$url = $modelTextdata->ingestUrl($basin_id, $infotype_id, $station_strid);
				debug($url);
				
				$textdata = $modelTextdata->ingestTextdata($url);
				debug('ok. Textdata size: ' . strlen($textdata));
				
				$error = '';
				if ($textdata) {
					/**
					 * some characters that may cause MySQL inserting error
					 */
					if (strpos($textdata, "\xC0\xE5\x12") !== false) {
						$textdata = str_replace("\xC0\xE5\x12", '\xC0\xE5\x12', $textdata);
						$error = '\xC0\xE5\x12';
					}
				}
				
				// 保存抓取内容
				$text_id = $modelTextdata->updateTextdataContent(
					$basin_id,
					$infotype_id,
					$station_strid,
					$station_version,
					$textdata,
					$error,
					$updatingVersion
				);
				debug("Textdata $text_id saved");
				
				if ($text_id && $station['text_version'] && $station['text_version'] != $updatingVersion) {
					debug("Old textdata $station[text_id] current status cleared");
					// clear old textdata 'current' status
					$modelTextdata->setStatus($basin_id, $infotype_id, $station_strid, $station['text_version'], '');
				}
			}
			
			// ignore empty data
			if ($textdata) {
				$result = $modelTextdata->parseContent($textdata);
				debug('Textdata parsed');
				
				// save useful information
				$modelTextdata->updateTextdataData($text_id, $basin_id, $infotype_id, $station_strid, $result);
				$modelTextdata->updateTextdataLayerInfo($basin_id, $infotype_id, $station_strid);
				debug('Statistic data saved');
			}
			
			$counter++;
			debug("Textdata $text_id ingesting finish");//pre($counter,1);
		}
	}
	// 记录结束抓取
	$modelLog->addIngestLog('finish ingesting', $counter, $serial);
	
	$ingest			= $modelLog->getIngestLog();
	$start_ingest	= $modelLog->getIngestLog('start ingesting');
	$finish_ingest	= $modelLog->getIngestLog('finish ingesting');
	
	if ($ingest->Name == 'current ingesting') {
		$output = "The No. $ingest->Serial Ingesting hangs,"
			. " starts from $start_ingest->Time,"
			. " end at $ingest->Time,"
			. " current ingesting: $ingest->Value";
	} elseif ($ingest->Name == 'finish ingesting') {
		$output = "The No. $ingest->Serial Ingesting finishes,"
			. " start from $start_ingest->Time,"
			. " end at $finish_ingest->Time,"
			. " total $counter stations updated";
	}
} else {
	$output = "Ingesting is disabled by command, at $lastSerialInfo[stop_time]";
}
debug($output);

if (EMAIL_REPORT_TO) {
	$success = mail(EMAIL_REPORT_TO, 'Realtime Data Ingesting Tool Report', $output);
	echo 'Email "Realtime Data Ingesting Tool Report" sent ' . ($success ? 'success' : 'failure') . '<br />';
	echo 'Message:<br />' . $output;
}

function debug($message) {
	static $logfile = '';
	if (!$logfile) {
		$logfile = dirname(__FILE__) . '/logs/' . date('Y/m/Y-m-d H.i.s') . '.log';
		if (!file_exists(dirname($logfile))) {
			mkdir(dirname($logfile), 0777, true);
		}
		$fp = fopen($logfile, 'w');
	} else {
		$fp = fopen($logfile, 'a');
	}
	$text = @date("Y-m-d H:i:s ")
		. preg_replace('/.*\./', '.', sprintf("%.4f", round(microtime(true), 4)))
		. " - $message";
	echo $text. '<br />';
	if ($fp) {
		fwrite($fp, "$text\n");
		fclose($fp);
	}
}

function mail2($to, $subject, $message, $from, $username, $password) {
	$msg = array();
	$fp = fsockopen('smtp.163.com', 25);
	$msg[] = $fp;
	
	if ($fp) {
		set_socket_blocking($fp, true);
		$response = fgets($fp, 512);
		if (!preg_match("/^220/", $response)) {
			return false;
		}
		$msg[] = $response;
		
		$commandList = array(
			array("HELO mlhch", "250"),
			array("AUTH LOGIN", "334"),
			array(base64_encode($username), '334'),
			array(base64_encode($password), '235'),
			array("MAIL FROM: <$from>", "250"),
			array("RCPT TO: <$to>", "250"),
			array("DATA", '354'),
			array(implode("\n", array(
				"Subject: $subject",
				"From:$from",
				"To:$to",
				"Content-Type:text/html; charset=utf8"
			)) . "\n\n$message\n.", ''),
			array('QUIT', '221')
		);
		foreach ($commandList as $row) {
			list($command, $code) = $row;
			
			$msg[]		= $command;
			fputs($fp, $command . "\n");
			$response	= fgets($fp, 512);
			$msg[]		= $response;
			
			if (!preg_match("/^$code/", $response)) {
				break;
			}
		}
		
		fclose($fp);
	}
}


/**
 * Example 1
|----------------------------------------------------------------------------------------|
|                                                                                        |
| Data provided at this site are provisional and preliminary in nature and are not       |
| intended for use by the general public. They are automatically generated by remote     |
| equipment that may not be under Alberta Government control and have not been reviewed  |
| or edited for accuracy. These data may be subject to significant change when manually  |
| reviewed and corrected. Please exercise caution and carefully consider the provisional |
| nature of the information provided.                                                    |
|                                                                                        |
| The Government of Alberta assumes no responsibility for the accuracy or completeness   |
| of these data and any use of them is entirely at your own risk.                        |
|                                                                                        |
|----------------------------------------------------------------------------------------|

             Alberta Environment                          Page: 1
         REAL TIME HYDROMETRIC REPORT    Last Updated: 2009-11-14
                                                 Time:   20:19:12
RBIRALIC : Birch River below Alice Creek
|=============|======================|=============|=============|
| Station     | Data and Time        | Water Level |    Flow     |
|             | (Mountain Standard)  |     (m)     |   (m3/s)    |
|=============|======================|=============|=============|
  RBIRALIC      2009-11-12 08:30:00          4.489      -999.00

 */
	
/**
 * @date 2012-01-26
 * Example 2
|----------------------------------------------------------------------------------------|
|                                                                                        |
| Data provided at this site are provisional and preliminary in nature and are not       |
| intended for use by the general public. They are automatically generated by remote     |
| equipment that may not be under Alberta Government control and have not been reviewed  |
| or edited for accuracy. These data may be subject to significant change when manually  |
| reviewed and corrected. Please exercise caution and carefully consider the provisional |
| nature of the information provided.                                                    |
|                                                                                        |
| The Government of Alberta assumes no responsibility for the accuracy or completeness   |
| of these data and any use of them is entirely at your own risk.                        |
|                                                                                        |
|----------------------------------------------------------------------------------------|

Government of Alberta Water Quality Data

Station:,Chinchaga River near High Level
Starting at:,2012-01-21 00:00:00 MST
Ending at:,2012-01-25 04:16:07 MST

Station No.,Date & Time in MST,Water Level
,,(m)
07OC001,2012-01-21 00:00:00,0.734
07OC001,2012-01-21 01:00:00,0.733
07OC001,2012-01-21 02:00:00,0.732
07OC001,2012-01-21 03:00:00,0.730
07OC001,2012-01-21 04:00:00,0.730
07OC001,2012-01-24 22:00:00,0.705
07OC001,2012-01-24 23:00:00,0.705
07OC001,2012-01-25 00:00:00,0.706
07OC001,2012-01-25 01:00:00,0.706

** End of report **
*/