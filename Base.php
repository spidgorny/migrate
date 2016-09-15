<?php

class Base {

	/**
	 * Server name or IP
	 * @var string
	 */
	var $liveServer = 'no.server.defined.com';

	/**
	 * @var Repo[]
	 */
	var $repos;

	function run() {
		$cmd = ifsetor($_SERVER['argv'][1], 'index');
		$res = array_slice($_SERVER['argv'], 2);
		//echo $cmd, BR;
		call_user_func_array([$this, $cmd], $res);
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
	 * Will return output
	 * @param $cmd
	 * @return mixed
	 */
	function exec($cmd) {
		echo '> ', $cmd, BR;
		exec($cmd, $output);
		return $output;
	}

	/**
	 * Will echo output to STDOUT
	 * @param $cmd
	 */
	function system($cmd) {
		echo '> ', $cmd, BR;
		system($cmd);
	}

	/**
	 * @return Repo
	 */
	function getMain() {
		return $this->repos['.'];
	}


	function ssh_exec($remoteCmd) {
		$userName = $_SERVER['USERNAME'];
		$home = ifsetor($_SERVER['HOMEDRIVE']) .
			cap($_SERVER['HOMEPATH']);
		$publicKeyFile = $home . '.ssh/id_rsa';
		$this->system('ssh '.$userName.'@'.$this->liveServer.' -i '.$publicKeyFile.' "'.$remoteCmd.'"');
	}

	function ssh_get($remoteCmd) {
		$userName = $_SERVER['USERNAME'];
		$home = ifsetor($_SERVER['HOMEDRIVE']) .
			cap($_SERVER['HOMEPATH']);
		$publicKeyFile = $home . '.ssh/id_rsa';
		return $this->exec('ssh '.$userName.'@'.$this->liveServer.' -i '.$publicKeyFile.' "'.$remoteCmd.'"');
	}

	function test_ssh2() {
		$userName = $_SERVER['USERNAME'];
		$home = ifsetor($_SERVER['HOMEDRIVE']) .
			cap($_SERVER['HOMEPATH']);
		$publicKeyFile = $home . 'NintendoSlawa.ppk';
		$privateKeyFile = $home . 'NintendoSlawa.ssh';
		debug($nr, $userName, $publicKeyFile, $privateKeyFile);
		$connection = ssh2_connect('glore.nintendo.de', 22);
		ssh2_auth_pubkey_file($connection, $userName, $publicKeyFile, $privateKeyFile);
		$stream = ssh2_exec($connection, '/usr/local/bin/php -i');
		echo $stream;
	}

}
