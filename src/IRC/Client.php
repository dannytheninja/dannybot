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

namespace DannyTheNinja\IRC;

use DannyTheNinja\Utility\UUID;
use DannyTheNinja\Utility\RNG;

/**
 * Generic IRC client library.
 */

class Client
{
	private $rng;
	
	private $socket;
	private $hooks = [];
	private $identity;
	
	// List of channels we're joined to, and information about them
	protected $joined_channels = [];
	
	// whois data cache
	protected $whois_cache = [];
	
	// Regular expression fragments, used to ease readability of message parser code
	const RE_FRAG_HOSTNAME = '(?:(?:[a-z0-9-]+\.)*(?:[a-z0-9-]+)|(?:[0-9a-z:]+))';
	const RE_FRAG_NICK = '[\w\|_-]+';
	const RE_FRAG_USERNAME = '~?[\w_-]+';
	
	/**
	 * Constructor - only used for dependency injection
	 * @param DannyTheNinja\Utility\RNG;
	 */
	
	public function __construct(RNG $rng = null)
	{
		$this->rng = $rng ?: new RNG;
	}
	
	/**
	 * Get overloader. Allows outside functions to pull protected variables, but not write them.
	 */
	
	public function __get($name)
	{
		if ( isset($this->$name) && !in_array($name, ['socket', 'hooks']) )
			return $this->$name;
	}
	
	/**
	 * Write a line (or multiple lines) to the socket.
	 * @param string
	 * @return null
	 */
	
	protected function write($msg)
	{
		if ( strlen($msg) > 1022 )
			throw new ClientException("Not permitted to write messages longer than 1024 bytes");
		
		echo "\x1B[0;34;1m->\x1B[0m $msg\n";
		while ( strlen($msg) )
		{
			$nw = fputs($this->socket, "$msg\r\n");
			$msg = substr($msg, $nw);
		}
	}
	
	/**
	 * Write an information line (or lines) to the terminal.
	 * @param string
	 */
	
	public function info($msg)
	{
		foreach ( explode("\n", trim($msg)) as $line )
			echo "\x1B[0;36;1mii\x1B[0m $line\n";
	}
	
	/**
	 * Connect to an IRC server.
	 * @param string Hostname
	 * @param int Port number
	 * @param array Identity. Should contain keys "nick", "username" and "gecos".
	 * @param bool If true, SSL will be used.
	 * @param array SSL context options
	 */
	
	public function connect($host, $port, $identity, $ssl = false, $ssl_options = [])
	{
		if ( $ssl ) {
			$sslctx = stream_context_create([
					'ssl' => $ssl_options
				]);
			$host = "tls://$host";
			$this->socket = stream_socket_client("$host:$port", $errno, $errstr, ini_get('default_socket_timeout'), STREAM_CLIENT_CONNECT, $sslctx);
		}
		else {
			$this->socket = fsockopen($host, $port);
		}
		if ( !$this->socket )
			throw new ClientException("Failed to open socket");
		
		$this->write("NICK {$identity['nick']}");
		$this->write("USER {$identity['user']} 0 * :{$identity['gecos']}");
		
		$this->identity = $identity;
		
		$this->setup_default_hooks();
	}
	
