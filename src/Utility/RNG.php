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

class RNG
{
	private $backend;
	
	private $entropyFactor = 32;
	
	/**
	 * Constructor.
	 */
	
	public function __construct(RNGInterface $backend = null)
	{
		if ( !$backend ) {
			if ( file_exists('/dev/urandom') && is_readable('/dev/urandom') ) {
				$backend = new RNGBackend\CharacterDevice('/dev/urandom');
			}
			else if ( file_exists('/dev/random') && is_readable('/dev/urandom') ) {
				$backend = new RNGBackend\CharacterDevice('/dev/random');
			}
			else {
				$backend = new RNGBackend\PhpMtrand;
			}
		}
		$this->backend = $backend;
	}
	
	/**
	 * Set the entropy factor. n bytes will be collected from the backend and
	 * XORed together per output byte in randomBytes().
	 *
	 * @param int
	 *   Number of bytes to read from backend per output byte.
	 * @return $this
	 */
	
	public function setEntropyFactor($factor)
	{
		if ( !is_int($factor) ) {
			throw new \InvalidArgumentException(
				"Utility\\setEntropyFactor(): expected integer"
			);
		}
		
		if ( $factor < 1 ) {
			throw new \OutOfBoundsException(
				"Utility\\setEntropyFactor(): must be at least 1"
			);
		}
		
		$this->entropyFactor = $factor;
		
		return $this;
	}
	
	/**
	 * Generate a random string
	 *
	 * @param int
	 *   Length of string
	 */
	
	public function randomBytes($len)
	{
		$buf = '';
		while ( strlen($buf) < $len ) {
			$byte = 0;
			for ( $i = 0; $i < $this->entropyFactor; $i++ ) {
				$byte ^= ord($this->backend->getByte());
			}
			$buf .= chr($byte);
		}
		return $buf;
	}
}