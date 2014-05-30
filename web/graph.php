<?php
	$timespan = 24*3600;
	$width = 600;
	$height = 200;
	$now = time();

	$xoff = 40;
	$yoff = 0;

	assert($timespan % $width == 0);

	$temps = array();
	$heater = array();
	$aspired = array();
	fetch_data();

/*
	var_dump($temps);
	var_dump($heater);
	var_dump($aspired);
	exit;
*/

	$img = imagecreatetruecolor($width + $xoff + 20, $height + $yoff + 40);
	$white = imagecolorallocate($img, 255, 255, 255);
	$black = imagecolorallocate($img, 0, 0, 0);
	$gray = imagecolorallocate($img, 200, 200, 200);
	$red = imagecolorallocate($img, 255, 0, 0);
	$green = imagecolorallocate($img, 0, 200, 0);
	$blue = imagecolorallocate($img, 0, 0, 255);

	imagefill($img, 0, 0, $white);
	imageline($img, $xoff, $yoff, $xoff, 40 + $height + $yoff, $black);
	imageline($img, 0, $height + $yoff, $width + $xoff + 20, $height + $yoff, $black);

	foreach(array(5, 10, 15, 20, 25, 30, 35, 40, 45) as $t) {
		$p = $height - $t*4;
		imagestring($img, 3, 10, $p - imagefontheight(3)/2, $t, $black);
		imageline($img, 40+1, $p, $width + $xoff + 20, $p, $gray);
	}

	foreach(range(0, $timespan, $timespan / 12) as $t) {
		$p = round($xoff + $t * $width / $timespan)-1;
		if($p < $xoff) {
			continue;
		}
		$str = date('H:i', $now - $timespan + $t);
		imageline($img, $p, $height - 3, $p, $height + 3, $black);
		imagestring($img, 3, $p - round(imagefontwidth(3)*2.5), $height + 20, $str, $black);
	}

	$tValues = array();
	foreach($temps as $ts => $temp) {
		$tValues[round(($ts - $now + $timespan) * $width / $timespan)][] = round($temp * 4);
	}
	$aValues = array();
	foreach($aspired as $ts => $temp) {
		$aValues[round(($ts - $now + $timespan) * $width / $timespan)][] = round($temp * 4);
	}
	$hValues = array();
	foreach($heater as $ts => $bool) {
		$hValues[round(($ts - $now + $timespan) * $width / $timespan)][] = $bool ? 1 : 0;
	}

	foreach($tValues as $x => $y) {
		imagesetpixel($img, $xoff + $x, $height - (array_sum($y) / count($y)), $red);
		imagesetpixel($img, $xoff + $x+1, $height - (array_sum($y) / count($y)), $red);
	}
	foreach($aValues as $x => $y) {
		imagesetpixel($img, $xoff + $x, $height - (array_sum($y) / count($y)), $green);
		imagesetpixel($img, $xoff + $x+1, $height - (array_sum($y) / count($y)), $green);
	}
	foreach($hValues as $x => $y) {
		if((array_sum($y) / count($y)) >= 0.5) {
			imagesetpixel($img, $xoff + $x, $height, $green);
			imagesetpixel($img, $xoff + $x + 1, $height, $green);
		}
	}

	header('Content-Type: image/png');
	imagepng($img);

	function fetch_data() {
		global $temps, $heater, $aspired;
		$lines = parse_stamped_file('/var/log/ekroll/temp.log');
		foreach($lines as $m) {
			$temps[$m[1]] = $m[2];
			$aspired[$m[1]] = $m[3];
			$heater[$m[1]] = $m[4];
		}

/*
		$lines = parse_stamped_file('/var/log/ekroll/actions.log');
		foreach($lines as $m) {
			switch(substr($m[2], 0, 6)) {
				case 'aspire':
					$aspired[$m[1]] = substr($m[2], 7);
					break;
				case 'heater':
					$heaterToggles[$m[1]] = (substr($m[2], 7) == 'on');
					break;
			}
		}
*/
	}

	function parse_stamped_file($fn) {
		global $timespan, $now;
		$from = $now - $timespan;

		# echo "From: ". $from .' '. date('r', $from) ."\n";

		$fh = fopen($fn, 'r');
		fseek($fh, 0, SEEK_END);
		do {
			if(fseek($fh, -1000, SEEK_CUR) == -1) {
				# echo "fseek failed.\n";
				fseek($fh, 0, SEEK_SET);
				break;
			}
			fgets($fh);
			$m = read_stamped_line($fh);
			# echo "Found: ". $m[1] .' '. date('r', $m[1]) ."\n";
		} while($m[1] > $from);
		# echo "Better than from (". $from .")\n";
		do {
			$m = read_stamped_line($fh);
		} while($m && $m[1] < $from);
		# echo "First line found: ". $m[1] ."\n";
		$lines = array($m);
		while(!feof($fh)) {
			$lines[] = read_stamped_line($fh);
		}
		array_pop($lines);
		fclose($fh);
		return $lines;
	}

	function read_stamped_line($fh) {
		$line = fgets($fh);
		if(!$line) {
			return false;
		}
		if(!preg_match('/^(\d+) (-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?) ([01])$/', $line, $m)) {
			return false;
		}
		return $m;
	}
?>
