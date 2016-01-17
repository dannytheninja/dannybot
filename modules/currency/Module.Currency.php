<?php

global $currency_abbrev, $currency_table;

$currency_table = array(
	'timestamp' => 0
);

$currency_abbrev = array(
		'USD' => array('before', '$'),
		'EUR' => array('before', "\xe2\x82\xac"),      // €
		'JPY' => array('either', "\xc2\xa5", 'after'), // ¥
		'GBP' => array('before', "\xc2\xa3"),          // £
		'CAD' => array('before', 'C$'),
		'AUD' => array('before', 'AU$')
	);

$currency_enabled = true;

if ( !function_exists('simplexml_load_string') )
{
	echo("[!!!] {$nick}: currency module: no SimpleXML support found, not enabling plugin\n");
}
else
{
	$irc->bind('PRIVMSG', function($irc, $msg)
		{
			if ( $msg['extra']{0} == '#' )
			{
				currency_event_channel_msg($irc, $msg);
			}
			else
			{
				currency_event_privmsg($irc, $msg);
			}
		});
}

function currency_event_channel_msg($irc, $message)
{
	global $currency_abbrev, $currency_enabled;
	if ( !$currency_enabled )
		return;
	
	if ( preg_match('/^\!convert(?:$| )/', $message['body']) )
	{
		$sender = $message['identity']['nick'];
		$currencies = currency_get_available($irc);
		list($re_before, $re_after) = currency_get_presuffix_rules();
		// check syntax
		if ( preg_match('/^!convert (-?[0-9,]*(?:\.[0-9]+)?) ([^ \t]+) ?(?:in|to|>) ?([^ \t]+) *$/', $message['body'], $match) )
		{
			$amount = doubleval($match[1]);
			if (strtolower($match[2]) === 'degf' && strtolower($match[3]) === 'degc' )
			{
				$degc = (5/9) * ($amount - 32);
				$degc = number_format($degc, 1);
				$irc->privmsg($message['extra'], "$sender: {$amount}\xC2\xB0F = {$degc}\xC2\xB0C");
			}
			else if (strtolower($match[2]) === 'degc' && strtolower($match[3]) === 'degf')
			{
				$degf = ((9/5) * $amount) + 32;
				$degf = number_format($degf, 1);
				$irc->privmsg($message['extra'], "$sender: {$amount}\xC2\xB0C = {$degf}\xC2\xB0F");
			}
			else
			{
				$from = currency_sym_to_abbrev($match[2]);
				$to = currency_sym_to_abbrev($match[3]);
				$amount_fmt = number_format($amount, 2);
				$irc->privmsg($message['extra'], "$sender: " . currency_format($amount_fmt, $from) . " in {$to}: " . currency_format(currency_convert($amount, $from, $to, $irc), $to));
			}
		}
		// match up against prefixes/suffixes
		else if ( preg_match("/^!convert $re_before([0-9,]*(?:\.[0-9]+)?)$re_after *(?:in|to|>) *([^ \t]+) *$/", $message['body'], $match) )
		{
			$amount = doubleval($match[2]);
			$amount_fmt = number_format($amount, 2);
			$to = currency_sym_to_abbrev($match[4]);
			// make sure we didn't get both (whoops) or neither
			if ( ( !empty($match[1]) && !empty($match[3]) ) || ( empty($match[1]) && empty($match[3]) ) )
			{
				currency_send_help($irc, $sender);
				return;
			}
			$marker = ( !empty($match[1]) ) ? $match[1] : $match[3];
			$from = currency_sym_to_abbrev($marker);
			$irc->privmsg($message['extra'], "$sender: " . currency_format($amount_fmt, $from) . " in {$to}: " . currency_format(currency_convert($amount, $from, $to, $irc), $to));
		}
		else
		{
			currency_send_help($irc, $sender);
		}
		
		throw new IRCBreakHooks;
	}
}

