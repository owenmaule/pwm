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

	$path = ! empty( $content[ 'rel_path' ] ) ? $content[ 'rel_path' ] :
		( ! empty( $_GET[ 'path_up' ] ) ? '../' : '' );


	$debugLayout = ! empty( $content[ 'debug_layout' ] ) ? ' class="debug-layout"' : '';
	$javascriptDisabled = ! empty( $content[ 'disable_javascript' ] ) ? '<!-- Test mode: Javascript disabled -->' : '';

	$secretWords = array ( 'flower', 'fruit', 'sun', 'moon', 'sea', 'salt', 'valley', 'water', 'fire',
		'smoke', 'frog', 'well', 'tea', 'green' );
	$secretWordMax = count( $secretWords ) - 1;

	$ninjaSecret1 = $secretWords[ mt_rand( 0, $secretWordMax ) ];

	do {
		$ninjaSecret2 = $secretWords[ mt_rand( 0, $secretWordMax ) ];
	} while( $ninjaSecret1 == $ninjaSecret2 );

	$ninjaSecret = $ninjaSecret1 . '-' . $ninjaSecret2;

?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <title>PwN Password Ninja<?php echo $content[ 'title' ] ? ' - ' . $content[ 'title' ] : '' ?></title>
<?php /*	<link rel="stylesheet" href="pure-min.css" /> */ ?>
	<link rel="stylesheet" href="<?php echo $path; ?>normalize.css" />
	<link rel="stylesheet" href="<?php echo $path; ?>pure-extract.css" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="stylesheet" href="<?php echo $path; ?>pwm.css" />
	<?php echo ! $javascriptDisabled ?
	'<script type="text/javascript" src="' . $path . 'jquery-1.11.3.min.js"></script>
	<script type="text/javascript" src="' . $path . 'jquery-ui.easing.min.js"></script>'
	. ( empty( $content[ 'disable_clipboard' ] )
		? '
	<script type="text/javascript" src="' . $path . 'jquery.zeroclipboard.min.js"></script>' : '' ) . '
	<script type="text/javascript" src="' . $path . 'pwm.js"></script>'
	: $javascriptDisabled ?>

	<link rel="icon" href="<?php echo $path; ?>favicon.ico" />
	<meta name="description" content="Open source Password Manager web application written in PHP by Owen Maule in early May 2015, as a demonstration of competency for a job interview." />
	<meta name="author" content="Owen Maule <o@owen-m.com>" />
	<meta name="copyright" content="Copyright Owen Maule 2015" />
</head>
<body<?php echo $debugLayout; ?>>
	<header>
		<div id="alert" role="alert"><?php
				foreach( $content[ 'alert' ] as $message => $type )
				{
					# Hide debug messages
					if( $type != ALERT_DEBUG || ! empty( $content[ 'alert_debug' ] ) )
					{
						echo '
			<span class="alert-', $type, '">', /*ucfirst( $type ), ': ',*/ $message, '</span>';
					}
				}
			?>

		</div>
		<div class="logo-mount">
			<img class="logo" src="<?php echo $path; ?>tux-ninja.png" alt="Tux the penguin as a badass ninja" title="<?php echo $ninjaSecret ?>" />
		</div>
		<div id="header-overlay">
			<h1><a href="<?php echo $path; ?>"><span class="app-title" title="Guarding your passwords">PwN <span class="first-part">Pass</span> <span>word</span> Ninja</span></a></h1>
			<h2>By <a href="http://owen-m.com/" target="_blank">Owen Maule</a></h2>
			<nav class="pure-menu pure-menu-horizontal">
				<ul class="pure-menu-list"><?php
					foreach( $content[ 'menu' ] as $menuText => $menuLink )
					{
						echo '
					<li class="pure-menu-item">',
							( $menuLink
								? '<a href="' . $menuLink . '" class="pure-menu-link">' . $menuText . '</a>'
								: '<span class="pure-menu-link">' . $menuText . '</span>'
							), '</li>';
					}
				?>

				</ul>
			</nav>
		</div>
	</header>
	<div id="main" role="main">
<?php echo $content['main'] ?>

	</div>
	<footer>
		<p>Developed as a competency test in early May 2015<br />
			&copy; Copyright <a href="http://owen-m.com/" target="_blank">Owen Maule</a> 2015
			<span class="nowrap">&lt;<a href="mailto:o@owen-m.com">o@owen-m.com</a>&gt;</span><br />
			Latest version on <a href="https://github.com/owenmaule/pwm" target="_blank">GitHub</a>
		</p>
		<p class="license">This software comes with ABSOLUTELY NO WARRANTY<br />
			It is <a href="https://www.gnu.org/licenses/agpl-3.0.html" rel="nofollow" target="_blank">free software</a>, and you are welcome to modify and redistribute it
			under certain conditions
		</p>
	</footer>

	<?php echo ! $javascriptDisabled ? 
	'<script type="text/javascript">
		var debugToConsole = ' . ( 'console' === $content[ 'alert_debug' ] ? 'true' : 'false' ) . ',
			appLocation = "' . ( ! empty( $content[ 'abs_path' ] ) ? $content[ 'abs_path' ] : '' ) . '",
			enableClipboard = ' . ( empty( $content[ 'disable_clipboard' ] ) ? 'true' : 'false' ) . ';
	</script>
' : $javascriptDisabled ?>

</body>
</html>