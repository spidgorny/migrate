<?php

namespace spidgorny\migrate;

class Mercurial extends Base {

	protected $verbose;

	function __construct(array $repos) {
		$this->repos = $repos;
	}

	function setVerbose($v) {
		$this->verbose = $v;
	}

	/**
	 * Will execute TortoiseHG in a specified folder (use .). Partial match works.
	 * @param $what
	 */
	function thg($what = '.') {
		try {
			$repo = $this->getRepoByName($what);
			$repo->thg();
		} catch (\Exception $e) {
			echo $e->getMessage(), BR;
		}
	}

	/**
	 * Shows a changelog of the main project which can be read by humans
	 */
	function changelog() {
		/** @var Repo $main */
		$main = $this->repos['.'];
		$cmd = 'hg log -r '.$main->hash.': --template "{rev}\t{node|short}\t{date|shortdate}\t{date|age}\t{desc|tabindent}\n"';
		if ($this->verbose) {
			echo '> ', $cmd, BR;
		}
		system($cmd);
	}

	/**
	 * Only does "hg pull" without any updating
	 * @param null $what
	 */
	function push($what = NULL) {
		if ($what) {
			$repo = $this->getRepoByName($what);
			$repo->push();
		} else {
			/** @var Repo $repo */
			foreach ($this->repos as $repo) {
				$repo->push();
			}
		}
	}

	/**
	 * Only does "hg pull" without any updating
	 * @param null $what
	 */
	function pull($what = NULL) {
		if ($what) {
			$repo = $this->getRepoByName($what);
			$repo->pull();
		} else {
			/** @var Repo $repo */
			foreach ($this->repos as $repo) {
				$repo->pull();
			}
		}
	}

	/**
	 * Only does "hg update"
	 * @param null $what
	 */
	function update($what = NULL) {
		if ($what) {
			$repo = $this->getRepoByName($what);
			$repo->update();
		} else {
			/** @var Repo $repo */
			foreach ($this->repos as $repo) {
				$repo->update();
			}
		}
	}

	/**
	 * Will commit the VERSION.json file and push
	 */
	function commit() {
		$cmd = 'hg commit -m "- UPDATE VERSION.json"';
		$this->exec($cmd);

		/** @var Repo $repo */
		foreach ($this->repos as $repo) {
			$repo->push();
		}
	}

	function status() {
		/** @var Repo $repo */
		foreach ($this->repos as $repo) {
			$repo->status();
		}
	}

}