	private function setup_default_hooks()
	{
		// handle pings
		$this->bind('PING', function($irc, $msg)
			{
				$irc->write("PONG :{$msg['body']}");
			});
		
		// be good when killed
		$this->bind('KILL', function($irc, $msg)
			{
				throw new Signal\Quit("Bot was killed :(");
			});
		
		// channel join handler
		$this->bind(
			'JOIN',
			function($irc, $msg) {
				$channel = $msg['body'];
				
				if ( $msg['identity']['nick'] === $this->identity['nick'] ) {
					// log that the channel was joined
					$irc->info("Channel $channel was joined successfully.");
					$irc->joined_channels[$channel] = [ 'names' => [] ];
					
					// set a composite hook for the NAMES list
					$irc->bind(
						[ Opcode::OP_NAMES_LIST, Opcode::OP_NAMES_END ],
						function($irc, $msg) use ($channel) {
							// verify that this line pertains to this channel
							$mchannel = preg_replace("/^{$this->identity['nick']} (= )?/", '', $msg['extra']);
							if ( $mchannel !== $channel ) {
								return;
							}
							
							if ( $msg['opcode'] === Opcode::OP_NAMES_END ) {
								throw new Signal\Unhook();
							}
							
							foreach ( preg_split('/\s+/', $msg['body']) as $nick ) {
								$irc->joined_channels[$channel]['names'][] = preg_replace('/^[@\+&~%]/', '', $nick);
							}
						});
				}
				else {
					$this->joined_channels[$channel]['names'][] = $msg['identity']['nick'];
				}
			});
		
		// channel part/kick handlers
		$this->bind(
			'PART',
			function($irc, $msg) {
				$channel =& $msg['extra'];
				if ( $msg['identity']['nick'] === $irc->identity['nick'] ) {
					// we left
					unset($irc->joined_channels[$channel]);
				}
				else {
					// someone else left
					foreach ( $irc->joined_channels[$channel]['names'] as $i => $name ) {
						if ( $name === $msg['identity']['nick'] ) {
							unset($irc->joined_channels[$channel]['names'][$i]);
						}
					}
					
					$irc->joined_channels[$channel]['names'] = array_values($irc->joined_channels[$channel]['names']);
				}
			});
		
		$this->bind(
			'KICK',
			function($irc, $msg) {
				list($channel, $who) = preg_split('/\s+/', $msg['extra']);
				
				if ( $who === $irc->identity['nick'] ) {
					// we were kicked
					unset($irc->joined_channels[$channel]);
				}
				else {
					// someone else left
					foreach ( $irc->joined_channels[$channel]['names'] as $i => $name ) {
						if ( $name === $who ) {
							unset($irc->joined_channels[$channel]['names'][$i]);
						}
					}
					
					$irc->joined_channels[$channel]['names'] = array_values($irc->joined_channels[$channel]['names']);
				}
			});
		
		// quit handler - for when a user quits IRC
		$this->bind(
			'QUIT',
			function($irc, $msg) {
				foreach ( $irc->joined_channels as $channame => $channel ) {
					if ( in_array($msg['identity']['nick'], $channel['names']) ) {
						foreach ( $channel['names'] as $i => $name ) {
							if ( $msg['identity']['nick'] === $name ) {
								$irc->info("$name has quit IRC, removing them from $channame");
								unset($channel['names'][$i]);
							}
						}
					}
				}
			});
		
		// nick changes
		$this->bind(
			'NICK',
			function($irc, $msg) {
				if ( $msg['identity']['nick'] === $irc->identity['nick'] ) {
					// our own nick changed
					$irc->identity['nick'] = $msg['body'];
				}
				
				$oldnick =& $msg['identity']['nick'];
				$newnick =& $msg['body'];
				
				foreach ( $this->joined_channels as &$chan ) {
					foreach ( $chan['names'] as &$name ) {
						if ( $name === $oldnick ) {
							$name = $newnick;
						}
					}
					unset($name);
				}
				unset($chan);
			});
		
		// nick conflict
		$this->bind(
			Opcode::OP_NICK_IN_USE,
			function ($irc, $msg) {
				$suffix = substr(
							preg_replace('/[^A-Za-z0-9]+/', '', base64_encode($this->rng->randomBytes(32))),
							0,
							6
							);
				$oldnick = $this->identity['nick'];
				$this->set_nick($newnick = "{$oldnick}_{$suffix}");
				$this->identity['nick'] = $newnick;
			}
		);
	}
	
	/**
	 * Change the bot's nick.
	 * @param string
	 */
	
	public function set_nick($nick)
	{
		$this->write("NICK $nick");
	}
	
	/**
	 * Joins a channel. This will also set appropriate hooks to gather information about the channel, and fill $irc->joined_channels asynchronously.
	 * If you want to be notified when the channel finishes being joined, bind to Opcode::OP_NAMES_END.
	 * @param string Channel name
	 */
	
	public function join($channel)
	{
		if ( isset($this->joined_channels[$channel]) ) {
			return;
		}
		
		$this->write("JOIN $channel");
	}
	
	/**
	 * Leaves a channel.
	 * @param string Channel name
	 */
	
	public function part($channel, $reason = '')
	{
		if ( !isset($this->joined_channels[$channel]) ) {
			return;
		}
		
		$this->write("PART $channel :$reason");
	}
	
	/**
	 * Send a message. Newlines will be automatically converted, but beware - no flood control is present!
	 * @param string Target (channel or nick)
	 * @param string Message to send
	 */
	
