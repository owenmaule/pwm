<?php
/*
	pwm Password Manager
	Copyright Owen Maule 2015
	o@owen-m.com
	https://github.com/owenmaule/pwm
	
	License: GNU Affero General Public License v3

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/agpl.html>.
*/

	define( 'NL', "\n" );
	define( 'RN', "\r\n" );
	define( 'BR', '<br />' . NL );

	# Invalid in indentifier, for use in PCRE regex
	define( 'SQL_INVALID_CHARS', '\'"`~\!%\^&\(\)\-\{\}\\\\' );

	define( 'TIME_FORMAT', 'l jS \of F Y h:i:s A' );
	define( 'TOKEN_DELIM', '-' );

	define( 'ALERT_ERROR', 'error' );
	define( 'ALERT_NOTE', 'notice' );
	define( 'ALERT_DENY', 'denied' );
	define( 'ALERT_DEBUG', 'debug' );
	define( 'ALERT_WARN', 'warning' );

	define( 'AUTH_LOGIN', 'Login' );
	define( 'AUTH_REGISTER', 'Register' );
	define( 'AUTH_CHANGE', 'Change' );
	define( 'AUTH_RESET', 'Reset' );
	define( 'AUTH_CANCEL', 'Cancel' );
	define( 'AUTH_LOGOUT', 'Logout' );

	define( 'ENTRY_CREATE', 'Create' );
	define( 'ENTRY_UPDATE', 'Update' );
	define( 'ENTRY_DELETE', 'Delete' );
