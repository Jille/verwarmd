<?php

function get_external_temp($log) {
	$url = "http://api.openweathermap.org/data/2.5/weather?lat=51.808739&lon=5.815926";


	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 2);

	$output = curl_exec($ch);
	$info = curl_getinfo($ch);
	curl_close($ch);

	if($output === false) {
		$log->log(curl_error($ch));

		return false;
	}



	if($info['http_code'] !== 200) {
		$log->log("Error HTTP ".$info['http_code']);
		return false;
	}


	$json = json_decode($output, true);

	if($json === null) {
		$log->log("Invalid json in response");
		return false;
	}

	if($json['main'] === null || $json['main']['temp'] === null) {
		$log->log("Unknown json in response");
		return false;
	}

	return floatval($json['main']['temp']) - 273.15;
}

?>