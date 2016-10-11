<?php

class Local extends Base {

	function __construct(array $repos) {
		$this->repos = $repos;
	}

	function index()
	{
		$this->dump();
	}

	/**
	 * Adds specified folder (default .) to the VERSION.json file
	 * @param string $folder
	 */
	function add($folder = '.') {
		if (!isset($this->repos[$folder])) {
			$new = new Repo($folder);
			$new->check();
			$this->repos[$folder] = $new;
		}
		$this->dump();
	}

	/**
	 * Removes specified folder (no default) from VERSION.json file
	 * @param $folder
	 */
	function del($folder) {
		unset($this->repos[$folder]);
		$this->dump();
	}

	/**
	 * Shows both the current VERSION.json and currently installed versions.
	 */
	function compare() {
		/** @var Repo $repo */
		foreach ($this->repos as $repo) {
			echo $repo, BR;
			$r2 = clone $repo;	// so that id() will not replace
			$r2->check();
			echo $r2, BR;
		}
	}

	/**
	 * Runs "hg pull" and "hg update -r xxx" on each repository.
	 * @param null $what
	 */
	function install($what = NULL) {
		if ($what) {
			$repo = $this->getRepoByName($what);
			if ($repo) {
				$repo->install();
			}
		} else {
			/** @var Repo $repo */
			foreach ($this->repos as $repo) {
				echo BR, '## ', $repo->path, BR;
				$repo->install();
			}
		}
	}

	/**
	 * Runs composer install
	 */
	function composer() {
		$cmd = 'composer self-update';
		echo '> ', $cmd, BR;
		system($cmd);

		$cmd = 'composer install --profile --no-plugins';
		echo '> ', $cmd, BR;
		system($cmd);
	}

	/**
	 * Checks current versions, does install of new versions, composer (call on LIVE)
	 */
	function golive() {
//		$this->check();	// should not be called to prevent overwrite
		$this->dump();
		$this->install();
		$this->composer();
		$this->check();
	}

	/**
	 * Shows the default pull/push location for current folder. Allows to compare current and latest version (call on
	 * LIVE)
	 */
	function info() {
		$this->dump();
		$remoteOrigin = $this->getMain()->getPaths();
		$remoteRepo = $this->fetchRemote($remoteOrigin['default']);
		foreach ($remoteRepo as $repo) {
			echo $repo, BR;
		}
	}

	/**
	 * @internal
	 * @param $url
	 * @return Repo[]
	 */
	function fetchRemote($url) {
		$url = cap($url) . 'raw-file/tip/VERSION.json';
		echo 'Downloading from ', $url, BR;
		$json = file_get_contents($url);
//		echo $json;
		$newVersions = (array)json_decode($json);
		foreach ($newVersions as &$row) {
			$row = Repo::decode($row);
		}
		return $newVersions;
	}

	/**
	 * Will fetch the latest version available and update to it. Like install but only for the main folder repo (.)
	 */
	function update() {
		$this->dump();
		$remoteOrigin = $this->getMain()->getPaths();
		$remoteRepo = $this->fetchRemote($remoteOrigin['default']);
		/** @var Repo $mainProject */
		$mainProject = $remoteRepo['.'];
		$current = $this->getMain();
		echo 'Updating from: ', BR;
		echo $current, BR;
		echo 'to: ', BR;
		echo $mainProject, BR;
		$mainProject->install();
	}

}