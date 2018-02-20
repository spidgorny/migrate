<?php

namespace spidgorny\migrate;

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

	var $verbose = false;

	/**
	 * @var 'default' by default
	 */
	var $branch = 'default';

	function __construct()
	{
		if (file_exists(self::versionFile)) {
			$json = json_decode(file_get_contents(self::versionFile));
			if (ifsetor($json->liveServer)) {
				foreach ($json as $key => $val) {
					$this->$key = $val;
				}
				$this->repos = (array)$this->repos;
			} else {
				$this->repos = (array)$json;
			}
			foreach ($this->repos as &$row) {
				$row = Repo::decode($row);
			}
		}

		$this->modules = [
			'this' => $this,
			'Local' => new Local($this->repos, $this),
			'Mercurial' => new Mercurial($this->repos),
			'Remote' => new Remote(
				$this->repos,
				$this->liveServer,
				$this->deployPath,
				$this->composerCommand,
				$this->remoteUser,
				$this->id_rsa,
				$this->branch
			),
			'Cleanup' => new \CleanupModule(),
		];

		if (in_array('--verbose', $_SERVER['argv'])
			|| $this->verbose) {
			$this->verbose = true;
			array_walk($this->repos, function ($repo) {
				/** @var $repo Repo */
				$repo->setVerbose(true);
			});
			array_walk($this->modules, function (\ModuleInterface $mod) {
				$mod->setVerbose(true);
			});
			$pos = array_search('--verbose', $_SERVER['argv']);
			unset($_SERVER['argv'][$pos]);
			$_SERVER['argc']--;
		}
	}

	/**
	 * @return Local
	 */
	function getLocal() {
		return $this->modules['Local'];
	}

	/**
	 * @return Mercurial
	 */
	function getMercurial() {
		return $this->modules['Mercurial'];
	}

	/**
	 * @return Remote
	 */
	function getRemote() {
		return $this->modules['Remote'];
	}

	function setVerbose($v) {
		$this->verbose = $v;
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
		//debug($_SERVER['argv']);
		$cmd = ifsetor($_SERVER['argv'][1], 'index');
		$res = array_slice($_SERVER['argv'], 2);
		//echo $cmd, BR;
		$done = false;
		foreach ($this->modules as $module) {
			$exists = method_exists($module, $cmd);
			if ($exists) {
				echo "> ", get_class($module), '->', $cmd, '(', json_encode($res).')', BR;
				call_user_func_array([$module, $cmd], $res);

				// copy changes to the repos back here for destruction
				$this->repos = $module->repos;
				$done = true;
				break;
			}
		}

		if (!$done) {
			echo 'Command not found: '.$cmd.'('.implode(', ', $res).')', BR;
		}
	}

	function help() {
		foreach ($this->modules as $key => $class) {
			if ($key != 'this') {
				echo TAB, $key, ':', BR;
				$this->helpAboutClass($class);
			}
		}
	}

	function helpAboutClass($class) {
		$rc = new \ReflectionClass($class);
		foreach ($rc->getMethods() as $method) {
			$comment = $method->getDocComment();
			if ($comment && !contains($comment, '@internal')) {
				$commentLines = trimExplode("\n", $comment);
				$commentAbout = $commentLines[1];
				$commentAbout = trim($commentAbout, " \t\n\r\0\x0B*");
				if ($commentAbout[0] != '@') {
					echo $this->twoTabs($method->getName()),
					$commentAbout, BR;
				}
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
	 * Pull, Update, Composer
	 */
	function puc() {
		$this->modules['Mercurial']->pull();
		$this->modules['Mercurial']->update();
		$this->modules['Local']->composer();
	}

}
