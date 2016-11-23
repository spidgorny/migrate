<?php

define('DEVELOPMENT', true);

/**
 * Created by PhpStorm.
 * User: DEPIDSVY
 * Date: 23.11.2016
 * Time: 11:45
 */
class ArrayFunctionsTest extends PHPUnit_Framework_TestCase {

	function test_unique_multidim_array_thru() {
		$fixture = [
			'a' => 'b',
			'c' => 'b', // del
			'e' => [
				'a' => 'b',
				'b' => 'b',	// del
			],
		];
		$unique = unique_multidim_array_thru($fixture);
//		debug($unique);
		$this->assertEquals([
			'a' => 'b',
			'e' => [
				'a' => 'b',
			]
		], $unique);
	}

}
