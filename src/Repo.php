<?php

namespace spidgorny\migrate;

class Repo {

	var $path;

	var $hash;

	var $nr;

	var $tag;

	var $message;

	var $date;

	protected $verbose;

	public $execStatus;

	function __construct($folder = NULL)
	{
		$this->path = $folder;
	}

	function setVerbose($v)
	{
		$this->verbose = $v;
	}

	public function getRevisionNr()
	{
		return $this->nr();
	}

	function exec($cmd, &$lines = [])
	{
		if ($this->verbose) {
			echo '> ', $cmd, BR;
		}
		$line = exec($cmd, $lines, $this->execStatus);
		return $line;
	}

	function system($cmd)
	{
		if ($this->verbose) {
			echo '> ', $cmd, BR;
		}
		system($cmd);
	}

	static function decode($properties)
	{
		$repo = new static();
		foreach ((array)$properties AS $key => $value) {
			$repo->{$key} = $value;
		}
		return $repo;
	}

	function nr()
	{
		return intval($this->nr);
	}

	function getHash()
	{
		$hash = str_replace('+', '', $this->hash);
		return $hash;
	}

	function path()
	{
		$path = str_replace('\\', '/', $this->path);
		return $path;
	}

	function __toString()
	{
		return implode(TAB, [$this->hash, $this->nr, $this->tag,
			$this->date,
			str_pad($this->path, 35, ' '), '"' . $this->message . '"']);
	}

	function check()
	{
		$line = $this->exec('hg id -int ' . $this->path);
		//echo $line, BR;
		$parts = trimExplode(' ', $line);
		//pre_print_r($parts);
		if (sizeof($parts) == 3) {
			list($this->hash, $this->nr, $this->tag) = $parts;
		} else {
			list($this->hash, $this->nr) = $parts;
		}

		$save = getcwd();
		chdir($this->path);

		$cmd = 'hg log -l 1 --template "{date|shortdate}\t{desc}\n"';
		$this->exec($cmd, $lines);
		list($this->date, $this->message) = trimExplode(TAB, first($lines));

		chdir($save);
	}

	function install()
	{
		$save = getcwd();
		$ok = @chdir($this->path);
		if ($ok) {

			system('hg pull');
			system('hg update -r ' . $this->getHash());

			chdir($save);
		} else {
			throw new \InvalidArgumentException('Folder ' . $this->path . ' does not exist. Install aborted.');
		}
	}

	function thg()
	{
		chdir($this->path);
		system('thg');
	}

	function push()
	{
		$save = getcwd();
		chdir($this->path);
		$this->system('hg push');
		chdir($save);
	}

	function pull()
	{
		$save = getcwd();
		chdir($this->path);
		echo '[' . getcwd() . ']', BR;
		$this->system('hg pull');
		chdir($save);
	}

	function getPaths()
	{
		$save = getcwd();
		chdir($this->path);

		$this->exec('hg path', $paths);
		echo implode(BR, $paths), BR;
		$remoteOrigin = [];
		foreach ($paths as $remote) {
			$parts = trimExplode('=', $remote);
			$remoteOrigin[$parts[0]] = $parts[1];
		}
//		debug($remoteOrigin);

		chdir($save);
		return $remoteOrigin;
	}

	function getDefaultPath()
	{
		$paths = $this->getPaths();
		return $paths['default'];
	}

	public function update()
	{
		$save = getcwd();
		chdir($this->path);
		echo '[' . getcwd() . ']', BR;
		$this->system('hg update');
		chdir($save);
	}

	public function status()
	{
		echo TAB, 'Repo: ', $this->path(), BR;
		$save = getcwd();
		chdir($this->path);
		$this->system('hg status');
		chdir($save);
	}

	public function checkRevision()
	{
		$save = getcwd();
		chdir($this->path);
		echo '[' . getcwd() . ']', BR;
		$cmd = 'hg log -r ' . $this->getHash();
		$this->exec($cmd, $output);
		chdir($save);
		return $output;
	}

}
