<?php

namespace spidgorny\migrate;

require_once __DIR__.'/SystemCommandException.php';

/**
 * Class Remote
 * @mixin Mercurial
 */
class Remote extends Base {

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

	var $remoteUser;

	var $id_rsa;

	function __construct(array $repos, $liveServer, $deployPath, $composerCommand, $remoteUser, $id_rsa, $branch) {
//		debug($_SERVER); exit;

		$this->repos = $repos;
		$this->liveServer = $liveServer;
		$this->deployPath = $deployPath;
		$this->composerCommand = $composerCommand;
		$this->remoteUser = $remoteUser;
		$this->id_rsa = $id_rsa;
		$this->branch = $branch;

		if (!$this->remoteUser) {
			$this->remoteUser = $_SERVER['USERNAME'];
		}
		if (!$this->id_rsa) {
			if ($this->isWindows()) {
				$home = ifsetor($_SERVER['HOMEDRIVE']) .
					cap($_SERVER['HOMEPATH']);
				// for mingw32
				if ($_SERVER['MSYSTEM'] == "MINGW32") {
					$home = str_replace('\\', '/', $home);
					$home = str_replace('c:', '/c', $home);
				}
			} else {
				$home = cap(ifsetor($_SERVER['HOME']));
			}
			$this->id_rsa = $home . '.ssh/id_rsa';
		}
	}

	function isWindows() {
		return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	}

	function ssh_exec($remoteCmd) {
		$this->system('ssh '.$this->remoteUser.'@'.$this->liveServer.' -i '.$this->id_rsa.' "'.$remoteCmd.'"');
	}

	function ssh_get($remoteCmd) {
		return $this->exec('ssh '.$this->remoteUser.'@'.$this->liveServer.' -i '.$this->id_rsa.' "'.$remoteCmd.'"');
	}

	function test_ssh2() {
		$userName = $_SERVER['USERNAME'];
		$home = ifsetor($_SERVER['HOMEDRIVE']) .
			cap($_SERVER['HOMEPATH']);
		$publicKeyFile = $home . 'Slawa.ppk';
		$privateKeyFile = $home . 'Slawa.ssh';
		$nr = $this->getMain()->nr();
		debug($nr, $userName, $publicKeyFile, $privateKeyFile);
		$connection = ssh2_connect('asd', 22);
		ssh2_auth_pubkey_file($connection, $userName, $publicKeyFile, $privateKeyFile);
		$stream = ssh2_exec($connection, '/usr/local/bin/php -i');
		echo $stream;
	}

	function rhelp() {
		echo 'Remote help', BR;
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
		$deployPath = str_replace('\\', '/', $deployPath);
		return $deployPath;
	}

	/**
	 * Will connect to the live server and ls -l.
	 * @param string $path
	 */
	function rpath($path = '') {
		$versionPath = $this->deployPath;
		$remoteCmd = 'cd '.$versionPath.' && ls -l '.$path;
		$this->ssh_exec($remoteCmd);
	}

	/**
	 * Will connect to the live server and ls -l.
	 * @param string $path
	 */
	function rls($path = '') {
		$this->checkOnce();
		$versionPath = $this->getVersionPath();
		$remoteCmd = 'cd '.$versionPath.' && ls -l '.$path;
		$this->ssh_exec($remoteCmd);
	}

	function rexists($path = '') {
		$this->checkOnce();
		$versionPath = $this->getVersionPath();
		$remoteCmd = 'cd '.$versionPath.' && ls -l '.$path;
		try {
			$files = $this->ssh_get($remoteCmd);
			if (sizeof($files) > 1) {    // error message is short
				return true;
			}
		} catch (\SystemCommandException $e) {
			// just return false
		}
		return false;
	}

	/**
	 * Will connect to the live server and clone current repository.
	 */
	function rclone() {
		$this->checkOnce();
		$versionPath = $this->getVersionPath();
		$paths = $this->getMain()->getPaths();
		$default = $paths['default'];
		$remoteCmd = 'cd '.$versionPath.' && hg clone '.$default .' .';
		$this->ssh_exec($remoteCmd);
	}

	/**
	 * Will run hg status remotely
	 */
	function rstatus() {
		$this->checkOnce();
		$versionPath = $this->getVersionPath();
		$remoteCmd = 'cd '.$versionPath.' && hg status';
		$this->ssh_exec($remoteCmd);
	}

	/**
	 * Will pull on the server
	 */
	function rpull() {
		$this->checkOnce();
		$versionPath = $this->getVersionPath();
		/** @var Repo $repo */
		foreach ($this->repos as $repo) {
			echo BR, '## ', $repo->path, BR;
			$deployPath = $versionPath . '/' . $repo->path();
			try {
				$exists = $this->rexists($deployPath);
				if ($exists) {
					$remoteCmd = 'cd ' . $deployPath . ' && hg pull';
					$this->ssh_exec($remoteCmd);
				} else {
					echo TAB, '*** path ', $deployPath, ' does not exist', BR;
				}
			} catch (\SystemCommandException $e) {
				echo 'Error: '.$e, BR;
			}
		}
	}

