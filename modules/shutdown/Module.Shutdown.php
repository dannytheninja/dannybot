<?php

// quit IRC when we get the PM "shutdown"
$irc->bind('PRIVMSG', function($irc, $msg)
	{
		if ( $msg['body'] === 'shutdown' )
		{
			// verify nick
			if ( check_permissions($msg['identity']['nick'], 'shutdown') )
			{
				// verify nickserv authentication
				$irc->whois($msg['identity']['nick'], function($whois) use ($irc)
					{
						if ( isset($whois['services_identity']) && check_permissions($whois['services_identity']['idnick'], 'shutdown') )
						{
							// nickserv auth is good
							$quitmsg = "Shutting down at request of {$whois['identity']['nick']} (identified to NickServ as {$whois['services_identity']['idnick']})";
							$irc->info($quitmsg);
							throw new IRCQuitSignal($quitmsg);
						}
						else
						{
							$irc->privmsg($whois['identity']['nick'], "Access denied.");
						}
					});
			}
			else
			{
				$irc->privmsg($msg['identity']['nick'], "Access denied.");
			}
			
			throw new IRCBreakHooks;
		}
	});

