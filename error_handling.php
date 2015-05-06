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

	$errorTypes = array (
		E_ERROR => ALERT_ERROR,
		E_WARNING => ALERT_WARNING,
		E_PARSE => ALERT_ERROR,
		E_NOTICE => ALERT_NOTE,
		E_USER_ERROR => ALERT_ERROR,
		E_USER_WARNING => ALERT_WARNING,
		E_USER_NOTICE => ALERT_NOTE,
	);

	function pwm_error_handler( $errno, $errstr, $errfile, $errline )
	{
		global $pwm, $errorTypes;

		$errorType = ! empty( $errorTypes[ $errno ] ) ? $errorTypes[ $errno ] : 'Message';
		$pathParts = explode( '/', $errfile );
		$errfilename = end( $pathParts );
		$errorText = 'PHP ' . $errorType . ': ' . $errfilename . ':' . $errline . ' ' . $errstr;
		if( is_object( $pwm ) )
		{
			$pwm->alert( $errorText, $errorType );
		} else {
			echo $errorText . BR;
		}
		return true;
	}

	set_error_handler( 'pwm_error_handler' );
	error_reporting( -1 );
	ini_set( 'display_errors', 'On' );