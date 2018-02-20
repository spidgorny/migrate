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
	var $repos = [];

	/**
	 * Folders which are currently in use and can not be deleted
	 * @var
	 */
	var $protected = [];

	var $docRoots = [];

	function __construct()
	{
		$this->c = new Color();
		$folders = glob('*', GLOB_ONLYDIR);
		$this->protected = $this->findProtected($folders);
		$this->docRoots = $this->findDocRoots();
	//	print_r($this->docRoots);
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
		$this->df();

		$folders = glob('*', GLOB_ONLYDIR);
		foreach ($folders as $f) {
			$this->infoAbout($f);
		}
	}

	function infoAbout($f)
	{
		$c = $this->c;
		echo '== ', $f, PHP_EOL;
		$project = $this->getProject($f);
		echo TAB, 'project:', TAB, $c($project)->bg_green(), PHP_EOL;
		$isRoot = in_array(realpath($f), $this->docRoots);
		echo TAB, 'isRoot:', TAB, $isRoot ? $c('Yes')->bg_red() : 'No', PHP_EOL;
		if (!$isRoot) {
			$files = $this->getFiles($f);
			echo TAB, 'files:', TAB, TAB, $c($files)->bg_yellow(), PHP_EOL;
			$prot = $this->isProt($f) ? $c('Yes')->bg_red() : 'No';
			echo TAB, 'protected:', TAB, $prot, PHP_EOL;
		}
	}

	function getProject($f)
	{
		$composer = $f . '/composer.json';
		if (file_exists($composer)) {
			$json = file_get_contents($composer);
			$info = json_decode($json);
			return $info->name;
		}
		return null;
	}

	function getFiles($f)
	{
		$cmd = 'find ' . escapeshellarg($f) . ' -type f | wc -l';
		return exec($cmd);
	}

	function findProtected(array $folders)
	{
		$special = ['.htaccess', 'index.php', 'index.html'];
		$merge = '';
		foreach ($special as $file) {
			if (file_exists($file)) {
				$merge .= file_get_contents($file);
			}
		}

		$protected = [];
		foreach ($folders as $f) {
			if (strpos($merge, '/' . $f . '/') !== false) {
				$protected[] = $f;
			}
		}
		return $protected;
	}

	function isProt($f)
	{
		return in_array($f, $this->protected);
	}

	/**
	 * @param array $folders
	 * @throws Exception
	 */
	function rm($folders)
	{
		$prev = $this->df();

		$folders = func_get_args();
		foreach ($folders as $f) {
			$this->infoAbout($f);
			if ($this->isProt($f)) {
				throw new Exception($f . ' is protected');
			}
//			exec('mv ' . escapeshellarg($f) . ' /tmp');
			exec('rm -rf ' . escapeshellarg($f));
		}

		$now = $this->df();
		echo 'Freed ', intval($now['avail']) - intval($prev['avail']), 'MB', PHP_EOL;
	}

	function df()
	{
		exec('df .', $out);
		$data = explode(' ', $out[1]);
		$data = array_filter($data);
//		print_r($data);
		$data = array_combine([
			'fs', 'total', 'used', 'avail', '%', 'mount',
		], $data);
		$data['total'] /= 1024;
		$data['used'] /= 1024;
		$data['avail'] /= 1024;
		$data['total'] = round($data['total']) . 'M';
		$data['used'] = round($data['used']) . 'M';
		$data['avail'] = round($data['avail']) . 'M';
		print_r($data);
		return $data;
	}

	function findDocRoots($confFile = '/etc/apache2/httpd.conf')
	{
		if (false) {
			if (file_exists($confFile)) {
				$conf = parse_ini_file($confFile);
				var_dump($conf);
			}
		}

		if (false) {
			$conf = new Config();
			$root = $conf->parseConfig($confFile, "apache");
			print_r($root);
		}

		$skip = [];
		$docRoots = [];
		foreach (file($confFile) as $line) {
			$parts = explode(' ', trim($line));
			if ($parts[0] == 'Include') {
				// Include /etc/apache2/vhosts.d/*.conf
				$files = glob($parts[1]);
				foreach ($files as $file) {
					$include = $this->findDocRoots($file);
					$docRoots = array_merge($docRoots, $include);
				}
			} elseif ($parts[0] == 'DocumentRoot') {
				$docRoots[] = $this->deQuote($parts[1]);
			} elseif ($parts[0] == '<Directory') {
				$docRoots[] = $this->deQuote($parts[1]);
			} else {
				$skip[$parts[0]] = ifsetor($parts[1]);
			}
		}
//		print_r($skip);
		$docRoots = array_filter($docRoots);
		foreach ($docRoots as $root) {
			$docRoots[] = realpath($root);
		}
		$docRoots = array_unique($docRoots);
		$docRoots = array_filter($docRoots);
		return $docRoots;
	}

	function deQuote($str)
	{
		preg_match('/"(.+?)"/', $str, $matches);
//		print_r([$str, $matches]);
		return ifsetor($matches[1]);
	}

}
