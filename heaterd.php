<?php
	require __DIR__.'/vendor/autoload.php';

	class Log { function log($data) { echo $data ."\n"; } }

	define('GPIO_ON_PORT', 2);
	define('GPIO_OFF_PORT', 0);

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
			switch($data) {
				case 'on':
					$GLOBALS['log']->log('heater enable request');
					heater_enable();
					$GLOBALS['autoDisableTimer'] = $loop->addTimer(1800, function() {
						$GLOBALS['log']->log('heater disabled due to timeout');
						heater_disable();
						$GLOBALS['autoDisableTimer'] = NULL;
					});
					break;
				case 'off':
					if($GLOBALS['autoDisableTimer']) {
						$loop->cancelTimer($GLOBALS['autoDisableTimer']);
						$GLOBALS['autoDisableTimer'] = NULL;
					}
					$GLOBALS['log']->log('heater disable request');
					heater_disable();
					break;
				case 'get':
					$conn->end(json_encode($GLOBALS['autoDisableTimer'] !== NULL));
					break;
				default:
					var_dump($data);
					$conn->close();
			}
		});
	});

	$socket->listen(7397);
	$loop->run();
?>
