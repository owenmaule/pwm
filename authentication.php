<?php
/*
	appBase
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

require_once( 'appBase.php' );

class authentication extends appBase
{
	private $authTable = '';
	private $loggedIn = false;
	private $canResetPassword = false;
	private $resetToken = '';
	
	public function __construct()
	{
		parent::__construct();
	}

	# Set up database
	public function install()
	{
		# Supply default table configuration
		# Save shortcut to table name
		$this->authTable = $this->supplyTableDefaults( 'auth', 'auth', '(
`user_id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`user_email` VARCHAR( 128 ) NOT NULL ,
`user_password` CHAR( 88 ) NOT NULL
) ENGINE = InnoDB' );

		return parent::install();
	}

	public function generateSalt()
	{
		# PHP 5.4 has bin2hex()
		$iv = mcrypt_create_iv( $this->config[ 'salt_length' ], MCRYPT_DEV_RANDOM );
		$hexSalt = current( unpack( 'H*', $iv ) );
#		$this->alert( 'Generated salt ' . $hexSalt . ' (' . strlen( $hexSalt ) . ')', ALERT_DEBUG );
		return $iv;
	}
	
	public function hashPassword( $password )
	{
		return hash( $this->config[ 'hash_algo' ], $password );
	}
	
	public function password_hash( $password, $salt = '' )
	{
		# PHP 5.5 has password_hash()
		if( ! $salt )
		{
			$salt = $this->generateSalt();
		}
		$hash = $this->hashPassword( $salt . $password );
		# PHP 5.4 has bin2hex()
		$hexSalt = current( unpack( 'H*', $salt ) );
#		$this->alert( 'Add salt hex= ' . $hexSalt . ' bin= ' . $salt, ALERT_DEBUG );
		return $hexSalt . $hash;
	}
	
	public function password_verify( $password , $saltHash )
	{
		# PHP 5.5 has password_verify()
		# strip salt - 2 bytes hex per byte binary
		$hexSalt = substr( $saltHash, 0, $this->config[ 'salt_length' ] * 2 );
		# PHP 5.4 has hex2bin()
		$passwordHash = $this->password_hash( $password, pack( 'H*', $hexSalt ) );
		$match = ( $passwordHash == $saltHash );
		if( ! $match )
		{
			$this->alert( 'Stripped hexSalt= ' . $hexSalt . ' (' . strlen( $hexSalt ) . ')', ALERT_DEBUG );
			$this->alert( 'passwordHash= ' . $passwordHash . BR
				. 'saltHash= ' . $saltHash, ALERT_DEBUG );
		}
		return $match;
	}

	# Hand written encryption. Avoid requiring mcrypt module
	public function symmetricEncrypt( $data, $key )
	{
#		$this->alert( 'Encrypting: ' . $data, ALERT_DEBUG );

		$encrypted = '';
		if( ( $dataLength = strlen( $data ) )
			&& ( $keyLength = strlen( $key ) ) )
		{
			$encryptedBinaryChars = '';
			for( $loop = 0, $keyLoop = 0; $loop != $dataLength; ++$loop )
			{
				$encryptedChar = ord( $data[ $loop ] ) + ord( $key[ $keyLoop ] );
				$encryptedBinaryChars .= chr( $encryptedChar );
#				$this->alert( '[' . $loop . ']: ' . ord( $data[ $loop ] ) . ' [' . $keyLoop . ']: '
#					. ord( $key[ $keyLoop ] ) . ' char: ' . $encryptedChar, ALERT_DEBUG );
				if( ++$keyLoop == $keyLength )
				{
					$keyLoop = 0;
				}
			}
			$encrypted = base64_encode( $encryptedBinaryChars );
#			$this->alert( 'Encrypted: ' . $encrypted, ALERT_DEBUG );
		}
		return $encrypted;
	}

	public function symmetricDecrypt( $data, $key )
	{
#		$this->alert( 'Decrypting: ' . $data, ALERT_DEBUG );

		$decrypted = '';
		$binaryData = base64_decode( $data );
		if( ( $dataLength = strlen( $binaryData ) )
			&& ( $keyLength = strlen( $key ) ) )
		{
			for( $loop = 0, $keyLoop = 0; $loop != $dataLength; ++$loop )
			{
				$subtraction = ord( $binaryData[ $loop ] ) - ord( $key[ $keyLoop ] );
				if( $subtraction < 0 )
				{
					$subtraction += 256;
				}
				$decryptedChar = chr( $subtraction );
#				$this->alert( '[' . $loop . ']: ' . ( (int) $binaryData[ $loop ] )
#					. ' [' . $keyLoop . ']: ' . chr( $key[ $keyLoop ] ) . ' char: ' . $decryptedChar,
#					ALERT_DEBUG );
				$decrypted .= $decryptedChar;
				if( ++$keyLoop == $keyLength )
				{
					$keyLoop = 0;
				}
			}
#			$this->alert( 'Decrypted: ' . $decrypted, ALERT_DEBUG );
		}
		return $decrypted;
	}
	
	public function logIn()
	{
		if( ! $this->loggedIn )
		{
			$user = $login_password = '';
			$claimLoggedIn = false;

			# Check for credentials already in session
#			echo 'SESSION = ', var_export( $_SESSION, true ), BR;
			if( empty( $_SESSION[ 'login_password' ] ) )
			{
				if( ! empty( $_SESSION[ 'user' ] ) )
				{	# Debugging
					$this->alert( 'Missing password from session', ALERT_ERROR );
				}

				# Not in session - Check for credentials submitted
#				echo 'POST = ', var_export( $_POST, true ), BR;
				if( empty( $_POST[ 'user' ] ) || empty( $_POST[ 'login_password' ] ) )
				{
					if( isset( $_POST[ 'user' ] ) )
					{
						$this->alert( 'Must supply credentials to log in', ALERT_DENY );
					}
				} else {
					# Submitted for login
					$user = $_POST[ 'user' ];
					$login_password = $_POST[ 'login_password' ];
				}
			} else {
				$claimLoggedIn = true;
				$user = $_SESSION[ 'user' ];
				$login_password = $_SESSION[ 'login_password' ];
			}

			if( $user && $login_password )
			{
				# Security check
				$query = $this->database->prepare( 'SELECT user_id, user_password FROM '
					. $this->authTable . ' where user_email = ? ORDER BY user_id LIMIT 1' );
				$query->execute( array ( $user ) );
				$result = $query->fetch();
				if( false == $result || empty( $result[ 'user_password' ] ) )
				{
					$this->alert( 'Account not found', ALERT_DENY );
				} else {
					if( $this->password_verify( $login_password, $result[ 'user_password' ] ) )
					{
						$this->loggedIn = true;
						if( ! $claimLoggedIn )
						{
							$this->alert( 'Logged in - Welcome', ALERT_NOTE );
						}
						$_SESSION[ 'user' ] = $user;
						$_SESSION[ 'login_password' ] = $login_password;
						$_SESSION[ 'user_id' ] = $result[ 'user_id' ];
					} else {
						$_SESSION[ 'user' ] = '';
						$_SESSION[ 'login_password' ] = '';
						$this->alert( 'Login failed', ALERT_DENY );
					}
				}
			}
		}
		return $this->loggedIn;
	}

	public function logOut()
	{
		$_SESSION = array ( );
		$this->alert( 'Logged out', ALERT_NOTE );
	}
	
	public function passwordQuality( $password )
	{
		# Security checks: return '' for acceptable or description of bad quality
		if( ! strlen( $password ) )
		{
			return 'Zero length';
		}

		# To do: more checks

		return '';
	}

	public function register()
	{
		if( empty( $_POST[ 'user' ] ) || empty( $_POST[ 'login_password' ] ) )
		{
			$this->alert( 'Must supply credentials to register', ALERT_DENY );
			return false;
		}
		$user = $_POST[ 'user' ];

		$query = $this->database->prepare( 'SELECT user_id FROM ' . $this->authTable
			. ' WHERE user_email = ? LIMIT 1' );
		$query->execute( array ( $user ) );
		$result = $query->fetch();
		if( false != $result )
		{
			$this->alert( 'User ' . htmlspecialchars( $user ) . ' already exists', ALERT_DENY );
			return false;
		}
		
		if( -1 != version_compare( phpversion(), '5.2.0' ) )
		{
			# additional filtering and validation of email address
			$cleanEmail = filter_var( $user, FILTER_SANITIZE_EMAIL );
			if( $cleanEmail != $user
				|| ! filter_var( $cleanEmail, FILTER_VALIDATE_EMAIL ) )
			{
				$this->alert( 'The email address supplied was considered invalid', ALERT_DENY );
				return false;				
			}
			$user = $cleanEmail;
		} else {
			# regex validation
			if( ! eregi( '^[0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.[a-z]{2,3}$', $user ) )
			{
				$this->alert( 'The email address supplied was considered invalid', ALERT_DENY );
				return false;				
			}
		}

		$login_password = $_POST[ 'login_password' ];
		$lowQuality = $this->passwordQuality( $login_password );
		if( $lowQuality )
		{
			$this->alert( 'Your password is low quality: ' . $lowQuality, ALERT_DENY );
			return false;
		}
		
		$this->alert( 'Creating user: ' . $user . ' password: ' . $login_password, ALERT_DEBUG );
		$hash = $this->password_hash( $login_password );		
		$this->alert( 'Salt + Hash: ' . $hash . ' (' . strlen( $hash ) . ')', ALERT_DEBUG );
		# Check hash
		$testPassword = $this->password_verify( $login_password, $hash );
		$this->alert( 'Verify: ' . ( $testPassword ? 'passed' : 'error' ), ALERT_DEBUG );
		if( ! $testPassword )
		{
			$this->alert( 'Password encryption error', ALERT_ERROR );
			return false;
		}
		$query = $this->database->prepare( 'INSERT INTO ' . $this->authTable
			. ' (user_email, user_password) VALUES (?, ?)' );
		$query->execute( array ( $user, $hash ) );
		$this->alert( 'Created user: ' . htmlspecialchars( $user ) . ' password: ' . $login_password,
			ALERT_DEBUG );

		# to do: email validation link
		# login
		$query = $this->database->prepare(
			'SELECT user_id FROM ' . $this->authTable . ' where user_email = ? ORDER BY user_id LIMIT 1' );
		$query->execute( array ( $user ) );
		$result = $query->fetch();

		$_SESSION[ 'user' ] = $user;
		$_SESSION[ 'login_password' ] = $login_password;
		$_SESSION[ 'user_id' ] = $result[ 'user_id' ];
		$this->loggedIn = true;

		$this->alert( 'Newly registered - Welcome', ALERT_NOTE );
		return true;
	}
	
	public function changePassword()
	{
		if( ! $this->loggedIn )
		{
			$this->alert( 'Must be logged in to change password', ALERT_DENY );
			return false;
		}

		if( empty( $_POST[ 'login_password' ] ) )
		{
			if( isset( $_POST[ 'login_password' ] ) )
			{
				$this->alert( 'Must supply a replacement password', ALERT_DENY );
			}
			$this->alert( 'Choose a new password', ALERT_NOTE );	
			return false;
		}

		$password = $_POST[ 'login_password' ];
		$lowQuality = $this->passwordQuality( $password );
		if( $lowQuality )
		{
			$this->alert( 'Your password is low quality: ' . $lowQuality, ALERT_DENY );
			return false;
		}
		
		# Simply change the password
		$hash = $this->password_hash( $password );
		$query = $this->database->prepare(
			'UPDATE ' . $this->authTable . ' SET user_password = ? WHERE user_id = ?' );
		$query->execute( array ( $hash, $_SESSION[ 'user_id' ] ) );
		$_SESSION[ 'login_password' ] = $password;

		# When the data is encrypted using the password
		# - Check there isn't multiple rows already, if so delete any extra ones
		# - Create a second row with the same email, re-encode the data, then delete the original row

		$this->alert( 'Changed password', ALERT_NOTE );	
		return true;
	}
	
	public function resetPassword()
	{
		$resetTimer = 1800;
		if( ! empty( $this->config[ 'reset_token_timeout' ] ) )
		{	# Adjust from the default 30 mins, if over the minimum of 10 minutes
			if( 600 < ( $resetTimerConfig = (int) $this->config[ 'reset_token_timeout' ] ) )
			{
				$resetTimer = $resetTimerConfig;
				$this->alert( 'Reset timer set at ' . ( $resetTimer / 60.0 ) . ' minutes', ALERT_DEBUG );
			}
		}
		
		# GET takes precedence
		$this->resetToken = $token = ! empty( $_GET[ 'reset' ] ) ? $_GET[ 'reset' ] :
			( ! empty( $_POST[ 'reset' ] ) ? $_POST[ 'reset' ] : '' );
		
		# Check if received the email
		if( $token )
		{
			$this->alert( 'Got token ' . htmlspecialchars( $token ), ALERT_DEBUG );
			$dashPos = strpos( $token, TOKEN_DELIM );
			if( ! $dashPos	# first position or not found
				|| ! ( $user_id = substr( $token, 0, $dashPos ) ) ) # Find user_id and check it's a number
			{
				$this->alert( 'Invalid token format', ALERT_ERROR );
				$this->alert( 'token=' . $token, ALERT_DEBUG );
				return false;
			}

			$subToken = substr( $token, $dashPos + 1 );
			$this->alert( 'User=' . $user_id . ' subToken=' . $subToken, ALERT_DEBUG );

			$query = $this->database->prepare( 'SELECT user_email, user_password FROM ' . $this->authTable
				. ' where user_id = ? ORDER BY user_id LIMIT 1' );
			$query->execute( array ( $user_id ) );
			$result = $query->fetch();
			if( false == $result
				|| empty( $result[ 'user_email' ] )
				|| empty( $result[ 'user_password' ] ) )
			{
				$this->alert( 'Account not found', ALERT_DENY );
				return false;
			}

			$email = utf8_decode( $result[ 'user_email' ] );
			$passwordHash = utf8_decode( $result[ 'user_password' ] );
			$rawToken = $this->SymmetricDecrypt( $subToken, $passwordHash );
			
			# Validate token
			$emailLength = strlen( $email );
			if( 0 !== strncmp( $rawToken, $email, $emailLength ) )
			{
				$this->alert( 'Invalid token', ALERT_DENY );
				$this->alert( 'rawToken=' . $rawToken. ' passwordHash='
					. implode( BR, str_split( $passwordHash, 44 ) ), ALERT_DEBUG );
				return false;
			}

			# Find token creation time
			$hexTime = substr( $rawToken, $emailLength );
			$timeNow = (int) time();
			$tokenTime = false;
			if( ! $hexTime || ( $tokenTime = hexdec( $hexTime ) ) > $timeNow )
			{
				$this->alert( 'Invalid token time', ALERT_DENY );
				$this->alert( 'hexTime=' . ( '' == $hexTime ? '\'\'' : '' ) . BR
					. 'tokenTime=' . ( $tokenTime ? date( TIME_FORMAT, $tokenTime ) : 'undefined' ) . BR
					. 'timeNow=' . date( TIME_FORMAT, $timeNow ),
					ALERT_DEBUG );
				return false;
			}
			$timeSince = $timeNow - $tokenTime;
#			$this->alert( 'tokenTime=' . date( TIME_FORMAT, $tokenTime ) . BR
#				. 'timeNow=' . date( TIME_FORMAT, $timeNow ) . BR
#				. 'timeSince=' . number_format ( $timeSince / 60.0, 1 ) . ' minutes', ALERT_DEBUG );
			if( $timeSince > $resetTimer )
			{
				$this->alert( 'Valid token has timed out. Request a new one', ALERT_DENY );
				return false;
			}
			
			$this->canResetPassword = true;
			$_SESSION[ 'user' ] = $result[ 'user_email' ];

			if( empty( $_POST[ 'login_password' ] ) )
			{
				$this->alert( 'Set your new password', ALERT_NOTE );
				return false;
			} else {
				$this->loggedIn = true;
				$_SESSION[ 'user_id' ] = $user_id;
				if( ! $this->changePassword() )
				{
					$this->alert( 'Failed to change your password', ALERT_ERROR );
					return false;
				}
				# Assuming a different password was set, the token is invalid
				$this->canResetPassword = false;
				$this->resetToken = '';
			}
			return true;
		}
	
		# Check for request to send the email
		if( empty( $_POST[ 'user' ] ) )
		{
			$this->alert( 'Must supply email to reset password', ALERT_DENY );
			return false;
		}
		$user = $_POST[ 'user' ];
		$query = $this->database->prepare( 'SELECT user_id, user_password FROM ' . $this->authTable
			. ' where user_email = ? ORDER BY user_id LIMIT 1' );
		$query->execute( array ( $user ) );
		$result = $query->fetch();
		if( false == $result || empty( $result[ 'user_id' ] ) || empty( $result[ 'user_password' ] ) )
		{
			$this->alert( 'Account not found', ALERT_DENY );
			return false;
		}
		
		# Generate token and store in authTable, store time of generation in the token
		$passwordHash = utf8_decode( $result[ 'user_password' ] );
		$this->alert( 'Password hash=' . implode( BR, str_split( $passwordHash, 44 ) ), ALERT_DEBUG );
		$time = (int) time();
		$rawToken = utf8_decode( $user ) . dechex( $time );
		$subToken = $this->SymmetricEncrypt( $rawToken, $passwordHash );
		$token = $result[ 'user_id' ] . TOKEN_DELIM . $subToken;
		$this->alert( 'time= ' . $time . ' rawToken=' . $rawToken . BR
			. 'token=' . $token, ALERT_DEBUG );

		# Test decrypt
		$rawToken2 = $this->SymmetricDecrypt( $subToken, $passwordHash );
		$this->alert( 'Decrypted rawToken=' . $rawToken2, ALERT_DEBUG );
		$this->alert( 'Symmetric en/decryption is' . ( $rawToken != $rawToken2 ? ' NOT' : '' )
			. ' working', ALERT_DEBUG );

		# Send email
		$adminEmail = isset( $this->config[ 'admin_email' ] ) ? $this->config[ 'admin_email' ] : '';
		if( ! $adminEmail || empty( $this->config[ 'app_location' ] ) )
		{
			$this->alert( 'Not configured to send email. Contact admin ' . $adminEmail, ALERT_ERROR );
			return false;
		}

		$resetLink = $this->config[ 'app_location' ] . 'reset/' . $token;
#		$this->alert( '<a href="' . $resetLink . '">Reset link</a>', ALERT_DEBUG );
		$this->alert( 'resetLink=' . $resetLink, ALERT_DEBUG );

		$headers = 'From: ' . $adminEmail . RN
			. 'Reply-To: ' . $adminEmail . RN
			. 'X-Mailer: PHP/' . phpversion();

		if( ! mail( $user, 'Password reset for Password Manager',
			'Password Manager' . NL
			. '~~~~~~~~~~~~~~~~' . NL
			. '' . NL
			. 'If you have not requested a password reset, please ignore this message.' . NL
			. '' . NL
			. 'To reset your password follow this link: ' . $resetLink . NL
			. '' . NL			
			. 'This reset token is valid for ' . ( $resetTimer / 60.0 ) . ' minutes from '
				. date( TIME_FORMAT, $time ) . NL,
			$headers ) )
		{
			$this->alert( 'Failed to send email to ' . $user, ALERT_ERROR );
		}

		$this->alert( 'Reset email sent to ' . $user, ALERT_NOTE );
		return true;

		# Check if a matching token is passed in the URL, if so, allow a new password to be entered
		# If no token in URL, send an email with the link containing the token
	}
	
	public function authentication()
	{
		$auth = ! empty( $_POST[ 'auth' ] ) ? $_POST[ 'auth' ] :
			( ! empty( $_GET[ 'auth' ] ) ? $_GET[ 'auth' ] : '' );
		$changePassword = false;

		if( $auth )
		{
			$this->alert( 'Auth action: ' . htmlspecialchars( $auth ), ALERT_DEBUG );
		}
		switch( $auth )
		{
			case '';
			case AUTH_LOGIN:
			case AUTH_CANCEL:
				$this->logIn();
				break;

			case AUTH_REGISTER:
				if( $this->register() )
				{
					$this->logIn();
					$auth = '';
				}
				break;

			case AUTH_CHANGE:
				$this->logIn();
				$changePassword = true;
				if( $this->changePassword() )
				{
					$changePassword = false;
					$auth = '';
				}
				break;

			case AUTH_RESET:
				if( $this->resetPassword() )
				{
					$auth = '';
				}
				break;

			case AUTH_LOGOUT:
				$this->logOut();
				break;

			default:
				$this->alert( 'Invalid authentication action requested', ALERT_ERROR );
				$auth = '';
		}
		
		if( ! $this->loggedIn || $changePassword )
		{
			$main = '
<form id="auth-form" name="auth" action="' . $this->content[ 'rel_path' ] . 'auth" method="post" '
				. 'class="pure-form">';

			$setButton = AUTH_CHANGE;
			if( $this->canResetPassword )
			{
				$changePassword = true;
				$setButton = AUTH_RESET;
				$main .= '
		<input type="hidden" name="reset" value="' . $this->resetToken . '" />';
			}

			# Login / Register / Change / Reset password
			$user = ! empty( $_SESSION[ 'user' ] ) ? $_SESSION[ 'user' ] : '';
			$main .= '
		<label for="user">E-mail address: </label><input type="text" id="user" name="user" value="'
				. $user . '" ' . ( $changePassword ? 'readonly="readonly" ' : '' ) . '/><br />
		<label for="login-password">Password: </label><input type="password" id="login-password" '
				. 'name="login_password" value="" />
		<div class="button-bar">';

			if( $changePassword )
			{
				$main .= '
			<input type="submit" name="auth" value="' . $setButton . '" />
			<input type="submit" name="auth" value="' . AUTH_CANCEL . '" />';
			} else {
				$main .= '
			<input type="submit" name="auth" value="' . AUTH_LOGIN . '" />
			<input type="submit" name="auth" value="' . AUTH_REGISTER . '" />
			<input type="submit" name="auth" value="' . AUTH_RESET . '" />';
			}

			$main .= '
		</div>
</form>';
			$this->content( 'title', $auth );
			$this->content( 'main', $main );
			return false;
		}
		return true;
	}
}