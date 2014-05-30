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
			$current = NULL;
			if($sock = stream_socket_client('tcp://127.0.0.1:7396')) {
				if($line = fgets($sock)) {
					$current = floatval($line);
				}
				fclose($sock);
			}
			$activeTill = intval(file_get_contents('/var/log/ekroll/activeTill.dat'));
			$active = intval(file_get_contents('/var/log/ekroll/active.dat'));
			$inactive = intval(file_get_contents('/var/log/ekroll/inactive.dat'));
			if($activeTill > time()) {
				$aspired = $active;
			} else {
				$aspired = $inactive;
			}
			echo json_encode(array('success' => true, 'current' => $current, 'aspired' => $aspired, 'active' => $active, 'inactive' => $inactive));
			exit;
		case 'setActiveTemperature':
		case 'setInactiveTemperature':
			if(!isset($_GET['temperature']) && !ctype_digit($_GET['temperature'])) {
				fail(400, "Invalid temperature given");
			}
			if($_GET['temperature'] < 1 || $_GET['temperature'] > 40) {
				fail(400, "Param temperature out of range");
			}
			if($_GET['action'] == 'setActiveTemperature') {
				file_put_contents('/var/log/ekroll/active.dat', $_GET['temperature']);
			} else {
				file_put_contents('/var/log/ekroll/inactive.dat', $_GET['temperature']);
			}
			echo json_encode(array('success' => true));
			exit;
		case 'enableInactive':
			file_put_contents('/var/log/ekroll/activeTill.dat', time());
			echo json_encode(array('success' => true, 'affected' => true));
			exit;
		case 'enableActiveTill':
			if(!isset($_GET['timestamp']) && !ctype_digit($_GET['timestamp'])) {
				fail(400, "Invalid timestamp given");
			}
			if($_GET['timestamp'] < time() || $_GET['timestamp'] > time() + 86400) {
				fail(400, "Param timestamp out of range");
			}
			$ts = intval(file_get_contents('/var/log/ekroll/activeTill.dat'));
			$affected = false;
			if($_GET['timestamp'] > $ts) {
				file_put_contents('/var/log/ekroll/activeTill.dat', $_GET['timestamp']);
				$affected = true;
				$ts = $_GET['timestamp'];
			}
			echo json_encode(array('success' => true, 'affected' => $affected, 'till' => $ts));
			exit;
		case 'getActiveTill':
			$ret = intval(file_get_contents('/var/log/ekroll/activeTill.dat'));
			echo json_encode(array('success' => true, 'till' => json_decode($ret)));
			exit;
		default:
			fail(400, "Invalid action given (getTemperatures, setActiveTemperature, setInactiveTemperature, enableActiveTill, enableInactive)");
	}

	function fail($code, $str) {
		http_response_code($code);
		echo json_encode(array('success' => false, 'error' => $str));
		exit;
	}
?>
