<?php

define('HEATERD_HOST', 'localhost');
define('HEATERD_PORT', '7396');

function heaterd_get() {

	$fp = fsockopen(HEATERD_HOST, HEATERD_PORT);
	
	if($fp === false) {
		throw new Exception('Cannot read from tempd ('.HEATERD_HOST.':'.HEATERD_PORT.')');
	}

	fwrite($fp, "get\n");

	return fread($fp);
}

function heaterd_on() {

	$fp = fsockopen(HEATERD_HOST, HEATERD_PORT);
	
	if($fp === false) {
		throw new Exception('Cannot connect to Heaterd ('.HEATERD_HOST.':'.HEATERD_PORT.')');
	}

	fwrite($fp, "on\n");

	return true;
}

function heaterd_off() {

	$fp = fsockopen(HEATERD_HOST, HEATERD_PORT);
	
	if($fp === false) {
		throw new Exception('Cannot connect to Heaterd ('.HEATERD_HOST.':'.HEATERD_PORT.')');
	}

	fwrite($fp, "off\n");

	return true;

}