	public function privmsg($target, $msg)
	{
		if ( strstr($msg, "\r") ) {
			throw new ClientException("Carriage returns are not permitted in private messages");
		}
		
		foreach ( explode("\n", trim($msg)) as $line )
		{
			$line = $this->format_message($line);
			$this->write("PRIVMSG $target :$line");
		}
	}
	
	/**
	 * The event loop. After connecting and binding to all the events you want to be notified about, call this method
	 * to have your bot do its thing.
	 */
	
	public function event_loop()
	{
		while ( !feof($this->socket) ) {
			try {
				$line = fgets($this->socket, 1024);
				if ( substr($line, -2) === "\r\n" ) {
					// it's a message!
					$line = trim($line);
					if ( $msg = $this->parse_message($line) ) {
						echo "\x1B[0;32;1m<-\x1B[0m $line\n";
						$this->run_hooks($msg['type'], $msg['opcode'], $msg);
					}
					else {
						echo "\x1B[0;31;1m<-\x1B[0m $line\n";
					}
				}
			}
			catch ( Signal\Quit $iqs ) {
				$this->write("QUIT :{$iqs->getQuitMessage()}");
				break;
			}
			catch ( Signal\BreakLoop $ibls ) {
				return;
			}
		}
		
		fclose($this->socket);
		$this->socket = false;
	}
	
	/**
	 * Execute all hooks associated with an opcode.
	 * @access private
	 */
	
	private function run_hooks($type, $opcode, $msg)
	{
		// Run all simple hooks.
		if ( isset($this->hooks[$opcode]) ) {
			foreach ( $this->hooks[$opcode] as $i => $func ) {
				if ( !is_callable($func) ) {
					continue;
				}
				
				try {
					call_user_func($func, $this, $msg);
				}
				catch ( Signal\Unhook $ius ) {
					// "IRCUnhookSignal" will cause the hook to be removed. The concept of temporary hooks
					// allows the introduction of polling loops that grab a finite amount of information, then
					// are finished.
					unset($this->hooks[$opcode][$i]);
				}
				catch ( Signal\BreakHooks $ibh ) {
					// "IRCBreakHooks" will cause all further hooks for this event to stop being processed. This
					// should only be used in cases where you are, for example, handling user input and responding
					// to a command.
					break;
				}
			}
		}
		
		// Run all composite hooks (hooks bound to more than one opcode)
		if ( isset($this->hooks['composite']) ) {
			foreach ( $this->hooks['composite'] as $i => $hook ) {
				if ( in_array($opcode, $hook['opcodes']) ) {
					if ( !is_callable($hook['function']) ) {
						continue;
					}
					
					// Same deal as before
					try {
						call_user_func($hook['function'], $this, $msg);
					}
					catch ( Signal\Unhook $ius ) {
						unset($this->hooks['composite'][$i]);
					}
					catch ( Signal\BreakHooks $ibh ) {
						break;
					}
				}
			}
		}
	}
	
	/**
	 * Parse as much of an incoming line as we can. You don't need to call this, any message
	 * sent to your module has already been put through it.
	 * @param string Line
	 * @return array|bool parsed message array on success, false on failure
	 * @access private
	 */
	
