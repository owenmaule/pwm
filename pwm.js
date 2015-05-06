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

$( function() {
	console.log( "pwm Password Manager (c) Copyright Owen Maule 2015 <o@owen-m.com> http://owen-m.com/" );
	console.log( "Latest version: https://github.com/owenmaule/pwm   Licence: GNU Affero General Public License" );
	if( debugToConsole )
	{	// Transfer debug alerts to console
		$( "#alert .alert-debug" ).each( function() {
			console.log( 'debug: ' + $( this ).html() );
			$( this ).hide();
		} );
	}
	
	// Dismiss the alerts
	$( "#alert span" ).dblclick( function() {
		$( this ).hide();
	} );
	
	$( "#selector" ).click( function() {
		// Very basic solution, will be improved to make an asynchronous call to json feed
		location.href = appLocation + 'entry/' + $( this ).val();
	} );
	$( "#select-entry" ).hide();
} );