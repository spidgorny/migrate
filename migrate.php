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

require_once __DIR__.'/Base.php';
require_once __DIR__.'/Repo.php';
require_once __DIR__.'/Remote.php';

class Migrate extends Base {

	/**
	 * Server name or IP
	 * @var string
	 */
	var $liveServer = 'no.server.defined.com';

	/**
	 * Live server path with project files
	 * @var
	 */
	var $deployPath = '/srv/www/vhosts/xxx';

	/**
	 * Command to run on the remote server to run composer
	 * @var string
	 */
	var $composerCommand = 'php composer.phar';

	function __construct()
	{
		$json = json_decode(@file_get_contents('VERSION.json'));
		if ($json->liveServer) {
			foreach ($json as $key => $val) {
				$this->$key = $val;
			}
			$this->repos = (array)$this->repos;
		} else {
			$this->repos = $json;
		}
		foreach ($this->repos as &$row) {
			$row = Repo::decode($row);
		}
	}

	function __destruct()
	{
		$json = get_object_vars($this);
		file_put_contents('VERSION.json',
			json_encode($json, JSON_PRETTY_PRINT));
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

	function run() {
		$cmd = ifsetor($_SERVER['argv'][1], 'index');
		$res = array_slice($_SERVER['argv'], 2);
		//echo $cmd, BR;
		$modules = [
			$this,
			new Remote(
				$this->repos,
				$this->liveServer,
				$this->deployPath,
				$this->composerCommand
			),
		];

		$done = false;
		foreach ($modules as $module) {
			$exists = method_exists($module, $cmd);
			if ($exists) {
				call_user_func_array([$module, $cmd], $res);
				$done = true;
			}
		}

		if (!$done) {
			echo 'Command not found: '.$cmd.'('.implode(', ', $res).')', BR;
		}
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
		$this->index();
	}

	/**
	 * Removes specified folder (no default) from VERSION.json file
	 * @param $folder
	 */
	function del($folder) {
		unset($this->repos[$folder]);
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
		$remoteOrigin = $this->getPaths();
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
		$remoteOrigin = $this->getPaths();
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

	/**
	 * Will execute TortoiseHG in a specified folder (use .). Partial match works.
	 * @param $what
	 */
	function thg($what = '.') {
		try {
			$repo = $this->getRepoByName($what);
			$repo->thg();
		} catch (Exception $e) {
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
		echo '> ', $cmd, BR;
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

}

$n = new InitNADLIB();
$n->init();

if (!defined('TAB')) {
	define('TAB', "\t");
}

$id = new Migrate();
$id->run();
