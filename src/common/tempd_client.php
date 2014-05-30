<?php

define('TEMPD_HOST', 'localhost');
define('TEMPD_PORT', '7396');

function tempd_get_temperature() {
	$fp = fsockopen(TEMPD_HOST, TEMPD_PORT);
	
	if($fp === false) {
		throw new Exception('Cannot read from tempd ('.TEMPD_HOST.':'.TEMPD_PORT.')');
	}

	return floatval(fread($fp, 10));
}
