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

class Repo {

	var $path;

	var $hash;

	var $nr;

	var $tag;

	function __construct($folder = NULL)
	{
		$this->path = $folder;
	}

	static function decode($properties) {
		$repo = new static();
		foreach ((array)$properties AS $key => $value) {
			$repo->{$key} = $value;
		}
		return $repo;
	}

	function __toString()
	{
		return implode(TAB, [$this->hash, $this->nr, $this->tag, $this->path]);
	}

	function check() {
		$cmd = 'hg id -int '.$this->path;
		//echo $cmd, BR;
		$line = exec($cmd);
		//echo $line, BR;
		$parts = trimExplode(' ', $line);
		//pre_print_r($parts);
		if (sizeof($parts) == 3) {
			list($this->hash, $this->nr, $this->tag) = $parts;
		} else {
			list($this->hash, $this->nr) = $parts;
		}
	}

	function install() {
		$save = getcwd();
		chdir($this->path);
		$cmd = 'hg pull';
		echo '> ', $cmd, BR;
		system($cmd);

		$hash = str_replace('+', '', $this->hash);
		$cmd = 'hg update -r '.$hash;
		echo '> ', $cmd, BR;
		system($cmd);

		chdir($save);
	}

	function thg() {
		chdir($this->path);
		system('thg');
	}

}

class ID {

	/**
	 * @var Repo[]
	 */
	var $repos;

	function __construct()
	{
		$this->repos = (array)json_decode(@file_get_contents('VERSION.json'));
		foreach ($this->repos as &$row) {
			$row = Repo::decode($row);
		}
	}

	function __destruct()
	{
		file_put_contents('VERSION.json',
			json_encode($this->repos, JSON_PRETTY_PRINT));
	}

	function getRepoByName($name) {
		$candidates = [];
		foreach ($this->repos as $r) {
			if (contains($r->path, $name)) {
				$candidates[] = $r;
			}
		}
		debug($r->path, $name, $candidates);

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
		call_user_func_array([$this, $cmd], $res);
	}

	function index() {
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
	 * Runs "hg id" for each repository in VERSION.json
	 */
	function check() {
		foreach ($this->repos as $repo) {
			$repo->check();
		}
		$this->index();
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
	 * Checks current versions, does install of new versions, composer
	 */
	function golive() {
		$this->check();
		$this->install();
		$this->composer();
		$this->check();
	}

	/**
	 * Shows the default pull/push location for current folder. Allows to compare current and latest version.
	 */
	function info() {
		$this->check();
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
		$url .= '/raw-file/tip/VERSION.json';
		echo $url, BR;
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
		$this->check();
		$remoteOrigin = $this->getPaths();
		$remoteRepo = $this->fetchRemote($remoteOrigin['default']);
		/** @var Repo $mainProject */
		$mainProject = $remoteRepo['.'];
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
	 */
	function pull() {
		$cmd = 'hg pull';
		echo '> ', $cmd, BR;
		system($cmd);
	}

}

$n = new InitNADLIB();
$n->init();

if (!defined('TAB')) {
	define('TAB', "\t");
}

$id = new ID();
$id->run();
