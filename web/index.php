<?php
	function api_call($action, $params = array()) {
		$url = 'http://10.4.0.2/api.php?auth_token=5a913b20f4d6fa8245860aa8f47ab3e5&action='. urlencode($action) .'&'. http_build_query($params);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$ret = curl_exec($ch);
		$info = curl_getinfo($ch);
		if($info['http_code'] != 200) {
			die('Foutcode: '. $info['http_code'] .': '. $ret);
		}
		if(!$data = json_decode($ret, true)) {
			die('Fout: Decode error');
		}
		return $data;
	}
	if(isset($_POST['activeer'])) {
		$data = api_call('enableActiveTill', array('timestamp' => time() + 3600));
		if(!$data['success']) {
			$error = 'Geen success.';
		} elseif(!$data['affected']) {
			$error = 'Was ik al van plan.';
		} else {
			$msg = 'De verwarming zal nu aanblijven tot '. date('H:i', $data['till']);
		}
	}
	$data = api_call('getTemperatures');
?>
<html>
	<head>
		<title>Thermostaat botenhuis</title>
	</head>
	<body>
<?php
	if($error) {
		echo '<div style="color: red">'. htmlentities($error) .'</div>';
	}
	if($msg) {
		echo '<div style="color: green">'. htmlentities($msg) .'</div>';
	}
?>
		<center>
		<form method="POST">
			Willen we meer of minder warmte?<br>
			<input type="submit" value="Minder! Minder!" disabled>
			<input type="submit" value="Meer! Meer!" name="activeer">
		</form>
		<img src="graph.php">
		</center>
	</body>
</html>