	private function parse_message($line)
	{
		if ( preg_match('/^:(' . self::RE_FRAG_HOSTNAME . ') (\d+|[A-Z]+) (.*?) :(.*)$/', $line, $match) ) {
			return [
				'type' => 'server',
				'line' => $match[0],
				'server' => $match[1],
				'opcode' => $match[2],
				'extra' => $match[3],
				'body' => $match[4]
			];
		}
		else if ( preg_match('/^:(' . self::RE_FRAG_HOSTNAME . ') (\d+|[A-Z]+) (.*?)$/', $line, $match) ) {
			return [
				'type' => 'server',
				'line' => $match[0],
				'server' => $match[1],
				'opcode' => $match[2],
				'extra' => $match[3],
				'body' => false
			];
		}
		else if ( preg_match('/^:(' . self::RE_FRAG_NICK . ')!(' . self::RE_FRAG_USERNAME . ')@(' . self::RE_FRAG_HOSTNAME . ') ([A-Z]+) (.*?) :(.*)$/', $line, $match) ) {
			return [
				'type' => 'user',
				'line' => $match[0],
				'identity' => [
					'nick' => $match[1],
					'user' => $match[2],
					'hostname' => $match[3]
				],
				'opcode' => $match[4],
				'extra' => $match[5],
				'body' => $match[6]
			];
		}
		else if ( preg_match('/^:(' . self::RE_FRAG_NICK . ')!(' . self::RE_FRAG_USERNAME . ')@(' . self::RE_FRAG_HOSTNAME . ') ([A-Z]+) :(.*)$/', $line, $match) ) {
			return [
				'type' => 'user',
				'line' => $match[0],
				'identity' => [
					'nick' => $match[1],
					'user' => $match[2],
					'hostname' => $match[3]
				],
				'opcode' => $match[4],
				'extra' => false,
				'body' => $match[5]
			];
		}
		else if ( preg_match('/^:(' . self::RE_FRAG_NICK . ')!(' . self::RE_FRAG_USERNAME . ')@(' . self::RE_FRAG_HOSTNAME . ') ([A-Z]+) (.*?)$/', $line, $match) ) {
			return [
				'type' => 'user',
				'line' => $match[0],
				'identity' => [
					'nick' => $match[1],
					'user' => $match[2],
					'hostname' => $match[3]
				],
				'opcode' => $match[4],
				'extra' => $match[5]
			];
		}
		else if ( preg_match('/^([A-Z]+) :(.*)$/', $line, $match) ) {
			return [
				'type' => 'primitive',
				'line' => $match[0],
				'opcode' => $match[1],
				'body' => $match[2]
			];
		}
		
		return false;
	}
	
	/**
	 * Bind to an opcode. When a message with this opcode arrives, your function will be called.
	 * Parameters to the callback are the IRCClient object and the parsed message.
	 * @param mixed
	 *   Opcode - string or array of strings. If it's a numeric code, use an
	 *   Opcode constant. If this is an array of opcodes, the same function will
	 *   be called whichever opcode is used. Throwing IRC\Signal\Unhook from
	 *   your callback will cause the entire hook to be removed.
	 * @param callback
	 *   Function that will be called.
	 * @return string
	 *   UUID identifying this bound hook. Calling unbind() later will remove
	 *   the hook.
	 */
	
	public function bind($opcode, $function)
	{
		$uuid = (new UUID)->asString();
		if ( is_array($opcode) ) {
			// composite hook - bound to multiple opcodes
			if ( !isset($this->hooks['composite']) ) {
				$this->hooks['composite'] = array();
			}
			
			$this->hooks['composite'][$uuid] = [
				'opcodes' => $opcode,
				'function' => $function
			];
		}
		else {
			// simple hook - bound to one opcode
			if ( !isset($this->hooks[$opcode]) ) {
				$this->hooks[$opcode] = [];
			}
			
			$this->hooks[$opcode][$uuid] = $function;
		}
		
		$this->info("Hook bound: $uuid to opcodes " . json_encode($opcode));
		
		return $uuid;
	}
	
	/**
	 * Unbind a previously bound hook.
	 *
	 * @param string
	 *   UUID
	 */
	
	public function unbind($uuid)
	{
		foreach ( $this->hooks as $opcode => $hooks ) {
			foreach ( $hooks as $hook_uuid => $callback ) {
				if ( $hook_uuid === $uuid ) {
					unset($hooks[$hook_uuid]);
					return;
				}
			}
		}
		throw new \RuntimeException(
			"There is no bound hook with the UUID \"$uuid\""
		);
	}
	
	/**
	 * Collect whois data on a user.
	 * @param string Nickname
	 * @param callback Function that will be called when the whois data finishes flowing in. The first parameter will be an array of whois data.
	 */
	
