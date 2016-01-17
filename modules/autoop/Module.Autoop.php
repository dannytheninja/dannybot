<?php

$irc->bind('JOIN', function($irc, $msg)
	{
		// if self, op through chanserv...
		if ( $msg['identity']['nick'] === $irc->identity['nick'] )
		{
			$irc->privmsg('ChanServ', "OP {$msg['body']} {$irc->identity['nick']}");
			return;
		}
		
		// else, op if permissions dictate
		check_permissions_and_nickserv($msg['identity']['nick'], 'autoop', $irc, function($irc) use ($msg)
			{
				$irc->mode($msg['body'], '+o', $msg['identity']['nick']);
			});
	});
