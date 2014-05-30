<?php
	assert_options(ASSERT_BAIL, true);
	function exception_error_handler($errno, $errstr, $errfile, $errline ) {
		throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
	}
	set_error_handler("exception_error_handler");

	if(is_readable('/var/log/ekroll/day.dat')) {
		$day = intval(file_get_contents('/var/log/ekroll/day.dat'));
	} else {
		$day = 20;
	}
	file_put_contents('/var/log/ekroll/day.dat', $day);
	if(is_readable('/var/log/ekroll/night.dat')) {
		$night = intval(file_get_contents('/var/log/ekroll/night.dat'));
	} else {
		$night = 12;
	}
	file_put_contents('/var/log/ekroll/night.dat', $night);

	$dayMode = false;
	$dayTill = NULL;

	$cliSock = stream_socket_client('udp://127.0.0.1:7396', $errno, $errstr);
	if(!$cliSock) {
		var_dump($errstr);
		exit(1);
	}

	setAspired($night);

	$sock = stream_socket_server('udp://127.0.0.1:7397', $errno, $errstr, STREAM_SERVER_BIND);
	while(true) {
		$read = array($sock);
		$write = NULL;
		$except = NULL;
		$n = stream_select($read, $write, $except, 30);
		assert($n !== false);
		assert($n >= 0);
		$now = time();
		if(in_array($sock, $read)) {
			$line = stream_socket_recvfrom($sock, 512, 0, $peer);
			var_dump($peer, $line);
			if(preg_match("/^ekroll:setNightTemperature:(\d+)\.\n\$/", $line, $m)) {
				$night = $m[1];
				file_put_contents('/var/log/ekroll/night.dat', $night);
				if(!$dayMode) {
					setAspired($night);
				}
				stream_socket_sendto($sock, 'OK', 0, $peer);
			} elseif(preg_match("/^ekroll:setDayTemperature:(\d+)\.\n\$/", $line, $m)) {
				$day = $m[1];
				file_put_contents('/var/log/ekroll/day.dat', $day);
				if($dayMode) {
					setAspired($day);
				}
				stream_socket_sendto($sock, 'OK', 0, $peer);
			} elseif(preg_match("/^ekroll:enableDayTill:(\d+)\.\n\$/", $line, $m)) {
				$dayMode = true;
				$dayTill = min($now + 86400, max($dayTill, $m[1]));
				setAspired($day);
				stream_socket_sendto($sock, 'OK', 0, $peer);
			} elseif(preg_match("/^ekroll:enableNight\.\n\$/", $line, $m)) {
				$dayMode = false;
				$dayTill = NULL;
				setAspired($night);
				stream_socket_sendto($sock, 'OK', 0, $peer);
			} elseif(preg_match("/^ekroll:getDayTill\.\n\$/", $line, $m)) {
				stream_socket_sendto($sock, json_encode($dayTill), 0, $peer);
			} else {
				stream_socket_sendto($sock, 'ERROR: unknown command', 0, $peer);
			}
		}
		if($dayMode && $now >= $dayTill) {
			$dayMode = false;
			$dayTill = NULL;
			setAspired($night);
		}
	}

	function setAspired($temp) {
		global $cliSock;
		echo "Aspired: ". $temp ."\n";
		fwrite($cliSock, "ekroll:set:". $temp .".\n");
	}
?>
