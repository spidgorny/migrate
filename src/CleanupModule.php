<?php

use Colors\Color;

function tab($text, $tabs = 1)
{
	// https://stackoverflow.com/questions/40731273/php-remove-terminal-codes-from-string
	$onlyAscii = preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $text);
	$len = strlen($onlyAscii);
	$lenTabs = floor($len/8);
	return $text . str_repeat(TAB, 1 + $tabs - $lenTabs);
}

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

	/**
	 * Current user
	 * @var string
	 */
	var $username;

	function __construct()
	{
		$this->c = new Color();
		$folders = glob('*', GLOB_ONLYDIR);
		$this->protected = $this->findProtected($folders);
		$this->docRoots = $this->findDocRoots();
	//	print_r($this->docRoots);
		$this->username = exec('whoami');
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
		$this->getOwner(posix_getuid());

		$folders = glob('*', GLOB_ONLYDIR);
		foreach ($folders as $f) {
			$this->infoAbout($f);
		}
	}

	function infoAbout($f)
	{
		$c = $this->c;

		$project = $this->getProject($f);
		$isRoot = in_array(realpath($f), $this->docRoots);
		if (!$isRoot) {
			$files = $this->getFiles($f);
			$owner = $this->getOwner(fileowner($f));
		} else {
			$files = null;
			$owner = null;
		}
		$this->render2($f, (object)[
			'project' => $c($project)->bg_green().'',
			'isRoot' => $isRoot ? $c('Yes')->bg_red().'' : 'No',
			'files' => $c($files)->bg_yellow().'',
			'prot' => $this->isProt($f)
				? $c('Yes')->bg_red().'' : 'No',
			'owner' => $owner == $this->username
				? $c($owner)->bg_green().''
				: $c($owner)->bg_red().'',
			'isLink' => is_link($f)
				? $c('Yes')->bg_cyan().'' : 'No',
		]);
	}

	function render($f, stdClass $props)
	{
		$project = $props->project;
		$isRoot = $props->isRoot;
		$files = $props->files;
		$prot = $props->prot;
		$owner = $props->owner;
		$isLink = $props->isLink;
		echo '== ', $f, PHP_EOL;
		echo TAB, 'project:', TAB, $project, PHP_EOL;
		echo TAB, 'isRoot:', TAB, TAB, $isRoot, PHP_EOL;
		echo TAB, 'files:', TAB, TAB, $files, PHP_EOL;
		echo TAB, 'protected:', TAB, $prot, PHP_EOL;
		echo TAB, 'owner:', TAB, TAB, $owner, PHP_EOL;
		echo TAB, 'isLink:', TAB, TAB, $isLink, PHP_EOL;
	}

	function render2($f, stdClass $props)
	{
		//print_r($props);
		$project = $props->project;
		$isRoot = $props->isRoot;
		$files = $props->files;
		$prot = $props->prot;
		$owner = $props->owner;
		$isLink = $props->isLink;
		echo tab($f, 2),
			tab($project, 4),
			'isRoot:', $isRoot, TAB,
			tab('files:' . $files, 0), TAB,
			'protected:', $prot, TAB,
			'owner:', $owner, TAB,
			'isLink:', $isLink, PHP_EOL;
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
		return in_array($f, $this->protected)
			|| in_array(realpath($f), $this->docRoots)
			|| is_link($f)
			|| $this->username != $this->getOwner(fileowner($f));
	}

	/**
	 * @param array $folders
	 * @throws Exception
	 */
	function rm($folders)
	{
		$prev = $this->df();
		$this->getOwner(posix_getuid());

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

	function getOwner($uid)
	{
		static $passwd;
		if (!$passwd) {
			exec('getent passwd', $lines);
			$passwd = array_map(function ($line) {
				$data = trimExplode(':', trim($line));
				$cols = [
					'login', 'pw', 'uid', 'gid', 'name', 'home', 'bash',
				];
				if (sizeof($cols) != sizeof($data)) {
					$data += array_fill(sizeof($data), sizeof($cols) - sizeof($data), null);
//					print_r($data);
				}
				return array_combine($cols, $data);
			}, $lines);
			$uidList = array_map(function (array $row) {
				return $row['uid'];
			}, $passwd);
			$passwd = array_combine($uidList, $passwd);
//			print_r($passwd);
		}
		return ifsetor($passwd[$uid]['login'], '<unknown>');	// login
	}

}
