<?php

if (file_exists('vendor/spidgorny/nadlib/init.php')) {
	require_once 'vendor/spidgorny/nadlib/init.php';
}
if (file_exists('nadlib/init.php')) {
	require_once 'nadlib/init.php';
}
if (file_exists('lib/nadlib/init.php')) {
	require_once 'lib/nadlib/init.php';
}

require_once __DIR__.'/Repo.php';
require_once __DIR__.'/Base.php';
require_once __DIR__.'/Local.php';
require_once __DIR__.'/Mercurial.php';
require_once __DIR__.'/Remote.php';
require_once __DIR__.'/MigrateClass.php';

if (class_exists('InitNADLIB', false)) {
	$n = new InitNADLIB();
	$n->init();
}

if (!defined('BR')) {
	define('BR', "\n");
}

if (!defined('TAB')) {
	define('TAB', "\t");
}

if (!function_exists('ifsetor')) {
	function ifsetor(&$variable, $default = null) {
		if (isset($variable)) {
			$tmp = $variable;
		} else {
			$tmp = $default;
		}
		return $tmp;
	}
}

if (!function_exists('first')) {
	function first(array $array) {
		reset($array);
		return current($array);
	}
}

if (!function_exists('cap')) {
	function cap($string, $with = '/') {
		return $string[strlen($string)-1] == $with
			? $string
			: $string . $with;
	}
}

$id = new Migrate();
$id->run();