function currency_event_privmsg($irc, $message)
{
	global $currency_enabled;
	$user =& $message['identity']['nick'];
	$auth_mod = check_permissions($user, 'currencymod');
	$table = currency_fetch_dataset($irc);
	$lastupdate = ( $table['timestamp'] === 0 ) ? 'never' : date('Y-m-d H:i:s T', $table['timestamp']);
	if ( strtolower($message['body']) === 'about currency' )
	{
		$irc->privmsg($user, "<lime><b>Currency conversion plugin</b></lime> v0.1 for <b>{$irc->identity['nick']}</b> - <blue>copyright \xc2\xa9 2009</blue>; Thanks to <u>Dan A.</u> for suggesting");
		$irc->privmsg($user, "<b>Last conversion table update:</b> $lastupdate");
		$irc->privmsg($user, "Symbols not working? Make sure your IRC client supports Unicode.");
		currency_send_help($irc, $user);
		
		throw new IRCBreakHooks;
	}
	else if ( strtolower($message['body']) === 'disable currency' && $auth_mod )
	{
		$currency_enabled = false;
		$irc->privmsg($user, "Currency conversion set to <b>disabled</b>.");
		
		throw new IRCBreakHooks;
	}
	else if ( strtolower($message['body']) === 'enable currency' && $auth_mod )
	{
		$currency_enabled = true;
		$irc->privmsg($user, "Currency conversion set to <b>enabled</b>.");
		
		throw new IRCBreakHooks;
	}
}

function currency_get_presuffix_rules()
{
	global $currency_abbrev;
	
	static $result = false;
	if ( is_array($result) )
		return $result;
	
	// build before/after matches
	global $currency_abbrev;
	$re_before = array();
	$re_after = array();
	foreach ( $currency_abbrev as $curr )
	{
		switch($curr[0])
		{
			case 'before': $re_before[] = preg_quote($curr[1]); break;
			case 'after':  $re_after[] = preg_quote($curr[1]); break;
			case 'either':
			case 'both':
				$re_before[] = preg_quote($curr[1]);
				$re_after[] = preg_quote($curr[1]);
				break;
		}
	}
	$re_before = '(' . implode('|', $re_before) . ')?';
	$re_after = '(' . implode('|', $re_after) . ')?';
	
	$result = array($re_before, $re_after);
	return $result;
}

function currency_fetch_dataset($irc)
{
	global $currency_table;
	
	if ( $currency_table['timestamp'] + 86400 >= time() )
	{
		return $currency_table;
	}
	
	$irc->info("Fetching latest currency conversion data from api.finance.xaviermedia.com");
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "http://api.finance.xaviermedia.com/api/latest.xml");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$body = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ( $code !== 200 )
	{
		return false;
	}
	$xml = simplexml_load_string($body);
	if ( !$xml )
		return false;
	
	foreach ( $xml->exchange_rates->fx as $fx )
	{
		$currency_table[ strval($fx->currency_code) ] = doubleval($fx->rate);
	}
	
	$currency_table['timestamp'] = time();
	
	return $currency_table;
}

function currency_get_available($irc)
{
	$table = currency_fetch_dataset($irc);
	if ( !$table )
		return array('unavailable (can\'t get current table)');
	
	unset($table['timestamp']);
	return array_keys($table);
}

function currency_convert($amount, $from, $to, $irc)
{                      
	$table = currency_fetch_dataset($irc);
	if ( !$table )
		return 'unavailable (can\'t get current table)';
	
	$from = strtoupper($from);
	$to = strtoupper($to);
	
	if ( !isset($table[$from]) || $from == 'timestamp' )
	{
		return "unknown currency: $from";
	}
	if ( !isset($table[$to]) || $from == 'timestamp' )
	{
		return "unknown currency: $to";
	}
	// convert to base
	$amount = $amount / $table[$from];
	// convert to target
	$amount = $amount * $table[$to];
	
	return number_format($amount, 2);
}

function currency_sym_to_abbrev($sym)
{
	global $currency_abbrev;
	foreach ( $currency_abbrev as $cur => $fmt )
	{
		if ( $sym === $fmt[1] )
			return $cur;
	}
	return strtoupper($sym);
}

function currency_send_help($irc, $user)
{
	$currencies = currency_get_available($irc);
	$irc->privmsg($user, censor_words("<b>Syntax:</b> !convert <u>amount</u> <u>from</u> to <u>to</u>"));
	$irc->privmsg($user, censor_words("<b>Currencies</b> (<u>from</u> and <u>to</u>): " . implode(' ', $currencies)));
};

function currency_format($amount, $currency)
{
	global $currency_abbrev;
	if ( !preg_match('/^([0-9,]*(?:\.[0-9]+)?)$/', $amount) )
		return $amount;
	
	$currency = strtoupper($currency);
	
	if ( isset($currency_abbrev[ $currency ]) )
	{
		$format =& $currency_abbrev[ $currency ];
		switch($format[0])
		{
			case 'before':
				return "{$format[1]}{$amount}";
			case 'after':
				return "{$amount}{$format[1]}";
			case 'either':
				if ( isset($format[2]) )
				{
					switch($format[2])
					{
						case 'before': return "{$format[1]}{$amount}";
						case 'after':  return "{$amount}{$format[1]}";
					}
				}
		}
	}
	return $amount;
}
