<?php
	require __DIR__.'/vendor/autoload.php';

	class Log { function log($data) { echo $data ."\n"; } }

	$loop = React\EventLoop\Factory::create();

	$log = new Log('/var/log/ekroll/pcsensor.log');

	$descr = array(
		0 => array('file', '/dev/null', 'r'),
		1 => array('pipe', 'w'),
		2 => array('file', '/var/log/ekroll/pcsensor.log', 'w'),
	);
	$ph = proc_open('./pcsensor -c -l10', $descr, $pipes);
	$pcsensor = new React\Stream\Stream($pipes[1], $loop);
	$pcsensor->on('data', function($data, $stream) {
		global $temp, $log;
		var_dump($data);
		if($data === '') {
			return;
		}
		if(!preg_match('@^\d{4}/\d{2}/\d{2} \d{2}:\d{2}:\d{2} Temperature (-?\d+.\d+)C@', $data, $m)) {
			$log->log('Unparseable data: '. var_export($data, true));
			exit(1);
		}
		$temp = $m[1];
	});
	$pcsensor->on('error', function($exception) {
		global $log;
		$log->log('pcsensor gave this error: '. strval($exception));
		exit(2);
	});
	$pcsensor->on('close', function() {
		global $log;
		$log->log('pcsensor died');
		exit(3);
	});

	$socket = new React\Socket\Server($loop);
	$socket->on('connection', function($conn) {
		global $temp;
		$conn->end($temp);
	});

	$socket->listen(7396);
	$loop->run();
?>
