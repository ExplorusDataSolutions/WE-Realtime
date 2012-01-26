<?php
error_reporting(E_ALL^E_WARNING);

/**
 * Override some consts of ML_.*
 */
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('ML_HOME') or define('ML_HOME', realpath($_SERVER['DOCUMENT_ROOT']) . DS . 'awp');

//define('EMAIL_REPORT_TO',	'spencer.cox@tesera.com,mlhch@163.com');
define('EMAIL_REPORT_TO',	'malhch@gmail.com');


global $cfg;
$cfg['dbs']['realtime-tool'] = array(
	'username' => 'awp',
	'password' => 'WK4SvY6fRjFYbRAc',
	'database' => 'awp'
);
$cfg['dbs']['realtime-data'] = array(
	'username' => 'awp',
	'password' => 'WK4SvY6fRjFYbRAc',
	'database' => 'awp_b',
);
$cfg['dbs']['geometry'] = array(
	'driver' => 'PostgreSQL',
	'username' => 'postgres',
	'password' => 'postgreSpostgreS',
	'database' => 'postgis'
);
$cfg['urls'] = array(
	'basins' => 'http://www.environment.alberta.ca/apps/basins/Default.aspx',
	'basin_info_types' => 'http://www.environment.alberta.ca/apps/basins/Default.aspx',
	'stations' => 'http://www.environment.alberta.ca/apps/basins/Map.aspx',
	'realtime' => 'http://www.environment.alberta.ca/apps/basins/DisplayData.aspx',
	'realtime_text' => 'http://www.environment.alberta.ca',
);
$cfg['realtime_status'] = array(
	'' => '?',
	'+' => '<span class="realtime-added">&nbsp;</span>',
	'-' => '<span class="realtime-deleted">&nbsp;</span>',
	'*' => '<span class="realtime-modified">&nbsp;</span>',
	'=' => '<span class="realtime-ok">&nbsp;</span>',
	'$' => '<span class="realtime-ok">&nbsp;</span>'
);


if (!function_exists('pre')):
function pre($var, $exit = false) {
	static $times = 0;

	$s[] = "<xmp>" . $times++ . ' : ' . gettype($var) . (is_string($var) ? "(" . strlen($var) . ")" : "") ."\n";
	$s[] = print_r($var, 1);
	$s[] = "\n";
	if ($exit) {
		$trace = debug_backtrace();
		foreach ($trace as $t) {
			$file = @$t['file'];
			$line = @$t['line'];
			$s[] = "#<b>$line</b> $t[function]() $file\n";
		}
	}
	$s[] = "</xmp>";
	echo join('', $s);

	if ($exit) {
		die('<hr color="red" />');
	}
}
endif;
?>