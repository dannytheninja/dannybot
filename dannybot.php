<?php

require_once('config.php');
require_once('libirc.php');
require_once('functions.php');

$irc = new IRCClient();

$irc->connect($config['server'], $config['port'], $config['identity'], $config['ssl'], isset($config['ssl_options']) ? $config['ssl_options'] : array());

// load modules
function load_modules()
{
	global $irc, $config;
	
	foreach ( $config['modules'] as $module )
	{
		if ( !preg_match('/^[a-z0-9_]+$/', $module) )
			throw new Exception("Module name \"$module\" is invalid.");
		
		$path = sprintf("modules/%s/Module.%s.php", $module, ucfirst($module));
		if ( !file_exists($path) )
			throw new Exception("Error loading module $module: $path: no such file");
		
		require_once($path);
	}
}

load_modules();

// join channels
// set umode -x when hostname masked
$irc->bind(IRCOpcode::OP_HOSTNAME_CHANGED, function($irc, $msg) use ($config)
	{
		$irc->bind(IRCOpcode::OP_HOSTNAME_CHANGED, function($irc, $msg) use ($config)
			{
				foreach ( $config['channels'] as $channel )
				{
					$irc->join($channel);
				}
				
				throw new IRCUnhookSignal();
			});
		
		$irc->umode('-x');
		throw new IRCUnhookSignal();
	});

// support the "rehash" command
$irc->bind('PRIVMSG', function($irc, $msg)
	{
		// this is only allowed via PM
		if ( $msg['extra']{0} == '#' )
			return;
		
		if ( $msg['body'] !== 'rehash' )
			return;
		
		check_permissions_and_nickserv($msg['identity']['nick'], 'rehash', $irc, function($irc) use ($msg)
			{
				require('config.php');
				$GLOBALS['config'] = $config;
				
				load_modules();
				
				// apply channel changes
				foreach ( $config['channels'] as $channel )
				{
					if ( !isset($irc->joined_channels[$channel]) )
						$irc->join($channel);
				}
				
				foreach ( array_keys($irc->joined_channels) as $channel )
				{
					if ( !in_array($channel, $config['channels']) )
						$irc->part($channel, "Channel was removed from the config");
				}
				
				// apply any nick changes
				if ( $irc->identity['nick'] !== $config['identity']['nick'] )
					$irc->set_nick($config['identity']['nick']);
				
				$irc->privmsg($msg['identity']['nick'], "Config has been reloaded.");
			});
		
		throw new IRCBreakHooks;
	});

// wait for things to happen
$irc->event_loop();

