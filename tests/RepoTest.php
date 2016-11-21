<?php

namespace spidgorny\migrate\tests\units;

require_once __DIR__.'/../vendor/autoload.php';

include_once __DIR__.'/../src/Repo.php';

use \mageekguy\atoum;
use spidgorny\migrate;

class Repo extends atoum\test
{

	public function testConstruct()
	{
		//$repo = new Repo('some/path');
//		echo $repo->path(), BR;
		$this
			->given($testedInstance = new migrate\Repo('some/path'))
			->string($testedInstance->path())
			->isEqualTo('some/path');
	}

	public function testConstruct()
	{
		//$repo = new Repo('some/path');
//		echo $repo->path(), BR;
		$this
			->given($testedInstance = new migrate\Repo('some/path'))
			->string($testedInstance->path())
			->isEqualTo('some/path');
	}

}
