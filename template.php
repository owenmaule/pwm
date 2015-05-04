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
	
	---
	To do:
	Continue searching for the owner of the image and check permission. I expect it's okay, it's Tux and GPL software.
	Make selector box wider in smallest layout
*/
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <title>PwN Password Ninja<?php echo $content[ 'title' ] ? ' - ' . $content[ 'title' ] : '' ?></title>
<?php /*	<link rel="stylesheet" href="pure-min.css" /> */ ?>
	<link rel="stylesheet" href="normalize.css" />
	<link rel="stylesheet" href="pure-extract.css" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="stylesheet" href="pwm.css" />
	<link rel="icon" href="favicon.ico" />
	<meta name="description" content="Open source Password Manager web application written in PHP by Owen Maule as a demonstration of competency for a job interview." />
	<meta name="author" content="Owen Maule" />
	<meta name="copyright" content="Copyright Owen Maule 2015" />
</head>
<body>
	<header>
		<div id="alert" role="alert"><?php
				foreach( $content[ 'alert' ] as $message => $type )
				{
					# Hide debug messages
					if( $type != ALERT_DEBUG || isset( $content[ 'alert_debug' ] ) )
					{
						echo '
			<span class="alert-', $type, '">', /*ucfirst( $type ), ': ',*/ $message, '</span>';
					}
				}
			?>

		</div>
		<div class="logo-mount">
			<img class="logo" src="tux-ninja.png" alt="Tux the penguin as a badass ninja" />
		</div>
		<div id="header-overlay">
			<h1 title="Guarding your passwords">PwN <span class="first-part">Pass</span> <span>word</span> Ninja</h1>
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
		&copy; Copyright <a href="http://owen-m.com/" target="_blank">Owen Maule</a> 2015 <a href="mailto:o@owen-m.com">o@owen-m.com</a></p>
	</footer>
</body>
</html>