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
	Encrypt entry data by login password
	Refactor into class hierarchy: appBase <- authentication <- passwordManager
	Missing functionality: password confirmation, password generation, password security analysis, limit failed logins
	Back-end support: FULLTEXT, SQLlite

	Template (default theme) to do:
	Front-end Javascript enhancements
	Missing client functionality: show/hide password, copy to clipboard, open website
	Continue searching for the owner of the image and check permission. ( I expect it's okay, it's Tux and GPL software. )
*/

define( 'NL', "\n" );
define( 'RN', "\r\n" );
define( 'BR', '<br />' . NL );

define( 'SQL_INVALID_CHARS', '\'"`~\!%\^&\(\)\-\{\}\\\\' ); # Invalid in indentifier, for use in PCRE regex
define( 'TIME_FORMAT', 'l jS \of F Y h:i:s A' );
define( 'TOKEN_DELIM', '-' );

define( 'ALERT_ERROR', 'error' );
define( 'ALERT_NOTE', 'note' );
define( 'ALERT_DENIED', 'denied' );
define( 'ALERT_DEBUG', 'debug' );
define( 'ALERT_WARNING', 'warn' );

define( 'AUTH_LOGIN', 'Login' );
define( 'AUTH_REGISTER', 'Register' );
define( 'AUTH_CHANGE', 'Change' );
define( 'AUTH_RESET', 'Reset' );
define( 'AUTH_CANCEL', 'Cancel' );
define( 'AUTH_LOGOUT', 'Logout' );

define( 'ENTRY_CREATE', 'Create' );
define( 'ENTRY_UPDATE', 'Update' );
define( 'ENTRY_DELETE', 'Delete' );

class pwm
{
	public $config = array ( );
	public $database = null;
	public $content = array (
		'title' => '',
		'menu' => array ( ),
		'alert' => array ( ), #'extra1' => ALERT_DEBUG, 'extra2' => ALERT_DEBUG, 'extra3' => ALERT_DEBUG, 'extra4' => ALERT_DEBUG, 'extra5' => ALERT_DEBUG, 'extra6' => ALERT_DEBUG ),
		'main' => '<p>An error has occurred.</p>',
		'rel_path' => '',
	);
	public $theme = 'template.php';

	private $authTable = '';
	private $loggedIn = false;
	private $canResetPassword = false;
	private $resetToken = '';

	private $pwmTable = '';
	private $selected = 0;
	public $entries = array ( );
	public $fields = array ( 'entry_id', 'label', 'username', 'password', 'url', 'notes' );
	public $entry = array ( );

	public function __construct()
	{
		require_once 'config.php';
		$this->config = $config;

		# true, false or 'console'
		$this->content[ 'alert_debug' ] = empty( $config[ 'debug_messages' ] ) ? false :
			( 'console' === $config[ 'debug_messages' ] ? 'console' : true );

		if( ! empty( $config[ 'enforce_https' ] ) )
		{
			if( empty( $_SERVER[ 'HTTPS' ] ) || $_SERVER[ 'HTTPS' ] !== 'on' )
			{
				header( 'Location: https://' . $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ],
					true, 301 );
				exit();
			}
			header( 'Strict-Transport-Security: max-age=31536000' );
		}

		if( empty( $config[ 'dsn' ] ) || ! isset( $config[ 'db_user' ] ) || ! isset( $config[ 'db_password' ] ) )
		{
			throw new Exception ( 'Missing database configuration' );
		}

