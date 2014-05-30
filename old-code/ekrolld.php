<?php
	assert_options(ASSERT_BAIL, true);
	function exception_error_handler($errno, $errstr, $errfile, $errline ) {
		throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
	}
	set_error_handler("exception_error_handler");

	define('GPIO_ON_PORT', 2);
	define('GPIO_OFF_PORT', 0);

	my_exec('gpio mode '. GPIO_ON_PORT .' out');
	my_exec('gpio mode '. GPIO_OFF_PORT .' out');

	$descr = array(
		0 => array('file', '/dev/null', 'r'),
		1 => array('pipe', 'w'),
		2 => array('file', '/var/log/ekroll/pcsensor.log', 'w'),
	);
	$ph = proc_open('./pcsensor -c -l10', $descr, $pipes);

	if(is_readable('/var/log/ekroll/aspired.dat')) {
		$aspire = intval(file_get_contents('/var/log/ekroll/aspired.dat'));
	} else {
		$aspire = 12;
	}
	$aspire = min(30, max(12, $aspire));
	file_put_contents('/var/log/ekroll/aspired.dat', $aspire);

	define('MODE_HAPPY', 1);
	define('MODE_HEATING', 2);
	define('MODE_WAITING', 3);

	$mode = MODE_HAPPY;
	$heaterOn = false;
	$actAt = time() + 60;
	$temp = NULL;
	$prevTemp = NULL;
	$heatTime = 300;
	$heatResult = 3;
	$heatDelay = 900;
	$heaterEnableEffective = false;
	$heaterEnabledAt = NULL;
	$heaterEnabledOn = NULL;
	$heaterDisabledAt = NULL;
	$heaterDisabledOn = NULL;
	$currentHeatingPeak = 0;

	$tlog = fopen('/var/log/ekroll/temp.log', 'a');

	toggle_heater(false);

	$sock = stream_socket_server('udp://127.0.0.1:7396', $errno, $errstr, STREAM_SERVER_BIND);
	while(true) {
		$read = array($pipes[1], $sock);
		$write = NULL;
		$except = NULL;
		$n = stream_select($read, $write, $except, 30);
		assert($n !== false);
		assert($n > 0);
		$now = time();
		if(in_array($sock, $read)) {
			$line = stream_socket_recvfrom($sock, 512, 0, $peer);
			var_dump($peer, $line);
			if(preg_match("/^ekroll:set:(\d+)\.\n\$/", $line, $m)) {
				$aspire = min(26, max(9, $m[1]));
				file_put_contents('/var/log/ekroll/aspired.dat', $aspire);
				$actAt = $now;
				log_entry();
			}
		}
		if(in_array($pipes[1], $read)) {
			$line = fgets($pipes[1], 512);
			assert($line !== false);
			assert($line !== '');
			$n = preg_match('@^\d{4}/\d{2}/\d{2} \d{2}:\d{2}:\d{2} Temperature (-?\d+.\d+)C@', $line, $m);
			assert($n == 1);
			$prevTemp = $temp;
			$temp = $m[1];
			log_entry();

			if($prevTemp === NULL) { // Only after startup
				$prevTemp = $temp;
			}
			$currentHeatingPeak = max($currentHeatingPeak, $temp);

			switch($mode) {
				case MODE_HAPPY:
					if($now >= $actAt) {
						$actAt = $now + 60;
						if($temp < $aspire) {
							$wanted = $aspire + 1 - $temp;
							# $heatTime = ($heatTime + ($heatTime * ($wanted / $heatResult))) / 2;
							$heatTime = 60 * $wanted;
							toggle_heater(true);
							$mode = MODE_HEATING;
						}
					}
					break;
				case MODE_HEATING:
					if($temp > $heaterEnabledOn + 1 && !$heaterEnableEffective) {
						// Take $heatDelay as the avg of the previous heatDelay and the current duration to get one degree warmer
						$heatDelay = ($heatDelay + ($now - $heaterEnabledAt)) / 2;
						$heaterEnableEffective = true;
					}
					if($heaterEnabledAt + $heatTime >= $now) {
						toggle_heater(false);
						$mode = MODE_WAITING;
						// Minstens een minuut niks doen
						$actAt = $now + 60;
					}
					break;
				case MODE_WAITING:
					// TODO: Als we in deze mode zitten en de aspire wordt verhoogd blijven we (mogelijk zinloos) wachten
					if($heaterDisabledOn > $temp + 1) {
						// Het wordt weer kouder, dus de delay van de sensor is voorbij
						$heatResult = ($heatResult + ($currentHeatingPeak - $heaterEnabledOn)) / 2;
						$mode = MODE_HAPPY;
					}
					break;
			}
		}
	}

	toggle_heater(false);
	fclose($tlog);
	proc_terminate($ph);
	proc_close($ph);

	function toggle_heater($enable) {
		global $heaterOn, $temp, $heaterEnabledAt, $heaterEnabledOn, $heaterEnableEffective, $currentHeatingPeak, $heaterDisabledAt, $heaterDisabledOn;
		$heaterOn = $enable;
		if($enable) {
			$heaterEnabledAt = time();
			$heaterEnabledOn = $temp;
			$heaterEnableEffective = false;
			$currentHeatingPeak = $temp;
			my_exec('gpio write '. GPIO_ON_PORT .' 1');
			usleep(100000);
			my_exec('gpio write '. GPIO_ON_PORT .' 0');
		} else {
			$heaterDisabledAt = time();
			$heaterDisabledOn = $temp;
			my_exec('gpio write '. GPIO_OFF_PORT .' 1');
			usleep(100000);
			my_exec('gpio write '. GPIO_OFF_PORT .' 0');
		}
		if($temp !== NULL) {
			// Bij startup geen entry loggen
			log_entry();
		}
	}

	function log_entry() {
		global $tlog, $now, $temp, $aspire, $heaterOn;
		fwrite($tlog, $now .' '. $temp .' '. $aspire .' '. intval($heaterOn) ."\n");
	}

	function my_exec($cmd) {
		$descr = array(
			0 => array('file', '/dev/null', 'r'),
			1 => array('file', '/var/log/ekroll/gpio.log', 'w'),
			2 => array('file', '/var/log/ekroll/gpio.log', 'w'),
		);
		$ph = proc_open($cmd, $descr, $pipes);
		$ret = proc_close($ph);
		var_dump($ret);
		// XXX checken
	}
?>
