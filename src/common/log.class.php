<?php

class Log {

	private $fp;

	public function __construct($log_file) {
		if(!is_dir(dirname($log_file))) { 
			mkdir(dirname($log_file), 0775, true);
		}

		$this->fp = fopen($log_file, 'a');
		if($this->fp === FALSE) {
			throw new Exception('Cannot open logfile '. $log_file);
		}
	}

	public function log($line) {

		$line = date('[r] ').trim($line)."\n";

		if(fwrite($this->fp, $line) === FALSE) {
			throw new Exception('Cannot write to logfile: '.$line);
		}
	}

}