		try {
			$this->database = new PDO( $config[ 'dsn' ], $config[ 'db_user' ], $config[ 'db_password' ],
				array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC )
			);
		} catch ( Exception $e )
		{
			throw new Exception ( 'Failed to open data store' . BR . $e->getMessage() );
		}

		$this->relativeLocation();

		if( true !== ( $errorMessage = $this->install() ) )
		{
			throw new Exception ( $errorMessage );
		}

		session_start();
	}
	
	public function install()
	{
		# Set up database
		# Return true for success or error message on failure
/*
CREATE TABLE `pwm`.`users` (
`user_id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`user_email` VARCHAR( 128 ) NOT NULL ,
`user_password` VARCHAR( 88 ) NOT NULL
) ENGINE = InnoDB;

CREATE TABLE `pwm`.`entries` (
`entry_id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`user_id` INT( 11 ) UNSIGNED NOT NULL ,
`label` VARCHAR( 128 ) NOT NULL ,
`username` VARCHAR( 128 ) NOT NULL ,
`password` VARCHAR( 128 ) NOT NULL ,
`url` VARCHAR( 128 ) NOT NULL ,
`notes` VARCHAR( 1024 ) NOT NULL
) ENGINE = InnoDB;
*/

		# Authentication table
		if( true !== ( $errorMessage = $this->checkCreateTable( 'db_auth_table', '(
`user_id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`user_email` VARCHAR( 128 ) NOT NULL ,
`user_password` VARCHAR( 88 ) NOT NULL
) ENGINE = InnoDB' ) ) )
		{
			return $errorMessage;
		}
		$this->authTable = $this->config[ 'db_auth_table' ];

		# Password details table
		if( true !== ( $errorMessage = $this->checkCreateTable( 'db_pwm_table', '(
`entry_id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`user_id` INT( 11 ) UNSIGNED NOT NULL ,
`label` VARCHAR( 128 ) NOT NULL ,
`username` VARCHAR( 128 ) NOT NULL ,
`password` VARCHAR( 128 ) NOT NULL ,
`url` VARCHAR( 128 ) NOT NULL ,
`notes` VARCHAR( 1024 ) NOT NULL
) ENGINE = InnoDB' ) ) )
		{
			return $errorMessage;
		}
		$this->pwmTable = $this->config[ 'db_pwm_table' ];

		return true;
	}

	public function relativeLocation()
	{
		$found = false;
		if( ! empty( $this->config[ 'app_location' ] ) )
		{
			$appLocation = $this->config[ 'app_location' ];
			$urlParts = parse_url( $appLocation );
			if( ! empty( $urlParts[ 'path' ] ) )
			{
				$this->content[ 'rel_path' ] = $urlParts[ 'path' ];
				$found = true;
			}
		}
		if( ! $found )
		{
			# Try an alternative method, maybe using path_up? Dodgy but could be okayish
			$pathUp = ! empty( $_GET[ 'path_up' ] ) ? '../' : '';

			$this->alert( 'Configuration error: app_location', ALERT_ERROR );
		}
		return $found;
	}
	
	public function tableExists( $tableName )
	{
		try {
			$result = $this->database->query( 'SELECT 1 FROM ' . $tableName . ' LIMIT 1' );
		}
		catch ( Exception $e )
		{
			return false;
		}
		return $result !== false;
	}

	public function checkCreateTable( $tableConfig, $tableDefinition )
	{
		if( empty( $this->config[ $tableConfig ] ) )
		{
			return 'Missing database configuration: ' . $tableConfig;
		}
		$fullTableName = $this->config[ $tableConfig ];

		# Check the table name format - I think I may be getting paranoid - or just really helpful to integrators
		$parts = explode( '.', $fullTableName );
		$databaseName = count( $parts ) > 1
			? preg_replace('/[' . SQL_INVALID_CHARS . ']/', '', current( $parts ) )
			: '';
		$tableName = '`' . preg_replace( '/[' . SQL_INVALID_CHARS . ']/', '', end( $parts ) ) . '`';
		if( $databaseName )
		{
			$tableName = '`' . $databaseName . '`.' . $tableName;
		}
		if( $tableName != $fullTableName )
		{
			return 'Invalid table name for ' . $tableConfig . BR
				. 'You may be missing the `quotes`' . BR
				. htmlspecialchars( $fullTableName ) . ' was specified and considered to be ' . $tableName;
		}

		# Check if table exists
		if( ! $this->tableExists( $fullTableName ) )
		{
			if( empty( $this->config[ 'auto_install' ] ) )
			{
				return 'Missing database table ' . $fullTableName . ' (' . $tableConfig . ')';
			}

			try {
				$this->database->exec( 'CREATE TABLE IF NOT EXISTS ' . $fullTableName . ' ' . $tableDefinition );
			}
			catch ( Exception $e )
			{
				return 'Unable to create database table ' . $fullTableName . ' (' . $tableConfig . ') - exception';
			}
	
			# In case not set to throw exceptions - check again
			if( ! $this->tableExists( $fullTableName ) )
			{
				return 'Unable to create database table ' . $fullTableName . ' (' . $tableConfig . ') - rechecked';
			}
		}
		return true;
	}

	# Get/set page content data
	public function content( $field, $value = null )
	{
		if( null === $value )
			return $this->content[ $field ];
		$this->content[ $field ] = $value;
	}

	# Notification system
	public function alert( $message, $type )
	{
		$this->content[ 'alert' ][ $message ] = $type;
	}

	# Draw the web page
	public function render( $theme = '' )
	{
		if( ! $theme )
		{
			$theme = $this->theme;
		}
		$content = $this->content;	# pass content in $content
		require_once $theme;
	}
	
	public function testAlerts()
	{
		foreach( array( ALERT_ERROR, ALERT_NOTE, ALERT_DENIED, ALERT_DEBUG, ALERT_WARNING )
			as $alert )
		{
			$this->alert( ucwords( $alert ) . ': Test alert colours', $alert );
		}
	}
	
	/* Authentication */

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
		$salt = pack( 'H*', $hexSalt );
#		$this->alert( 'Strip salt hex= ' . $hexSalt . ' bin= ' . $salt, ALERT_DEBUG );
		return $this->password_hash( $password, $salt ) == $saltHash;
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
#				$this->alert( '[' . $loop . ']: ' . ( (int) $binaryData[ $loop ] ) . ' [' . $keyLoop . ']: '
#					. chr( $key[ $keyLoop ] ) . ' char: ' . $decryptedChar, ALERT_DEBUG );
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
						$this->alert( 'Must supply credentials to log in', ALERT_DENIED );
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
				$query = $this->database->prepare(
					'SELECT user_id, user_password FROM ' . $this->authTable . ' where user_email = ? ORDER BY user_id LIMIT 1' );
				$query->execute( array ( $user ) );
				$result = $query->fetch();
				if( false == $result || empty( $result[ 'user_password' ] ) )
				{
					$this->alert( 'Account not found', ALERT_DENIED );
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
						$this->alert( 'Login failed', ALERT_DENIED );
					}
				}
			}
		}
		return $this->loggedIn;
	}

	public function logOut()
	{
		$_SESSION = array( );
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
			$this->alert( 'Must supply credentials to register', ALERT_DENIED );
			return false;
		}
		$user = $_POST[ 'user' ];

		$query = $this->database->prepare( 'SELECT user_id FROM ' . $this->authTable . ' WHERE user_email = ? LIMIT 1' );
		$query->execute( array ( $user ) );
		$result = $query->fetch();
		if( false != $result )
		{
			$this->alert( 'User ' . htmlspecialchars( $user ) . ' already exists', ALERT_DENIED );
			return false;
		}
		
		if( -1 != version_compare( phpversion(), '5.2.0' ) )
		{
			# additional filtering and validation of email address
			$cleanEmail = filter_var( $user, FILTER_SANITIZE_EMAIL );
			if( $cleanEmail != $user
				|| ! filter_var( $cleanEmail, FILTER_VALIDATE_EMAIL ) )
			{
				$this->alert( 'The email address supplied was considered invalid', ALERT_DENIED );
				return false;				
			}
			$user = $cleanEmail;
		} else {
			# regex validation
			if( ! eregi( '^[0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.[a-z]{2,3}$', $user ) )
			{
				$this->alert( 'The email address supplied was considered invalid', ALERT_DENIED );
				return false;				
			}
		}

		$login_password = $_POST[ 'login_password' ];
		$lowQuality = $this->passwordQuality( $login_password );
		if( $lowQuality )
		{
			$this->alert( 'Your password is low quality: ' . $lowQuality, ALERT_DENIED );
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
		$query = $this->database->prepare( 'INSERT INTO ' . $this->authTable . ' (user_email, user_password) VALUES (?, ?)' );
		$query->execute( array ( $user, $hash ) );
		$this->alert( 'Created user: ' . htmlspecialchars( $user ) . ' password: ' . $login_password, ALERT_DEBUG );
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
			$this->alert( 'Must be logged in to change password', ALERT_DENIED );
			return false;
		}

		if( empty( $_POST[ 'login_password' ] ) )
		{
			if( isset( $_POST[ 'login_password' ] ) )
			{
				$this->alert( 'Must supply a replacement password', ALERT_DENIED );
			}
			return false;
		}

		$password = $_POST[ 'login_password' ];
		$lowQuality = $this->passwordQuality( $password );
		if( $lowQuality )
		{
			$this->alert( 'Your password is low quality: ' . $lowQuality, ALERT_DENIED );
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

			$query = $this->database->prepare(
				'SELECT user_email, user_password FROM ' . $this->authTable . ' where user_id = ? ORDER BY user_id LIMIT 1' );
			$query->execute( array ( $user_id ) );
			$result = $query->fetch();
			if( false == $result
				|| empty( $result[ 'user_email' ] )
				|| empty( $result[ 'user_password' ] ) )
			{
				$this->alert( 'Account not found', ALERT_DENIED );
				return false;
			}

			$email = utf8_decode( $result[ 'user_email' ] );
			$passwordHash = utf8_decode( $result[ 'user_password' ] );
			$rawToken = $this->SymmetricDecrypt( $subToken, $passwordHash );
			
			# Validate token
			$emailLength = strlen( $email );
			if( 0 !== strncmp( $rawToken, $email, $emailLength ) )
			{
				$this->alert( 'Invalid token', ALERT_DENIED );
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
				$this->alert( 'Invalid token time', ALERT_DENIED );
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
				$this->alert( 'Valid token has timed out. Request a new one', ALERT_DENIED );
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
			$this->alert( 'Must supply email to reset password', ALERT_DENIED );
			return false;
		}
		$user = $_POST[ 'user' ];
		$query = $this->database->prepare(
			'SELECT user_id, user_password FROM ' . $this->authTable . ' where user_email = ? ORDER BY user_id LIMIT 1' );
		$query->execute( array ( $user ) );
		$result = $query->fetch();
		if( false == $result || empty( $result[ 'user_id' ] ) || empty( $result[ 'user_password' ] ) )
		{
			$this->alert( 'Account not found', ALERT_DENIED );
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
		$this->alert( 'Symmetric en/decryption is' . ( $rawToken != $rawToken2 ? ' NOT' : '' ) . ' working', ALERT_DEBUG );

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
			. 'This reset token is valid for ' . ( $resetTimer / 60.0 ) . ' minutes from ' . date( TIME_FORMAT, $time ) . NL,
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
<form id="auth-form" name="auth" action="' . $this->content[ 'rel_path' ] . 'auth" method="post" class="pure-form">';

			$setButton = AUTH_CHANGE;
			if( $this->canResetPassword )
			{
				$changePassword = true;
				$setButton = AUTH_RESET;
				$main .= '
		<input type="hidden" name="reset" value="' . $this->resetToken . '" />';
			}

			# Login / Register / Change / Reset password
			$main .= '
		<label for="user">E-mail address: </label><input type="text" id="user" name="user" value="' . $_SESSION[ 'user' ] . '" '
				. ( $changePassword ? 'readonly="readonly" ' : '' ) . '/><br />
		<label for="login-password">Password: </label><input type="password" id="login-password" name="login_password" value="" />
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
	
	/* Password manager */
	
	public function loadEntries( $search = '' )
	{
		# To do: InnoDB fulltext index ( Requires MySQL 5.6+ )
		$queryParams = array ( 'user_id' => $_SESSION[ 'user_id' ] );
		$searchQuery = '';
		if( $search )
		{
			$searchQuery = ' AND ( label LIKE :search OR username LIKE :search OR password LIKE :search OR url LIKE :search OR notes LIKE :search )';
			$queryParams[ 'search' ] = '%' . $search . '%';
		}
		
		$query = $this->database->prepare(
			'SELECT entry_id, label FROM ' . $this->pwmTable . ' WHERE user_id = :user_id' . $searchQuery . ' ORDER BY label' );
		$query->execute( $queryParams );
		$result = $query->fetchAll();
		if( false != $result )
		{
			$this->entries = $result;
		}
	}

	public function entryIsMine( $selected )
	{
		$query = $this->database->prepare( 'SELECT user_id FROM ' . $this->pwmTable . ' where entry_id = ? LIMIT 1' );
		$query->execute( array ( $selected ) );
		$result = $query->fetch();
		if( false == $result )
		{
			$this->alert( 'Entry not found', ALERT_ERROR );
			return false;
		}		
		
		if( $result[ 'user_id' ] != $_SESSION[ 'user_id' ] )
		{
			$this->alert( 'The selected entry does not belong to you', ALERT_DENIED );
			return false;
		}
		return true;
	}

	# Check that no fields are missing (they can be 0 or '')
	public function copyEntryFields( &$entry, $fields = null )
	{
		if( ! is_array( $entry ) )
		{
			$this->alert( 'copyEntryFields(): entry not an array', ALERT_DEBUG );
			return false;
		}
		if( null === $fields )
		{
			$fields = $this->fields;
		}
		if( ! is_array( $fields ) )
		{
			$this->alert( 'copyEntryFields(): fields not an array', ALERT_DEBUG );
			return false;
		}
		
		$missing = array ( );
		$entryCopy = array ( );
		foreach( $fields as $field )
		{
			if( ! isset( $entry[ $field ] ) )
			{
				$missing[] = $field;
			} else {
				$entryCopy[ $field ] = $entry[ $field ];
			}
		}
		if( count( $missing ) )
		{
			$this->alert( 'copyEntryFields(): Fields missing: ' . implode( ', ', $missing ),
				ALERT_DEBUG );
			return false;
		}
		return $entryCopy;
	}
	
	public function loadEntry( $selected )
	{
		if( ! $selected || ! $this->entryIsMine( $selected ) )
		{
			return false;
		}
		$query = $this->database->prepare( 'SELECT * FROM ' . $this->pwmTable . ' where entry_id = ? LIMIT 1' );
		$query->execute( array ( $selected ) );
		$result = $query->fetch();
		$this->entry = $result;

		$this->alert( 'Entry ' . $selected . ' loaded', ALERT_DEBUG );
		return true;
	}
	
	public function saveEntry( $entry )
	{
		if( ! $this->copyEntryFields( $entry ) )
		{
			$this->alert( 'Invalid entry submitted for storage', ALERT_ERROR );
			return false;
		}
		
		if( empty( $entry[ 'label' ] ) )
		{
			$this->alert( 'Entries must have a label', ALERT_DENIED );
			return false;
		}

		if( empty( $entry[ 'entry_id' ] ) )
		{
			# Insert entry
			$user_id = $_SESSION[ 'user_id' ];
			$query = $this->database->prepare(
				'INSERT INTO ' . $this->pwmTable . ' (user_id, label, username, password, url, notes) VALUES (?, ?, ?, ?, ?, ?)' );
			$query->execute( array ( $_SESSION[ 'user_id' ], $entry[ 'label' ], $entry[ 'username' ],
				$entry[ 'password' ], $entry[ 'url' ], $entry[ 'notes' ] ) );
			$_SESSION[ 'selected' ] = $this->database->lastInsertId();
			$this->alert( 'Created entry ' . htmlspecialchars( $entry[ 'label' ] ), ALERT_NOTE );
		} else {
			$entry_id = $entry[ 'entry_id' ];
			if( ! $this->entryIsMine( $entry_id ) )
			{
				return false;
			}
			# Update entry
			$query = $this->database->prepare(
				'UPDATE ' . $this->pwmTable . ' SET label = ?, username = ?, password = ?, url = ?, notes = ? WHERE entry_id = ?' );
			$query->execute( array ( $entry[ 'label' ], $entry[ 'username' ],
				$entry[ 'password' ], $entry[ 'url' ], $entry[ 'notes' ], $entry_id ) );
			$this->alert( 'Updated entry', ALERT_NOTE );
		}
		return true;
	}
	
	public function deleteEntry( $entry_id )
	{
		if( ! $entry_id )
		{
			$this->alert( 'Cannot delete entry 0', ALERT_ERROR );
			return false;
		}
		if( ! $this->entryIsMine( $entry_id ) )
		{
			return false;
		}
		$query = $this->database->prepare( 'DELETE FROM ' . $this->pwmTable . ' WHERE entry_id = ?' );
		$query->execute( array ( $entry_id ) );

		$this->alert( 'Deleted entry', ALERT_NOTE );
		return true;
	}
	
	public function editAction()
	{
		if( isset( $_POST[ 'edit' ] ) )
		{
			$this->alert( 'Edit action: ' . htmlspecialchars( $_POST[ 'edit' ] ), ALERT_DEBUG );

			$entry = $this->copyEntryFields( $_POST );
			if( ! $entry )
			{
				var_export( $_POST );
				$this->alert( 'Entry data incomplete', ALERT_ERROR );
				return false;
			}

			switch( $_POST[ 'edit' ] )
			{
				case ENTRY_CREATE:
					if( $entry[ 'entry_id' ] )
					{
						$this->alert( 'Entry ID should not be specified when creating', ALERT_ERROR );
					}
					$entry[ 'entry_id' ] = 0;
					$this->saveEntry( $entry );
					break;

				case ENTRY_UPDATE:
					$this->saveEntry( $entry );
					break;

				case ENTRY_DELETE:
					if( ! $this->deleteEntry( $entry[ 'entry_id' ] ) )
					{
						return false;
					}
					$this->selected = 0;
					break;

				default:
					$this->alert( 'Invalid edit action requested', ALERT_ERROR );
					return false;
			}
		}
		return true;
	}
	
	public function passwordManager()
	{
		# Logged into application
#		$this->alert( 'User ID ' . $_SESSION[ 'user_id' ], ALERT_DEBUG );

#		$this->content[ 'menu' ][ 'New entry' ] = 'new';
		$this->content[ 'menu' ][ $_SESSION[ 'user' ] ] = '';
		$this->content[ 'menu' ][ 'Change password' ] = 'change';
		$this->content[ 'menu' ][ 'Log out' ] = 'logout';

		$this->selected = isset( $_POST[ 'selected' ] ) ? (int) $_POST[ 'selected' ] : 
			( isset( $_GET[ 'selected' ] ) ? (int) $_GET[ 'selected' ] :
			( isset( $_SESSION[ 'selected' ] ) ? (int) $_SESSION[ 'selected' ] : '' ) );

		$this->editAction();

		$search = '';
		if( ! isset( $_POST[ 'reset' ] ) )
		{
			$search = isset( $_POST[ 'search' ] ) ? $_POST[ 'search' ] :
				( isset( $_GET[ 'search' ] ) ? $_GET[ 'search' ] :
				( isset( $_SESSION[ 'search' ] ) ? $_SESSION[ 'search' ] : '' ) );
		}
		$_SESSION[ 'search' ] = $search;

		$this->loadEntries( $search );
#		$this->alert( 'Entries: ' . var_export( $this->entries, true ), ALERT_DEBUG );

		# Check selected is in list
		if( $this->selected )
		{
			$foundSelected = false;
			foreach( $this->entries as $entry )
			{
				if( $this->selected == $entry[ 'entry_id' ] )
				{
					$foundSelected = true;
					break;
				}
			}
			if( ! $foundSelected )
			{
				$this->alert( 'Selected not in search results', ALERT_DEBUG );
				$this->selected = 0;
			}
		}

		$newEntry = isset( $_POST[ 'new' ] ) || isset( $_GET[ 'new' ] ) || ! $this->selected;

		$this->alert( 'Search: ' . htmlspecialchars( $search ) . ' Selected: ' . (int) $this->selected, ALERT_DEBUG );

		if( $newEntry || ! $this->loadEntry( $this->selected ) )
		{
			$this->selected = 0;
			$newEntry = true;
			$this->entry = array_fill_keys( $this->fields, '' );
		}
		$_SESSION[ 'selected' ] = $this->selected;

		$main = 
'		<form id="search-form" action="' . $this->content[ 'rel_path' ] . 'search" method="post" class="pure-form">
			<div class="textinput-bar">
				<input type="text" id="search" name="search" value="' . htmlspecialchars( $search ) . '" placeholder="Search" />
				<span class="nowrap">
					<input type="submit" id="search-button" value="Search"  />
					<input type="submit" name="reset" value="X" />
				</span>
			</div>
		</form>
		<form id="selector-form" action="' . $this->content[ 'rel_path' ] . 'select" method="post" class="pure-form">
			<select id="selector" name="selected" size="5">
';

		foreach( $this->entries as $entry )
		{
#			$this->alert( 'Entry: ' . $index . ' => ' . var_export( $entry, true ), ALERT_DEBUG );
			
			$entry_id = $entry[ 'entry_id' ];
			$label = $entry[ 'label' ];
			$main .= 
'					<option value="' . $entry_id . '" title="' . $label . '"' . ( $entry_id == $this->selected ? ' selected="selected"' : '' ) . '>'
				. $label . '</option>';
		}

		/*
		Label
		Username - Copy to clipboard
		Password - Show / Copy to clipboard / Generate 
		Password confirmation - Match indicator
		URL - Copy to clipboard / Open site
		Notes
		*/
		$main .= '
			</select>
			<div id="selector-buttons" class="button-bar">
				<input id="select-entry" type="submit" value="Select" />
				<input type="submit"' . ( ! (int) $this->selected ? ' class="hidden"' : '' )  . ' name="new" value="New" />
			</div>
		</form>
		<form id="entry-form" action="' . $this->content[ 'rel_path' ] . 'edit" method="post" class="pure-form">
			<input type="hidden" name="entry_id" value="' . $this->selected . '" />
			<label for="label">Label: </label><input type="text" id="label" name="label" value="' . $this->entry[ 'label' ] . '" />
			<label for="username">Username: </label><input type="text" id="username" name="username" value="' . $this->entry[ 'username' ] . '" />
			<label for="password">Password: </label><input type="text" id="password" name="password" value="' . $this->entry[ 'password' ] . '" />
			<label for="url">Web address: </label><input type="text" id="url" name="url" value="' . $this->entry[ 'url' ] . '" />
			<label for="notes" class="textarea-label">Notes: </label><br />
			<textarea id="notes" name="notes">' . $this->entry[ 'notes' ] . '</textarea>
			<div class="button-bar">
				<input type="submit" name="edit" value="' . ( $newEntry ? ENTRY_CREATE : ENTRY_UPDATE ) . '"  />
				<input type="submit" name="edit" value="' . ENTRY_DELETE . '"  />
			</div>
		</form>';

		$this->content[ 'main' ] = $main;
	}
}