	/**
	 * Will update only the main project on the server. Not recommended. Use rinstall instead.
	 */
	function rupdate() {
		$this->checkOnce();
		$deployPath = $this->getVersionPath();
		$branch = $this->branch ?: 'default';
		$remoteCmd = 'cd '.$deployPath.' && hg update -r '.$branch;
		$this->ssh_exec($remoteCmd);
	}

	/**
	 * Will update to the exact versions from VERSION.json on the server. All repositories one-by-one. Make sure to run rpull() first.
	 */
	function rinstall() {
		$versionPath = $this->getVersionPath();
//		debug($versionPath);
		/** @var Repo $repo */
		foreach ($this->repos as $repo) {
			echo BR, '## ', $repo->path(), BR;
			if ($this->rexists($repo->path())) {
				$deployPath = $versionPath . '/' . $repo->path();
				$cmd = 'cd ' . $deployPath . ' && hg update -r ' . $repo->getHash();
				$this->ssh_exec($cmd);
			} else {
				echo 'Error: '.$repo->path.' is not on the server. Maybe do rdeploy?', BR;
			}
		}
	}

	/**
	 * Will run composer remotely
	 */
	function rcomposer() {
		$this->checkOnce();
		$deployPath = $this->getVersionPath();
		//$this->ssh_exec($this->composerCommand.' clear-cache');
		$this->ssh_exec($this->composerCommand.' config -g secure-http false');
		$remoteCmd = 'cd '.$deployPath.' && '.$this->composerCommand.' install --ignore-platform-reqs';
		$this->ssh_exec($remoteCmd);
	}

	/**
	 * Will make a new folder and clone, compose into it or update existing
	 */
	function rdeploy() {
		echo BR, TAB, TAB, '### checkOnce', BR;
		$this->checkOnce();

		echo BR, TAB, TAB, '### pushAll', BR;
		$this->pushAll();
		try {
			$exists = $this->rexists();
		} catch (\SystemCommandException $e) {
			$exists = false;
		}

		echo 'The destination folder ', $this->getVersionPath() . ($exists ? ' exists' : 'does not exist'), BR, BR;
		if ($exists) {
			echo BR, TAB, TAB, '### rpull', BR;
			$this->rpull();
		} else {
			echo BR, TAB, TAB, '### mkdir', BR;
			$this->mkdir();

			echo BR, TAB, TAB, '### rclone', BR;
			$this->rclone();
		}

		echo BR, TAB, TAB, '### rupdate', BR;
		$this->rupdate();

		echo BR, TAB, TAB, '### rcomposer', BR;
		$this->rcp('composer.json');
		$this->rcp('composer.lock');
		$this->rcomposer();

		echo BR, TAB, TAB, '### postinstall', BR;
		$this->rpostinstall();

		echo BR, TAB, TAB, '### rinstall', BR;
		$this->rinstall();

		echo BR, TAB, TAB, '### rstatus', BR;
		$this->rstatus();

		echo BR, TAB, TAB, '### deployDependencies', BR;
		$this->deployDependencies();
		$nr = $this->getMain()->nr();
		$this->exec('start http://'.$this->liveServer.'/v'.$nr);
	}

	function pushAll() {
		$m = new Mercurial($this->repos);
		$m->push();
	}

	/**
	 * Trying to rcp the project to the folder.
	 * @param string $path
	 */
	function rcp($path = '.') {
		$this->setVerbose(true);
		$this->checkOnce();
		$remotePath = $this->getVersionPath();
		$pathLinux = str_replace('\\', '/', $path);
		$this->system('scp -r -i '.$this->id_rsa.' '.
			escapeshellarg($path). ' ' .
			$this->remoteUser.'@'.$this->liveServer.
			':'.$remotePath.'/'.$pathLinux);
	}

	/**
	 * Trying to rcp vendor folder. Used when composer is not available remotely.
	 */
	function rvendor() {
		$this->checkOnce();
		$remotePath = $this->getVersionPath().'/';
		$this->system('scp -r -v -i '.$this->id_rsa.' vendor '.
			$this->remoteUser.'@'.$this->liveServer.':'.$remotePath);
	}

	function deployDependencies() {
		$this->pushAll();
		$deployPath = $this->getVersionPath();
		/** @var Repo $repo */
		foreach ($this->repos as $path => $repo) {
			if ($path != '.') {
				$repoPath = $repo->path();
				if (!$this->rexists($repoPath)) {
					$remoteCmd = 'cd ' . $deployPath . ' && mkdir -p ' . $repoPath;
					$this->ssh_exec($remoteCmd);

					$remoteCmd = 'cd ' . $deployPath . ' && hg clone ' . $repo->getDefaultPath() . ' ' . $repoPath;
					$this->ssh_exec($remoteCmd);
				}
			}
		}
		$this->rpull();
		$this->rinstall();
	}

	/**
	 * Will run post-install-cmd scripts from composer.json
	 */
	function rpostinstall() {
		if (file_exists('composer.json')) {
			$composer = json_decode(file_get_contents('composer.json'));
			//debug($composer);
			if (isset($composer->scripts) && isset($composer->scripts->{'post-install-cmd'})) {
				$cmd = $this->composerCommand . ' run-script post-install-cmd';
				$deployPath = $this->getVersionPath();
				$remoteCmd = 'cd ' . $deployPath . ' && ' . $cmd;
				$this->ssh_exec($remoteCmd);
			}
		}
	}

}
