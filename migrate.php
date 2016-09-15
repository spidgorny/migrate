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

class Migrate extends Base {

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

	function index()
	{
		$this->dump();
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
	 * Runs "hg id" for each repository in VERSION.json. This will replace the VERSION.json
	 */
	function check() {
		/** @var Repo $repo */
		foreach ($this->repos as $repo) {
			$repo->check();
		}
		$this->index();
	}

	function checkOnce() {
		static $checked = false;
		if (!$checked) {
			$this->check();
			$checked = true;
		}
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

	function getPaths() {
		$cmd = 'hg path';
		echo '> ', $cmd, BR;
		exec($cmd, $paths);
		echo implode(BR, $paths), BR;
		$remoteOrigin = [];
		foreach ($paths as $remote) {
			$parts = trimExplode('=', $remote);
			$remoteOrigin[$parts[0]] = $parts[1];
		}
//		debug($remoteOrigin);
		return $remoteOrigin;
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

	/**
	 * Will ssh to the live server and create subfolder for current version.
	 */
	function mkdir() {
		$this->checkOnce();
		$nr = $this->getMain()->nr();

		$remoteCmd = 'cd '.$this->deployPath.' && mkdir v'.$nr;
		$this->ssh_exec($remoteCmd);
	}

	function getVersionPath() {
		$deployPath = $this->deployPath . '/v'.$this->getMain()->nr();
		return $deployPath;
	}

	/**
	 * Will connect to the live server and ls -l.
	 * @param string $path
	 */
	function rls($path = '') {
		$this->checkOnce();
		$deployPath = $this->deployPath . '/v'.$this->getMain()->nr();
		$remoteCmd = 'cd '.$deployPath.' && ls -l '.$path;
		$this->ssh_exec($remoteCmd);
	}

	function rexists() {
		$this->checkOnce();
		$deployPath = $this->deployPath . '/v'.$this->getMain()->nr();
		$remoteCmd = 'cd '.$deployPath.' && ls -l';
		$files = $this->ssh_get($remoteCmd);
		if (sizeof($files) > 5) {	// error message is short
			return true;
		}
		return false;
	}

	/**
	 * Will connect to the live server and clone current repository.
	 */
	function rclone() {
		$this->checkOnce();
		$deployPath = $this->deployPath . '/v'.$this->getMain()->nr();
		$paths = $this->getPaths();
		$default = $paths['default'];
		$remoteCmd = 'cd '.$deployPath.' && hg clone '.$default .' .';
		$this->ssh_exec($remoteCmd);
	}

	/**
	 * Will run hg status remotely
	 */
	function rstatus() {
		$this->checkOnce();
		$deployPath = $this->deployPath . '/v'.$this->getMain()->nr();
		$remoteCmd = 'cd '.$deployPath.' && hg status';
		$this->ssh_exec($remoteCmd);
	}

	/**
	 * Will pull on the server
	 */
	function rpull() {
		$this->checkOnce();
		$deployPath = $this->deployPath . '/v'.$this->getMain()->nr();
		$remoteCmd = 'cd '.$deployPath.' && hg pull';
		$this->ssh_exec($remoteCmd);
	}

	/**
	 * Will update only the main project on the server. Not recommended. Use rinstall instead.
	 */
	function rupdate() {
		$this->checkOnce();
		$deployPath = $this->getVersionPath();
		$remoteCmd = 'cd '.$deployPath.' && hg update';
		$this->ssh_exec($remoteCmd);
	}

	/**
	 * Will update to the exact versions from VERSION.json on the server. All repositories one-by-one. Make sure to run rpull first.
	 */
	function rinstall() {
		$versionPath = $this->getVersionPath();
		/** @var Repo $repo */
		foreach ($this->repos as $repo) {
			echo BR, '## ', $repo->path, BR;
			$deployPath = $versionPath . '/' . $repo->path();
			$cmd = 'cd '.$deployPath.' && hg update -r '.$repo->getHash();
			$this->ssh_exec($cmd);
		}
	}

	/**
	 * Will run composer remotely
	 */
	function rcomposer() {
		$this->checkOnce();
		$deployPath = $this->deployPath . '/v'.$this->getMain()->nr();
		$remoteCmd = 'cd '.$deployPath.' && '.$this->composerCommand.' install --ignore-platform-reqs';
		$this->ssh_exec($remoteCmd);
	}

	/**
	 * Will make a new folder and clone, compose into it or update existing
	 */
	function rdeploy() {
		$exists = $this->rexists();
		if ($exists) {
			$this->rpull();
			$this->rinstall();
			$this->rcomposer();
		} else {
			$this->mkdir();
			$this->rclone();
			$this->rcomposer();
		}
		$this->rstatus();
		$nr = $this->getMain()->nr();
		$this->exec('start http://'.$this->liveServer.'/v'.$nr);
	}

	/**
	 * Trying to rcp vendor folder. Not working since rcp not using id_rsa?
	 */
	function rvendor() {
		$userName = $_SERVER['USERNAME'];
		$home = ifsetor($_SERVER['HOMEDRIVE']) .
			cap($_SERVER['HOMEPATH']);
		$publicKeyFile = $home . '.ssh/id_rsa';
		$remotePath = $this->deployPath . '/v'.$this->getMain()->nr().'/';
		$this->system('scp -r vendor '.
			$userName.'@'.$this->liveServer.':'.$remotePath.
			' -i '.$publicKeyFile);
	}

}

$n = new InitNADLIB();
$n->init();

if (!defined('TAB')) {
	define('TAB', "\t");
}

$id = new Migrate();
$id->run();
