<?php
	function exception_error_handler($errno, $errstr, $errfile, $errline ) {
		throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
	}
	set_error_handler("exception_error_handler");

	require __DIR__.'/vendor/autoload.php';

	class Log { function log($data) { echo $data ."\n"; } }

	define('GPIO_ON_PORT', 2);
	define('GPIO_OFF_PORT', 0);

	function my_exec($cmd) {
		$descr = array(
			0 => array('file', '/dev/null', 'r'),
			1 => array('file', '/var/log/ekroll/gpio.log', 'w'),
			2 => array('file', '/var/log/ekroll/gpio.log', 'w'),
		);
		$ph = proc_open($cmd, $descr, $pipes);
		$status = proc_close($ph);
		var_dump($status);
		if(pcntl_wifexited($status)) {
			if(pcntl_wexitstatus($status) == 0) {
				return true;
			}
			$GLOBALS['log']->log($cmd .' exited with exitcode '. pcntl_wexitstatus($status));
			return false;
		} elseif(pcntl_wifsignaled($status)) {
			$GLOBALS['log']->log($cmd .' was killed by signal '. pcntl_wtermsig($status));
			return false;
		} else {
			$GLOBALS['log']->log($cmd .' died unexpectedly');
			return false;
		}
	}

	function heater_enable() {
		my_exec('gpio write '. GPIO_ON_PORT .' 1');
		usleep(100000);
		my_exec('gpio write '. GPIO_ON_PORT .' 0');
	}

	function heater_disable() {
		my_exec('gpio write '. GPIO_OFF_PORT .' 1');
		usleep(100000);
		my_exec('gpio write '. GPIO_OFF_PORT .' 0');
	}

	my_exec('gpio mode '. GPIO_ON_PORT .' out');
	my_exec('gpio mode '. GPIO_OFF_PORT .' out');

	heater_disable();

	$loop = React\EventLoop\Factory::create();

	$log = new Log('/var/log/ekroll/heaterd.log');

	$autoDisableTimer = NULL;

	$socket = new React\Socket\Server($loop);
	$socket->on('connection', function($conn) {
		$conn->on('data', function($data, $conn) {
			switch(trim($data)) {
				case 'on':
					$GLOBALS['log']->log('heater enable request');
					heater_enable();
					$GLOBALS['autoDisableTimer'] = $GLOBALS['loop']->addTimer(1800, function() {
						$GLOBALS['log']->log('heater disabled due to timeout');
						heater_disable();
						$GLOBALS['autoDisableTimer'] = NULL;
					});
					$conn->end("true\n");
					break;
				case 'off':
					if($GLOBALS['autoDisableTimer']) {
						$GLOBALS['loop']->cancelTimer($GLOBALS['autoDisableTimer']);
						$GLOBALS['autoDisableTimer'] = NULL;
					}
					$GLOBALS['log']->log('heater disable request');
					heater_disable();
					$conn->end("true\n");
					break;
				case 'get':
					$conn->end(json_encode($GLOBALS['autoDisableTimer'] !== NULL) ."\n");
					break;
				default:
					var_dump($data);
					$conn->end("zaad\n");
			}
		});
	});

	$socket->listen(7397);
	$loop->run();
?>
