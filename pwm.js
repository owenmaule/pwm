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
		$( this ).fadeOut( 600 );
	} );

	// Click list, to view entries
	$( "#selector" ).click( function() {
		// Very basic solution, will be improved to make an asynchronous call to json feed
		location.href = appLocation + 'entry/' + $( this ).val();
	} );
	$( "#select-entry" ).hide();

	// ZeroClipboard
	if( enableClipboard )
	{
		$( "body" ).on( "copy", "#entry-form input[type=text], #entry-form input[type=password]", function( e )
		{
			var textToCopy = $( this ).val();

			if( textToCopy )
			{
				console.log( 'Clipboard copy: ' + textToCopy );

				e.clipboardData.setData( "text/plain", textToCopy );
				e.preventDefault();
			}
		} );
	}

	/* Ahhh Ninja! - i.e. Silly stuff beyond this point */

	// Overlap more elements
	$( "nav.pure-menu" ).css( "z-index", 0 );
	$( "form#entry-form input[type=text]" ).css( "z-index", 0 );

	var backgroundFading = false,
		defaultBackgroundColour = $( "body" ).css( "background-color" ),
		CRIMSON = "#DC143C";

	function surpriseAttack( disable )
	{
		if( ! backgroundFading )
		{
			backgroundFading = true;
			$( "body" ).animate( { backgroundColor: CRIMSON }, 50, "easeInCirc" )
				.animate( { backgroundColor: defaultBackgroundColour }, 50, "easeOutCirc",
				function() {
					if( disable )
					{	// disable for 7s
						setTimeout( function() {
							backgroundFading = false;
						}, 7000 );
					} else {
						backgroundFading = false;
					}
				} );
		}
	}

	$( "header img.logo" )
		.bind( "dragstart", function( e ) {
			e.preventDefault();
		} )
		.click( function() {
			backgroundFading = true;
			$( this ).css( { zIndex: 3, opacity: 1 } )
				.animate( { right: "-100px" }, 300, function() {
						// animate background in sync
						$( "body" )
							.delay( 100 )
							.animate( { backgroundColor: CRIMSON }, 50, "easeInCirc" )
							.animate( { backgroundColor: defaultBackgroundColour }, 50, "easeOutCirc" );
					} )
				.animate( { right: "0px" }, 900, "easeOutElastic" )
				.animate( { right: "-100px", opacity: 0 }, 600, "easeInOutCirc", function() {
						$( this ).hide();
						setTimeout( function() { // disable for 7s
							backgroundFading = false;
						}, 7000 );
					} )
		} )
		// Page load animation
		.css( { zIndex: 3, right: "-100px", opacity: 0 } )
		.animate( { right: "0px", opacity: 1 }, 900 )
		// jQuery animate breaks the css :hover - so implement in jQuery
		.mouseover( function() {
				$( this ).css( "opacity", .8 );
		} )
		.mouseout( function() { 
				$( this ).css( "opacity", 1 );
		} )
		;
	
	$( "h1 a" ).mouseover( function() {
		surpriseAttack( true );
	} );
} );