	public function whois($nick, $callback)
	{
		if ( isset($this->whois_cache[$nick]) && $this->whois_cache[$nick]['timestamp'] + 300 > time() ) {
			call_user_func($callback, $this->whois_cache[$nick]);
			return;
		}
		
		unset($this->whois_cache[$nick]);
		
		$this->write("WHOIS $nick");
		
		$this->whois_cache[$nick] = array();
		
		$this->bind([
				Opcode::OP_WHOIS_IDENTITY,
				Opcode::OP_WHOIS_CHANNELS,
				Opcode::OP_WHOIS_SERVER,
				Opcode::OP_WHOIS_SERVICES_IDENTITY,
				Opcode::OP_WHOIS_END
			],
			function($irc, $msg) use ($callback, $nick) {
				switch($msg['opcode']) {
					case Opcode::OP_WHOIS_IDENTITY:
						list(,$snick,$user,$hostname) = preg_split('/\s+/', $msg['extra']);
						$gecos = $msg['body'];
						$irc->whois_cache[$nick]['identity'] = [
							'nick' => $snick,
							'user' => $user,
							'hostname' => $hostname,
							'gecos' => $gecos
						];
						
						break;
					case Opcode::OP_WHOIS_CHANNELS:
						foreach ( preg_split('/\s+/', $msg['body']) as $channel ) {
							$irc->whois_cache[$nick]['channels'][] = $channel;
						}
						break;
					case Opcode::OP_WHOIS_SERVER:
						list(,,$server) = preg_split('/\s+/', $msg['extra']);
						$irc->whois_cache[$nick]['server'] = [
							'hostname' => $server,
							'location' => $msg['body']
						];
						break;
					case Opcode::OP_WHOIS_SERVICES_IDENTITY:
						list(,,$idnick) = preg_split('/\s+/', $msg['extra']);
						$irc->whois_cache[$nick]['services_identity'] = [
							'idnick' => $idnick
						];
						break;
					case Opcode::OP_WHOIS_END:
						$irc->whois_cache[$nick]['timestamp'] = time();
						call_user_func($callback, $irc->whois_cache[$nick]);
						throw new Signal\Unhook();
						break;
				}
			});
	}
	
	/**
	 * Parse bold (<b>...</b>) tags and color tags in a text into IRC speak, and process /me
	 * commands. Colors are <cyan>...</cyan>, specify background with <fg:bg>...</fgcolor:bgcolor>.
	 * Valid colors are white, black, navy, green, red, maroon, purple, orange, yellow, lime, teal,
	 * aqua, cyan, blue, pink, grey, and silver
	 * @param string Text to filter
	 * @return string
	 * @access private
	 */
	
	private function format_message($text)
	{
		$text = preg_replace('#<b>(.*?)</b>#is', "\x02$1\x02", $text);
		$text = preg_replace('#<u>(.*?)</u>#is', "\x1f$1\x1f", $text);
		
		if ( preg_match('#^/me #i', $text) ) {
			$text = "\x01ACTION " . preg_replace('#^/me #i', '', $text) . "\x01";
		}
		
		$supportedcolors = ['white', 'black', 'navy', 'green', 'red', 'maroon',
			'purple', 'orange', 'yellow', 'lime', 'teal', 'cyan', 'blue',
			'pink', 'grey', 'silver'];
		
		$colors = implode('|', $supportedcolors);
		$supportedcolors = array_flip($supportedcolors);
		preg_match_all("#<((?:$colors)(?::(?:$colors))?)>(.*?)</\\1>#is", $text, $matches);
		
		foreach ( $matches[0] as $i => $match ) {
			preg_match("#<(?:($colors)(?::($colors))?)>#i", $match, $colordata);
			$fgcolor = $supportedcolors[$colordata[1]];
			$bgcolor = $colordata[1] ? $supportedcolors[$colordata[2]] : '';
			$fgcolor = ( $fgcolor < 10 ) ? "0$fgcolor" : "$fgcolor";
			if ( is_int($bgcolor) )
				$bgcolor = ( $bgcolor < 10 ) ? ",0$bgcolor" : ",$bgcolor";
			$text = str_replace($match, "\x03{$fgcolor}{$bgcolor}{$matches[2][$i]}\x03", $text);
		}
		
		return $text;
	}
	
	/**
	 * "QUOTE" - frontend for write().
	 * @param string
	 *   Line to write
	 */
	
	public function quote($line)
	{
		$this->write($line);
	}
	
	/**
	 * Set mode on an object.
	 * @param string Object name
	 * @param string Mode string
	 * @param string Extra parameters, defaults to empty string
	 */
	
	public function mode($object, $mode, $extra = '')
	{
		$this->write("MODE $object $mode :$extra");
	}
	
	/**
	 * Set mode on current nick
	 * @param string mode
	 */
	
	public function umode($mode)
	{
		$this->write("MODE {$this->identity['nick']} $mode");
	}
	
	/**
	 * Destructor.
	 */
	
	public function __destruct()
	{
		if ( $this->socket ) {
			$this->write("QUIT :IRC\\Client instance is being destroyed");
			while ( !feof($this->socket) ) {
				fgets($this->socket, 1024);
			}
			
			fclose($this->socket);
			$this->socket = false;
		}
	}
}
