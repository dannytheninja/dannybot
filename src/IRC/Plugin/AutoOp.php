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

class AutoOp extends AbstractPlugin
{
	protected function loadPlugin()
	{
		$this->bind('JOIN', function($irc, $msg)
			{
				// if self, op through chanserv...
				if ( $msg['identity']['nick'] === $irc->identity['nick'] ) {
					$irc->privmsg('ChanServ', "OP {$msg['body']} {$irc->identity['nick']}");
					return;
				}
				
				// else, op if permissions dictate
				$this->bot->checkPermissionsAndNickserv(
					$msg['identity']['nick'],
					'autoop',
					function($irc) use ($msg) {
						$irc->mode($msg['body'], '+o', $msg['identity']['nick']);
					});
			});
	}
}