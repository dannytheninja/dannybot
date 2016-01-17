<?php

// debug tools! :D
$irc->bind('PRIVMSG', function($irc, $msg)
	{
		$args = preg_split('/\s+/', $msg['body']);
		list($cmd) = $args;
		switch($cmd)
		{
			case 'NAMES':
				if ( isset($irc->joined_channels[$args[1]]) && isset($irc->joined_channels[$args[1]]['names']) )
				{
					$irc->privmsg($msg['identity']['nick'], implode(' ', $irc->joined_channels[$args[1]]['names']));
				}
				else
				{
					$irc->privmsg($msg['identity']['nick'], "Not joined to {$args[1]}.");
				}
				throw new IRCBreakHooks;
				break;
			case 'WHOIS':
				$irc->whois($args[1], function($whois) use ($irc, $msg)
					{
						$irc->info(print_r($whois, true));
					});
				throw new IRCBreakHooks;
				break;
			case 'QUOTE':
				array_shift($args);
				$quote = implode(' ', $args);
				
				check_permissions_and_nickserv($msg['identity']['nick'], 'quote', $irc, function($irc) use ($quote)
					{
						try
						{
							$irc->quote("$quote");
						}
						catch ( IRCIKnowThisIsDangerousException $e )
						{
						}
					});
				
				throw new IRCBreakHooks;
				break;
		}
	});
