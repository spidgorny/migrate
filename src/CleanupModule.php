<?php

use Colors\Color;

class CleanupModule implements ModuleInterface {

	var $verbose;

	/**
	 * @var Color
	 */
	var $c;

	/**
	 * For compatibility with other modules
	 * @var
	 */
	var $repos;

	function __construct()
	{
		$this->c = new Color();
	}

	function setVerbose($bool)
	{
		$this->verbose = $bool;
	}

	/**
	 * Help remove old deployed folder
	 */
	function cleanup()
	{
		$c = $this->c;

		system('du -h');

		$folders = glob('*', GLOB_ONLYDIR);
		foreach ($folders as $f) {
			echo '== ', $f, PHP_EOL;
			$project = $this->getProject($f);
			$files = $this->getFiles($f);
			echo TAB, 'project:', TAB, $c($project)->bg_green(), PHP_EOL;
			echo TAB, 'files:', TAB, TAB, $c($files)->bg_yellow(), PHP_EOL;
		}
	}

	function getProject($f)
	{
		$composer = $f.'/composer.json';
		if (file_exists($composer)) {
			$json = file_get_contents($composer);
			$info = json_decode($json);
			return $info->name;
		}
		return null;
	}

	function getFiles($f)
	{
		$cmd = 'find '.escapeshellarg($f).' -type f | wc -l';
		return exec($cmd);
	}

}
