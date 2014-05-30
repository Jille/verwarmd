<?php

$_heaterd_stub_state = false;

function heaterd_get() {
	global $_heaterd_stub_state;
	return $_heaterd_stub_state;
}

function heaterd_on() {
	global $_heaterd_stub_state;
	$_heaterd_stub_state = true;
}

function heaterd_off() {
	global $_heaterd_stub_state;
	$_heaterd_stub_state = false;
}