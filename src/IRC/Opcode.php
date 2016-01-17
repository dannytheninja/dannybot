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

/**
 * Class containing opcodes for various received IRC messages. Please use these.
 */

class Opcode
{
	const OP_WELCOME = '001';
	const OP_NAMES_LIST = '353';
	const OP_NAMES_END = '366';
	const OP_HOSTNAME_CHANGED = '396';
	
	// whois
	const OP_WHOIS_IDENTITY = '311';
	const OP_WHOIS_CHANNELS = '319';
	const OP_WHOIS_SERVER = '312';
	const OP_WHOIS_SERVICES_IDENTITY = '330';
	const OP_WHOIS_END = '318';
}
