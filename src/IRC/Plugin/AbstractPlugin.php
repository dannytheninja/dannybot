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

namespace DannyTheNinja\IRC\Plugin;
use DannyTheNinja\IRC;

/**
 * Foundation for all IRC plugins.
 */

abstract class AbstractPlugin
{
	/** @var DannyTheNinja\IRC\Bot */
	protected $bot;
	
	/** @var DannyTheNinja\IRC\Client */
	private $client;
	
	/** @var array */
	private $hookUUIDs = [];
	
	/**
	 * Load the plugin. Your method should make any necessary calls to the
	 * "bind" method here.
	 * 
	 * @abstract
	 */
	
	abstract protected function loadPlugin();
	
	/**
	 * Called from Bot->loadPlugins().
	 * 
	 * @final
	 */
	
	final public function load(IRC\Bot $bot, IRC\Client $client)
	{
		$this->bot = $bot;
		$this->client = $client;
		$this->loadPlugin();
		$this->client = null;
		
		$client->info(
			"Plugin loaded: " . get_class($this)
		);
	}
	
	/**
	 * Unload this plugin.
	 *
	 * @final
	 */
	
	final public function unload(IRC\Client $client)
	{
		foreach ( $this->hookUUIDs as $uuid ) {
			$client->unbind($uuid);
		}
		$this->hookUUIDs = [];
	}
	
	/**
	 * Bind to an IRC event.
	 *
	 * @param mixed
	 *   IRC opcode. Can be a textual event (i.e. "PING", "PRIVMSG", etc.) or
	 *   one of the numeric opcodes from DannyTheNinja\IRC\Opcode, or an array
	 *   of multiple of the above.
	 * @param callback
	 *   Function to call when those events happen in IRC. It will be provided
	 *   2 parameters: the IRC\Client instance, and an array with details about
	 *   the message.
	 * @final
	 */
	
	final protected function bind($opcode, $callback)
	{
		$this->hookUUIDs[] = $this->client->bind($opcode, $callback);
	}
}