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

class Debug extends AbstractPlugin
{
	protected function loadPlugin()
	{
		// debug tools! :D
		$this->bind('PRIVMSG', function($irc, $msg)
			{
				$args = preg_split('/\s+/', $msg['body']);
				list($cmd) = $args;
				switch($cmd)
				{
					case 'NAMES':
						if ( isset($irc->joined_channels[$args[1]]) && isset($irc->joined_channels[$args[1]]['names']) ) {
							$irc->privmsg($msg['identity']['nick'], implode(' ', $irc->joined_channels[$args[1]]['names']));
						}
						else {
							$irc->privmsg($msg['identity']['nick'], "Not joined to {$args[1]}.");
						}
						throw new IRC\Signal\BreakHooks;
						break;
					case 'WHOIS':
						$irc->whois(
							$args[1],
							function($whois) use ($irc, $msg) {
								$irc->info(print_r($whois, true));
							});
						throw new IRC\Signal\BreakHooks;
						break;
					case 'QUOTE':
						array_shift($args);
						$quote = implode(' ', $args);
						
						$this->bot->checkPermissionsAndNickserv(
							$msg['identity']['nick'],
							'quote', 
							function($irc) use ($quote) {
								$irc->quote("$quote");
							});
						
						throw new IRC\Signal\BreakHooks;
						break;
				}
			});
	}
}