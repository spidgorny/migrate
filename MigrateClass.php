<?php

class Migrate {

	const versionFile = 'VERSION.json';

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

	/**
	 * We have too many commands now. They are separated into
	 * several modules. Each module may have multiple commands.
	 * @var Base[]
	 */
	var $modules = [];

	/**
	 * @var Repo[]
	 */
	var $repos = [];

	function __construct()
	{
		if (file_exists(self::versionFile)) {
			$json = json_decode(file_get_contents(self::versionFile));
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

		$this->modules = [
			$this,
			new Local($this->repos),
			new Mercurial($this->repos),
			new Remote(
				$this->repos,
				$this->liveServer,
				$this->deployPath,
				$this->composerCommand,
				$this->remoteUser,
				$this->id_rsa
			),
		];
	}

	function __destruct()
	{
		$json = get_object_vars($this);
		unset($json['modules']);	// recursion
//		debug($json);
		$json = json_encode($json, JSON_PRETTY_PRINT);
//		echo $json, BR;
		if ($json) {
			file_put_contents(self::versionFile, $json);
		} else {
			echo 'JSON data missing', BR;
		}
	}

	function run() {
		$cmd = ifsetor($_SERVER['argv'][1], 'index');
		$res = array_slice($_SERVER['argv'], 2);
		//echo $cmd, BR;
		$done = false;
		foreach ($this->modules as $module) {
			$exists = method_exists($module, $cmd);
			if ($exists) {
				call_user_func_array([$module, $cmd], $res);

				// copy changes to the repos back here for destruction
				$this->repos = $module->repos;
				$done = true;
			}
		}

		if (!$done) {
			echo 'Command not found: '.$cmd.'('.implode(', ', $res).')', BR;
		}
	}

}
