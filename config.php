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

$config = array(
	'dsn' => 'mysql:host=localhost;dbname=YOUR_DATABASE;charset=utf8',
	'db_user' => 'YOUR_USERNAME',
	'db_password' => 'YOUR_PASSWORD',
	
	'debug_messages' => true,
	'salt_length' => 12,
	'hash_algo' => 'sha256',
	'enforce_https' => false,

	'limit_fails' => 10,
	'limit_fails_timer' => 600,
);
