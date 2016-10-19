<?php

class Base {

	/**
	 * @var Repo[]
	 */
	var $repos;

	protected $verbose;

	function setVerbose($v) {
		$this->verbose = $v;
	}

	/**
	 * @param $name
	 * @return Repo
	 * @throws Exception
	 */
	function getRepoByName($name) {
		$candidates = [];
		foreach ($this->repos as $r) {
			if (contains($r->path, $name)) {
				$candidates[] = $r;
			}
		}
		//debug($r->path, $name, $candidates);

		if (sizeof($candidates) == 1) {
			return first($candidates);
		} else {
			throw new Exception(sizeof($candidates).' matching repos for ['.$name.'] found');
		}
	}

	function help() {
		$rc = new ReflectionClass($this);
		foreach ($rc->getMethods() as $method) {
			$comment = $method->getDocComment();
			if ($comment && !contains($comment, '@internal')) {
				$commentLines = trimExplode("\n", $comment);
				$commentAbout = $commentLines[1];
				$commentAbout = trim($commentAbout, " \t\n\r\0\x0B*");
				if ($commentAbout[0] != '@') {
					echo $this->twoTabs($method->getName()),
					$commentAbout, BR;
				}
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
	 * @internal
	 * @param $cmd
	 * @return mixed
	 */
	function exec($cmd) {
		if ($this->verbose) {
			echo '> ', $cmd, BR;
		}
		exec($cmd, $output);
		return $output;
	}

	/**
	 * Will echo output to STDOUT
	 * @param $cmd
	 * @throws Exception
	 */
	function system($cmd) {
		if ($this->verbose) {
			echo '> ', $cmd, BR;
		}
		system($cmd, $exit);
		if ($exit) {
			throw new Exception('Command filed. Code: '.$exit.BR.$cmd);
		}
	}

	/**
	 * @return Repo
	 */
	function getMain() {
		return $this->repos['.'];
	}

	/**
	 * Just shows what is stored in VERSION.json now
	 */
	function dump() {
		foreach ($this->repos as $repo) {
			echo $repo, BR;
		}
	}

	/**
	 * Runs "hg id" for each repository in VERSION.json. This will replace the VERSION.json
	 */
	function check() {
		/** @var Repo $repo */
		foreach ($this->repos as $repo) {
			echo 'Checking ', $repo->path(), BR;
			$repo->check();
		}
		$this->dump();
	}

	function checkOnce() {
		static $checked = false;
		if (!$checked) {
			$this->check();
			$checked = true;
		}
	}

}
