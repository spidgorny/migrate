<?php

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

	function __construct(array $repos, $liveServer, $deployPath, $composerCommand, $remoteUser, $id_rsa) {
		$this->repos = $repos;
		$this->liveServer = $liveServer;
		$this->deployPath = $deployPath;
		$this->composerCommand = $composerCommand;
		$this->remoteUser = $remoteUser;
		$this->id_rsa = $id_rsa;
		if (!$this->remoteUser) {
			$this->remoteUser = $_SERVER['USERNAME'];
		}
		if (!$this->id_rsa) {
			$home = ifsetor($_SERVER['HOMEDRIVE']) .
				cap($_SERVER['HOMEPATH']);
			$this->id_rsa = $home . '.ssh/id_rsa';
		}
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
		return $deployPath;
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
		$files = $this->ssh_get($remoteCmd);
		if (sizeof($files) > 1) {	// error message is short
			return true;
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
			$remoteCmd = 'cd ' . $deployPath . ' && hg pull';
			$this->ssh_exec($remoteCmd);
		}
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
	 * Will update to the exact versions from VERSION.json on the server. All repositories one-by-one. Make sure to run rpull() first.
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
		$this->checkOnce();
		$this->pushAll();
		$exists = $this->rexists();
		if ($exists) {
			$this->rpull();
			$this->rinstall();
			$this->rcomposer();
		} else {
			$this->mkdir();
			$this->rclone();
			$this->rcomposer();
			$this->rinstall();
		}
		$this->rstatus();
		$this->deployDependencies();
		$nr = $this->getMain()->nr();
		$this->exec('start http://'.$this->liveServer.'/v'.$nr);
	}

	function pushAll() {
		$m = new Mercurial($this->repos);
		$m->push();
	}

	/**
	 * Trying to rcp vendor folder. Not working since rcp not using id_rsa?
	 */
	function rvendor() {
		$remotePath = $this->deployPath . '/v'.$this->getMain()->nr().'/';
		$this->system('scp -r vendor '.
			$this->remoteUser.'@'.$this->liveServer.':'.$remotePath.
			' -i '.$this->id_rsa);
	}

	function deployDependencies() {
		$this->pushAll();
		$deployPath = $this->deployPath . '/v'.$this->getMain()->nr();
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

}
