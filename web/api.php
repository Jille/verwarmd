<?php
	if(!isset($_GET['auth_token'])) {
		fail(403, "No auth_token given");
	}
	if($_GET['auth_token'] != '5a913b20f4d6fa8245860aa8f47ab3e5') {
		fail(403, "Invalid auth_token given");
	}
	if(!isset($_GET['action'])) {
		fail(400, "No action given");
	}
	switch($_GET['action']) {
		case 'getTemperatures':
			$line = shell_exec('tail -1 /var/log/ekroll/temp.log');
			if(!preg_match('/^(\d+) (\d+\.\d+) (\d+) ([01])$/', $line, $m)) {
				fail(500, "Internal server error (error parsing temp.log)");
			}
			$day = intval(file_get_contents('/var/log/ekroll/day.dat'));
			$night = intval(file_get_contents('/var/log/ekroll/night.dat'));
			echo json_encode(array('success' => true, 'current' => $m[2], 'aspired' => $m[3], 'day' => $day, 'night' => $night));
			exit;
		case 'setDayTemperature':
		case 'setNightTemperature':
		case 'enableDayTill':
		case 'enableNight':
		case 'getDayTill':
			$sock = stream_socket_client('udp://127.0.0.1:7397', $errno, $errstr);
			if(!$sock) {
				fail(500, "Internal server error (ctm: ". $errstr .")");
			}
			switch($_GET['action']) {
				case 'setDayTemperature':
				case 'setNightTemperature':
					if(!isset($_GET['temperature']) && !ctype_digit($_GET['temperature'])) {
						fail(400, "Invalid temperature given");
					}
					if($_GET['temperature'] < 1 || $_GET['temperature'] > 40) {
						fail(400, "Param temperature out of range");
					}
					fwrite($sock, "ekroll:". $_GET['action'] .":". $_GET['temperature'] .".\n");
					break;
				case 'enableDayTill':
					if(!isset($_GET['timestamp']) && !ctype_digit($_GET['timestamp'])) {
						fail(400, "Invalid timestamp given");
					}
					if($_GET['timestamp'] < time() || $_GET['timestamp'] > time() + 86400) {
						fail(400, "Param timestamp out of range");
					}
					fwrite($sock, "ekroll:enableDayTill:". $_GET['timestamp'] .".\n");
					break;
				case 'enableNight':
					fwrite($sock, "ekroll:enableNight.\n");
					break;
				case 'getDayTill':
					fwrite($sock, "ekroll:getDayTill.\n");
					$ret = fread($sock, 512);
					echo json_encode(array('success' => true, 'till' => json_decode($ret)));
					exit;
			}
			$ret = fread($sock, 512);
			echo json_encode(array('success' => ($ret == 'OK')));
			exit;
		default:
			fail(400, "Invalid action given (getTemperatures, setDayTemperature, setNightTemperature, enableDayTill, enableNight)");
	}

	function fail($code, $str) {
		http_response_code($code);
		echo json_encode(array('success' => false, 'error' => $str));
		exit;
	}
?>
