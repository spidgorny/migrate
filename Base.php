<?php

class Base {

	/**
	 * @var Repo[]
	 */
	var $repos;

	function run() {
		$cmd = ifsetor($_SERVER['argv'][1], 'index');
		$res = array_slice($_SERVER['argv'], 2);
		//echo $cmd, BR;
		call_user_func_array([$this, $cmd], $res);
	}

	function help() {
		$rc = new ReflectionClass($this);
		foreach ($rc->getMethods() as $method) {
			$comment = $method->getDocComment();
			if ($comment && !contains($comment, '@internal')) {
				$commentLines = trimExplode("\n", $comment);
				$commentAbout = $commentLines[1];
				echo $this->twoTabs($method->getName()), $commentAbout, BR;
			}
		}
	}

	function twoTabs($text) {
		if (strlen($text) < 8) {
			$text .= TAB;
		}
		$text .= TAB;
		return $text;
	}

	/**
	 * Will return output
	 * @param $cmd
	 * @return mixed
	 */
	function exec($cmd) {
		echo '> ', $cmd, BR;
		exec($cmd, $output);
		return $output;
	}

	/**
	 * Will echo output to STDOUT
	 * @param $cmd
	 */
	function system($cmd) {
		echo '> ', $cmd, BR;
		system($cmd);
	}

	/**
	 * @return Repo
	 */
	function getMain() {
		return $this->repos['.'];
	}


}
