<?php

namespace spidgorny\migrate\tests\units;

require_once __DIR__.'/../vendor/autoload.php';
include_once __DIR__.'/../bootstrap.php';

use \mageekguy\atoum;
use spidgorny\migrate;

class Repo extends atoum\test
{

	var $mock = [
		"path" => ".",
		"hash" => "d5f1a238a4b6+",
		"nr" => "117+",
		"tag" => "tip",
		"message" => "- FIX EditConsoleFields",
		"date" => "2016-06-07"
	];

	public function test_construct()
	{
		//$repo = new Repo('some/path');
//		echo $repo->path(), BR;
		$this
			->given($testedInstance = new migrate\Repo('some/path'))
			->string($testedInstance->path())
			->isEqualTo('some/path');
	}

	public function test_toString()
	{
		$this
			->given(
				$testedInstance = migrate\Repo::decode($this->mock))
			->string($testedInstance->__toString())
			->isEqualTo('d5f1a238a4b6+	117+	tip	2016-06-07	.                                  	"- FIX EditConsoleFields"');
	}

	public function test_nr()
	{
		$this
			->given(
				$testedInstance = migrate\Repo::decode($this->mock))
			->integer($testedInstance->nr())
			->isEqualTo(117);
	}

	public function test_getHash()
	{
		$this
			->given(
				$testedInstance = migrate\Repo::decode($this->mock))
			->string($testedInstance->getHash())
			->isEqualTo('d5f1a238a4b6');
	}

}
