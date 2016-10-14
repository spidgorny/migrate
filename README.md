# migrate
PHP script to handle versioning of mercurial project migration with it's dependent mercurial repositories.

If you use git - you don't need to read further.

If you use composer for versioning your dependencies with branches - you don't need to read further. Continue reading if you don't use branches in Mercurial.

## Quick start

```bash
> migrate add .
> migrate add vendor/spidgorny/nadlib
> migrate add vendor/something/else
> hg add VERSION.json
> migrate commit "update VERSION.json"
> cd /directory/corresponding/to/the/live/server
> rem Start synchronization tool (read below)
> migrate golive
...........
```

## Intro

When testing [deployer](http://deployer.org/) for deploying my PHP applications I've realized it is meant to be used with Git. It does not work with Mercurial (hg). This led me to write my own deployment script for projects tracked in Mercurial.

## Purpose of deployment in general

There are two independent problems that **migrate** tries to solve. One is the ability to upload files to the remote machine. This is a bare deployment itself. The other problem is to know which exact version of the project and it's dependent libraries are to be deployed and were deployed on the live server last time.

This is solved in **migrate** by storing the necessary versions in the VERSION.json file. There are some commands for managing repositories in the VERSION.json file. This file can be edited manually as well, if you know what you are going.

## Management of repositories in VERSION.json

### add	
Adds specified folder (default .) to the VERSION.json file. Make sure you add at least the main project as ".".

```bash
> migrate add .
```

### del		
Removes specified folder (no default) from VERSION.json file.

### compare		
Shows both the current VERSION.json and currently installed versions.

### dump		
Just shows what is stored in VERSION.json now. This is a default command if migrate is called without any parameters.

## Deployment strategies

There are two ways to do the deployment with **migrate**. One way is to run the commands on the local folder on your PC and rely on some third party synchronization tool to copy the changes made to the local files to a remote server. Such tools are:
* PhpStorm
* WinSCP
* FileZilla

The other way is to run Mercurial and composer commands directly on the live server so that the server will pull the changes from the repositories. This requires SSH access to the server and SSH client installed on the PC. It also requires public key authentication to the server since otherwise you will be asked to enter your password way too often. At least once for every command.

## Deployment with external synchronization

When running these commands you switch to a local folder which corresponds to the state of the live server and run synchronization tool in the background. These commands will change the contents of the local files only. All changes to the live server depend on the sync tool (for example PhpStorm). 

_This is not recommended anymore. If you have SSH access to the live server please check the deployment with remote hg commands instead._

### install		
Runs "hg pull" and "hg update -r xxx" on each repository.

### composer	
Runs composer install.

### golive		
Checks current versions, does install of new versions, composer (call on LIVE).

### info		
Shows the default pull/push location for current folder. Allows to compare current and latest version.

### update		
Will fetch the latest version available and update to it. Like install but only for the main folder repo (.)

### check		
Runs "hg id" for each repository in VERSION.json. This will replace the VERSION.json.

## Mercurial commands

Since we have dependant repositories stored in VERSION.json file we can run some Mercurial commands on the specific repository without going to that directory manually. And we can run these commands for all the tracked repositories one-by-one automatically.

You may specify which repository each command should be run at by typing only part of the repository path.

```bash
> migrate thg nadlib
```

This will run a command on vendor\spidgorny\nadlib path as "nadlib" matches part of the path.

### thg		
Will execute TortoiseHG in a specified folder (use .). Partial match works.

### changelog	
Shows a changelog of the main project which can be read by humans.

### push		
Only does "hg pull" without any updating.

### pull		
Only does "hg pull" without any updating.

### commit		
Will commit the VERSION.json file and push.

### dump		
Just shows what is stored in VERSION.json now.

### check		
Runs "hg id" for each repository in VERSION.json. This will replace the VERSION.json. This is called before other commands often in order to know the current state of the repositories. 

## Deployment with remote HG commands

This is the best part of **migrate**. Before continue - make sure you can run

```bash
> ssh live.server.com -i your/public/key.file
```

without typing your password. All commands below run ssh command like the one above + specify what to do on the live server after connecting to it.
 
Another concept is versioning in a subfolder. Migrate will automatically create a subfolder on the live server which corresponds to the revision number of your project in mercurial repository. It's up to you to change your Apache configuration to point to a new location or use RewriteRule to dynamically route user requests to a corresponding subfolder.

The live server name, live server 

### mkdir		
Will ssh to the live server and create subfolder for current version.

###rls		
Will connect to the live server and ls -l.

###rclone		
Will connect to the live server and clone current repository.

###rstatus		
Will run hg status remotely

rpull		Will pull on the server
rupdate		Will update only the main project on the server. Not recommended. Use rinstall instead.
rinstall	Will update to the exact versions from VERSION.json on the server. All repositories one-by-one. Make sure to run rpull() first.
rcomposer	Will run composer remotely
rdeploy		Will make a new folder and clone, compose into it or update existing
rvendor		Trying to rcp vendor folder. Not working since rcp not using id_rsa?
dump		Just shows what is stored in VERSION.json now
check		Runs "hg id" for each repository in VERSION.json. This will replace the VERSION.json
