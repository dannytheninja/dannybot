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

namespace DannyTheNinja\IRC\Bot;

use DannyTheNinja\IRC\Plugin\AbstractPlugin;

/**
 * Represents configuration for an IRC bot.
 */

class Config
{
	/** @var string */
	private $hostname;
	
	/** @var int */
	private $port;
	
	/** @var bool */
	private $ssl = false;
	
	/** @var array */
	private $sslOptions = [];
	
	/** @var string */
	private $nick;
	
	/** @var string */
	private $username;
	
	/** @var string */
	private $gecos;
	
	/** @var array */
	private $channels = [];
	
	/** @var array */
	private $permissions = [];
	
	/** @var array */
	private $plugins = [];
	
	/**
	 * Set server parameters
	 *
	 * @param string
	 *   Server hostname
	 * @param int
	 *   Server port
	 * @param bool
	 *   SSL switch; default = false
	 * @param array
	 *   SSL options; default = empty array
	 *   @see http://php.net/manual/en/context.ssl.php
	 */
	
	public function setServer($hostname, $port, $ssl = false, array $sslOptions = [])
	{
		$this->hostname = $hostname;
		$this->port = $port;
		$this->ssl = !!$ssl;
		$this->sslOptions = $sslOptions;
	}
	
	/**
	 * Set identity parameters
	 * 
	 * @param string
	 *   bot nickname
	 * @param string
	 *   bot username
	 * @param string
	 *   gecos string
	 */
	
	public function setIdentity($nick, $user, $gecos)
	{
		$this->nick = $nick;
		$this->username = $user;
		$this->gecos = $gecos;
	}
	
	/**
	 * Add a channel
	 *
	 * @param string
	 *   Channel name including preceding "#"
	 */
	
	public function addChannel($channel)
	{
		$this->channels[] = $channel;
	}
	
	/**
	 * Add permission for a user
	 * 
	 * @param string
	 *   nickname to receive privileges
	 * @param array
	 *   list of permissions, "ALL" is special and grants all permissions
	 */
	
	public function addPermission($nick, array $permissions)
	{
		if ( !isset($this->permissions[$nick]) ) {
			$this->permissions[$nick] = [];
		}
		
		$this->permissions[$nick] = array_merge(
			$this->permissions[$nick],
			$permissions
		);
	}
	
	/**
	 * Add a bot plugin to be loaded at startup
	 *
	 * @param AbstractPlugin
	 */
	
	public function addPlugin(AbstractPlugin $plugin)
	{
		$this->plugins[] = $plugin;
	}
	
	/**
	 * Getter
	 */
	
	public function __get($val)
	{
		if ( is_string($val) && isset($this->$val) ) {
			return $this->$val;
		}
		
		throw new \OutOfBoundsException("Unknown config attribute: $val");
	}
}