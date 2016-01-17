<?php

function check_permissions($nick, $permission)
{
	global $config;
	
	if ( !isset($config['permissions'][$nick]) )
		return false;
	
	return in_array('ALL', $config['permissions'][$nick]) || in_array($permission, $config['permissions'][$nick]);
}

function check_permissions_and_nickserv($nick, $permission, $irc, $callback)
{
	if ( check_permissions($nick, $permission) )
	{
		$irc->whois($nick, function($whois) use ($irc, $nick, $permission, $callback)
			{
				if ( isset($whois['services_identity']) && check_permissions($whois['services_identity']['idnick'], $permission) )
				{
					call_user_func($callback, $irc);
				}
			});
	}
}

$censored_words = array('cock', 'fuck', 'cunt', 'bitch', 'nigger');

function censor_words($text)
{
	global $censored_words;
	foreach ( $censored_words as $word )
	{
		$replacement = substr($word, 0, 1) . preg_replace('/./', '*', substr($word, 1));
		while ( stristr($text, $word) )
		{
			$text = preg_replace("/$word/i", $replacement, $text);
		}
	}
	return $text;
}
