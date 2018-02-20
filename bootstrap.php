<?php

require_once __DIR__.'/vendor/autoload.php';

if (file_exists('vendor/spidgorny/nadlib/init.php')) {
	require_once 'vendor/spidgorny/nadlib/init.php';
} elseif (file_exists('nadlib/init.php')) {
	require_once 'nadlib/init.php';
} elseif (file_exists('lib/nadlib/init.php')) {
	require_once 'lib/nadlib/init.php';
} elseif (file_exists('vendor/nadlib/nadlib/init.php')) {
	require_once 'vendor/nadlib/nadlib/init.php';
}

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

