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

abstract class AbstractPlugin
{
	protected $bot;
	private $client;
	private $hookUUIDs = [];
	
	abstract protected function loadPlugin();
	
	final public function load(IRC\Bot $bot, IRC\Client $client)
	{
		$client->info(
			"Plugin loaded: " . get_class($this)
		);
		
		$this->bot = $bot;
		$this->client = $client;
		$this->loadPlugin();
		$this->client = null;
	}
	
	final public function unload(IRC\Client $client)
	{
		foreach ( $this->hookUUIDs as $uuid ) {
			$client->unbind($uuid);
		}
		$this->hookUUIDs = [];
	}
	
	final protected function bind($opcode, $callback)
	{
		$this->hookUUIDs[] = $this->client->bind($opcode, $callback);
	}
}