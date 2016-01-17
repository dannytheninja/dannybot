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
 
namespace DannyTheNinja\Utility;

/**
 * Universally Unique Identifier (UUID) generator
 */

class UUID
{
	private $rng;
	
	private $uuid;
	
	/**
	 * Constructor.
	 *
	 * @param string
	 *   Optional UUID to seed with. If omitted or invalid, a random one is
	 *   generated.
	 *
	 * @param DannyTheNinja\Utility\RNG
	 *   Optional RNG
	 */
	
	public function __construct($uuid = null, RNG $rng = null)
	{
		$this->rng = $rng ?: new RNG;
		
		if ( is_string($uuid) ) {
			if ( strlen($uuid) === 16 ) {
				$this->uuid = $uuid;
			}
			if ( preg_match('/^[a-f0-9]{32}$/i', preg_replace('/[^a-f0-9]/i', '', $uuid)) ) {
				$this->uuid = hex2bin(preg_replace('/[^a-f0-9]/i', '', $uuid));
			}
		}
		
		if ( empty($this->uuid) ) {
			$this->uuid = $this->rng->randomBytes(16);
		}
	}
	
	/**
	 * Format as string
	 */
	
	public function asString()
	{
		$lengths = [4, 2, 2, 2, 6];
		$sections = [];
		$pos = 0;
		foreach ( $lengths as $len ) {
			$sections[] = bin2hex(
				substr($this->uuid, $pos, $len)
			);
			$pos += $len;
		}
		return implode('-', $sections);
	}
	
	/**
	 * Format as binary
	 */
	
	public function asBinary()
	{
		return $this->uuid;
	}
}