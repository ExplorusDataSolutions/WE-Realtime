<?php
if (!empty($_POST['data'])) {
	$data = $_POST['data'];
	if (get_magic_quotes_gpc()) {
		$data = stripslashes($data);
	}
	
	
	$ch = curl_init ();
	curl_setopt ( $ch, CURLOPT_URL, 'http://localhost/ZF2/WE-Realtime/' );
	curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, TRUE );
	curl_setopt ( $ch, CURLOPT_POST, TRUE );
	//添加变量
	curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
	$response = curl_exec ( $ch );
	curl_close ( $ch );
} else {
	$data = '{
	"request": "getStationList"
}';
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Post data to test</title>
</head>
<body>
	<div id="header">
		<h1>Post data to test</h1>
	</div>
	<div id="nav"></div>
	<div id=content>
		<p>Data:</p>
		<form action="" method="post">
			<textarea name="data" rows="10" cols="80"><?php echo $data?></textarea><br />
			<input type="submit" value="Submit" />
		</form>
		<textarea cols="120" rows="25"><?php echo htmlspecialchars($response)?></textarea>
	</div>
	<div id="footer"></div>
</body>
</html>