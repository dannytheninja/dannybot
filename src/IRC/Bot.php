<?php

/**
 * dannybot - an IRC logging, ban management and stats bot
 * Copyright (C) 2016 DannyTheNinja <danny@paddedninja.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
 
namespace DannyTheNinja\IRC;

/**
 * IRC bot
 */

class Bot
{
	/**
	 * The bot's configuration
	 */
	
	private $config;
	
	/**
	 * Words that will be censored.
	 * @var array
	 */
	
	protected $censoredWords = [
		'cock',
		'fuck',
		'cunt',
		'bitch',
		'nigger'
	];
	
	/**
	 * Constructor.
	 */
	
	public function __construct(Bot\Config $config, Client $client = null)
	{
		$this->config = $config;
		$this->client = $client ?: new Client;
	}
	
	/**
	 * Run the bot!
	 */
	
	public function run()
	{
		$this->client->connect(
			$this->config->hostname,
			$this->config->port,
			[
				'nick' => $this->config->nick,
				'user' => $this->config->username,
				'gecos' => $this->config->gecos
			],
			$this->config->ssl,
			$this->config->sslOptions
		);
		
		$this->loadPlugins();
		$this->joinChannels();
		$this->setupRehash();
		
		$this->client->event_loop();
	}
	
	/**
	 * Check that a nick can do a thing
	 * 
	 * @param string
	 *   nickname
	 * @param string
	 *   what they want to do
	 */
	
	public function checkPermission($nick, $permission)
	{
		if ( !isset($this->config->permissions[$nick]) ) {
			return false;
		}
		
		return in_array('ALL', $this->config->permissions[$nick]) || in_array($permission, $this->config->permissions[$nick]);
	}
	
	/**
	 * More extensive permission check. You really should use this one most of
	 * the time, since it verifies the nickname with nickserv
	 * 
	 * @param string
	 *   nickname
	 * @param string
	 *   what they want to do
	 * @param callback
	 *   callback to be called if they are allowed to do the thing - will not be
	 *   called if the user lacks permission
	 */
	
	public function checkPermissionsAndNickserv($nick, $permission, $callback)
	{
		if ( $this->checkPermission($nick, $permission) )
		{
			$this->client->whois($nick, function($whois) use ($nick, $permission, $callback)
				{
					if ( isset($whois['services_identity']) && $this->checkPermission($whois['services_identity']['idnick'], $permission) )
					{
						call_user_func($callback, $this->client);
					}
				});
		}
	}
	
	/**
	 * (Re)load plugins
	 */
	
	private function loadPlugins()
	{
		foreach ( $this->config->plugins as $plugin ) {
			$plugin->unload($this->client);
			$plugin->load($this, $this->client);
		}
	}
	
	/**
	 * Join channels
	 */
	
	private function joinChannels()
	{
		// join channels
		// set umode -x when hostname masked
		$this->client->bind(Opcode::OP_HOSTNAME_CHANGED, function($irc, $msg)
			{
				$irc->bind(Opcode::OP_HOSTNAME_CHANGED, function($irc, $msg)
					{
						foreach ( $this->config->channels as $channel ) {
							$irc->join($channel);
						}
						
						throw new Signal\Unhook();
					});
				
				$irc->umode('-x');
				throw new Signal\Unhook();
			});
	}
	
	/**
	 * Setup the rehash hook
	 */
	
	private function setupRehash()
	{
		// support the "rehash" command
		$this->client->bind('PRIVMSG', function($irc, $msg)
			{
				// this is only allowed via PM
				if ( $msg['extra']{0} === '#' ) {
					return;
				}
				
				if ( $msg['body'] !== 'rehash' ) {
					return;
				}
				
				$this->checkPermissionsAndNickserv($msg['identity']['nick'], 'rehash', function($irc) use ($msg)
					{
						$this->loadPlugins();
						
						// apply channel changes
						foreach ( $this->config->channels as $channel ) {
							if ( !isset($irc->joined_channels[$channel]) ) {
								$irc->join($channel);
							}
						}
						
						foreach ( array_keys($irc->joined_channels) as $channel ) {
							if ( !in_array($channel, $this->config->channels) ) {
								$irc->part($channel, "Channel was removed from the config");
							}
						}
						
						// apply any nick changes
						if ( $irc->identity['nick'] !== $this->config->nick ) {
							$irc->set_nick($this->config->nick);
						}
						
						$irc->privmsg($msg['identity']['nick'], "Config has been reloaded.");
					});
				
				throw new Signal\BreakHooks;
			});
	}
	
	/**
	 * Censor words
	 * 
	 * @param string
	 *   String to be censored
	 * @return string
	 * 
	 * @example
	 <code>
	 $result = $this->censorWords("fuck you bitch nigger!");
	 $this->client->msg('#derp', $result); // sends "f*** you b**** n*****!"
	 </code>
	 */
	
	private function censorWords($text)
	{
		foreach ( $this->censoredWords as $word ) {
			$replacement = substr($word, 0, 1) . preg_replace('/./', '*', substr($word, 1));
			while ( stristr($text, $word) ) {
				$text = preg_replace("/$word/i", $replacement, $text);
			}
		}
		return $text;
	}
}