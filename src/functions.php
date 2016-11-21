<?php

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

if (!function_exists('contains')) {
	function contains($string, $term) {
		return strpos($string, $term) >= -1;
	}
}

if (!function_exists('trimExplode')) {
	/**
	 * Does string splitting with cleanup.
	 * @param $sep string
	 * @param $str string
	 * @param null $max
	 * @return array
	 */
	function trimExplode($sep, $str, $max = NULL) {
		if (is_object($str)) {
			$is_string = method_exists($str, '__toString');
		} else {
			$is_string = is_string($str);
		}
		if (!$is_string) {
			debug_pre_print_backtrace();
		}
		if ($max) {
			$parts = explode($sep, $str, $max); // checked by isset so NULL makes it 0
		} else {
			$parts = explode($sep, $str);
		}
		$parts = array_map('trim', $parts);
		$parts = array_filter($parts);
		$parts = array_values($parts);
		return $parts;
	}
}

