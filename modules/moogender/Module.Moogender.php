<?php

$irc->bind('PRIVMSG', function($irc, $msg)
	{
		static $gender = 'male';
		
		if ( $msg['extra']{0} !== '#' )
			return;
		
		if ( $msg['body'] === '!sexchange' )
		{
			$gender = $gender == 'male' ? 'female' : 'male';
			$irc->privmsg($msg['extra'], "Moo's sex is now <b>$gender</b>.");
			
			throw new IRCBreakHooks;
		}
	});
