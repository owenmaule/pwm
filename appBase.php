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
	
	---
	To do:
		Namespaces
		Packaging
		Multiple DSN database connections
*/

class appBase
{
	public $config = array ( );
	public $database = null;
	public $content = array (
		'title' => '',
		'menu' => array ( ),
		'alert' => array ( ),
		'main' => '<p>An error has occurred.</p>',
		'rel_path' => '',
	);
	public $theme = 'template.php';

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

		if( empty( $config[ 'dsn' ] ) || ! isset( $config[ 'db_user' ] )
			|| ! isset( $config[ 'db_password' ] ) )
		{
			throw new Exception ( 'Missing database configuration' );
		}

		$this->setAppLocation();
		// Need to generalise these pass-throughs soon
		$this->content[ 'disable_clipboard' ] = ! empty( $config[ 'disable_clipboard' ] );
		$this->content[ 'debug_layout' ] = ! empty( $config[ 'debug_layout' ] );
		$this->content[ 'disable_javascript' ] = ! empty( $config[ 'disable_javascript' ] );

		$this->seedRNG();

		if( true !== ( $errorMessage = $this->install() ) )
		{
			throw new Exception ( $errorMessage );
		}

		session_start();
	}
	
	# Seed Random Number Generator with microseconds
	public function seedRNG()
	{
		list( $usec, $sec ) = explode( ' ', microtime() );
		mt_srand( (float) $sec + ( (float) $usec * 100000 ) );
	}
	
	# Set up database
	# Return true for success or array of error messages on failure
	public function install()
	{
		$this->alert( 'appBase install()', ALERT_DEBUG );
		# Open database connection
		try {
			$this->database = new PDO( $this->config[ 'dsn' ],
					$this->config[ 'db_user' ], $this->config[ 'db_password' ],
				array ( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC )
			);
			if( empty( $this->database ) )
			{
				throw new Exception ( 'Invalid configuration. See warning above.' );
			}
		} catch ( Exception $e )
		{
			throw new Exception ( 'Failed to open data store ' . $this->config[ 'dsn' ] . BR
				. 'Exception: ' . $e->getMessage() );
		}
		
		$installErrors = array ( );

		# Check / create database tables
		foreach( $this->config[ 'db_tables' ] as $table )
		{
			if( true !== ( $errorMessage = $this->checkCreateTable( $table[ 'name' ], $table[ 'schema' ] ) ) )
			{
				$installErrors[] = $errorMessage;
			}
		}
		return true;
	}

	public function setAppLocation()
	{
		$found = false;
		if( ! empty( $this->config[ 'app_location' ] ) )
		{
			$appLocation = $this->config[ 'app_location' ];
			$this->content[ 'abs_path' ] = $appLocation;
			$urlParts = parse_url( $appLocation );
			if( ! empty( $urlParts[ 'path' ] ) )
			{
				$this->content[ 'rel_path' ] = $urlParts[ 'path' ];
				$found = true;
			}
		}
		if( ! $found )
		{
			# Try an alternative method, maybe using path_up? Dodgy but could be okayish?
			$pathUp = ! empty( $_GET[ 'path_up' ] ) ? '../' : '';
#			$this->alert( 'Configuration error: app_location', ALERT_ERROR );

			# For now make it mandatory
			throw new Exception( 'Configuration error: app_location' );
		}
		return $found;
	}
	
	# Setup default schema and return the table name
	function supplyTableDefaults( $key, $defaultName, $defaultSchema )
	{
		if( empty( $this->config[ 'db_tables' ] ) )
		{
			$this->config[ 'db_tables' ] = array ( $key => array ( 'name' => $defaultName ) );
		}
		else if( empty( $this->config[ 'db_tables' ][ $key ] ) )
		{
			$this->config[ 'db_tables' ][ $key ] = array ( 'name' => $defaultName );
		}

		# Supply default schema
		if( empty( $this->config[ 'db_tables' ][ $key ][ 'schema' ] ) )
		{
			$this->config[ 'db_tables' ][ $key ][ 'schema' ] = $defaultSchema;
		}
		
		$this->alert( var_export( $this->config[ 'db_tables' ][ $key ], true ), ALERT_DEBUG );
		return $this->config[ 'db_tables' ][ $key ][ 'name' ];
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

		# Check the table name format - I think I may be getting paranoid
		# - or just really helpful to integrators
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

		$this->alert( 'Check table: ' . $fullTableName, ALERT_DEBUG );

		# Check if table exists
		if( ! $this->tableExists( $fullTableName ) )
		{
			if( empty( $this->config[ 'auto_install' ] ) )
			{
				return 'Missing database table ' . $fullTableName . ' (' . $tableConfig . ')';
			}

			try {
				$this->database->exec( 'CREATE TABLE IF NOT EXISTS ' . $fullTableName . ' '
					. $tableDefinition );
			}
			catch ( Exception $e )
			{
				return 'Unable to create database table ' . $fullTableName . ' (' . $tableConfig
					. ') - exception';
			}
	
			# In case not set to throw exceptions - check again
			if( ! $this->tableExists( $fullTableName ) )
			{
				return 'Unable to create database table ' . $fullTableName . ' (' . $tableConfig
					. ') - rechecked';
			}
			$this->alert( 'Created table: ' . $fullTableName, ALERT_DEBUG );
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
		foreach( array ( ALERT_ERROR, ALERT_NOTE, ALERT_DENY, ALERT_DEBUG, ALERT_WARN )
			as $alert )
		{
			$this->alert( ucwords( $alert ) . ': Test alert colours', $alert );
		}
	}
	
	/* Helpers - Could move to library */

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
	
	public function validURL( &$url )
	{
		if( ! is_string( $url ) )
		{
			$this->alert( 'validURL(): Not even a string', ALERT_ERROR );
			return false;
		}
		if( empty( $url ) )
		{
			$this->alert( ERROR_NO_WEBSITE, ALERT_DENY );
			return false;			
		}
		# Maybe we shouldn't strtolower is?
		$urlParts = parse_url( strtolower( $url ) );
		if( false === $urlParts )
		{
			$this->alert( 'Seriously malformed URL', ALERT_DENY );
			return false;
		}
		
		if( empty( $urlParts[ 'host' ] ) && empty( $urlParts[ 'path' ] ) )
		{
			$this->alert( ERROR_NO_WEBSITE, ALERT_DENY );
			$this->alert( 'urlParts=' . var_export( $urlParts, true ), ALERT_DEBUG );
			return false;
		}
		if( empty( $urlParts[ 'host' ] ) )
		{
			# It's all in path
			if( false === strpos( $urlParts[ 'path' ], '.' ) )
			{	# We need a Top Level Domain, we can supply a default for the lazy
				$urlParts[ 'path' ] .= '.com';
			}
		}
		if( empty( $urlParts[ 'path' ] ) )
		{
			# It's all in host
			if( false === strpos( $urlParts[ 'host' ], '.' ) )
			{
				# We need a Top Level Domain, we can supply a default for the lazy
				$urlParts[ 'host' ] .= '.com';
			}
		}
		if( empty( $urlParts[ 'scheme' ] ) )
		{
			# We need a scheme, we can supply that for the lazy
			$urlParts[ 'scheme' ] = 'http';
		}

		# Pass back by reference
		# http_build_url() requires PECL pecl_http >= 0.21.0
		$url = $this->http_build_url( '', $urlParts );
#		$this->alert( 'Click [Go!] again to open ' . htmlspecialchars( $url ), ALERT_NOTE );
		$this->alert( 'Constructed URL ' . $url, ALERT_DEBUG );
		return true;
	}
	
	public function http_build_url( $ignoredURL, $urlParts )
	{
		return ( isset( $urlParts[ 'scheme' ] ) ? $urlParts[ 'scheme' ] . '://' : '' )
			. ( isset( $urlParts[ 'host' ] ) ? $urlParts[ 'host' ] : '' )
			. ( isset( $urlParts[ 'port' ] ) ? ':' . $urlParts[ 'port' ] : '' )
			. ( isset( $urlParts[ 'user' ] ) ? $urlParts[ 'user' ] : '' )
			. ( isset( $urlParts[ 'pass' ] ) ? ':' . $urlParts[ 'pass' ]  : '' )
			. ( ( ! empty( $urlParts[ 'user' ] ) || ! empty( $urlParts[ 'pass' ] ) ) ? '@' : '' )
			. ( isset( $urlParts[ 'path' ] ) ? $urlParts[ 'path' ] : '' )
			. ( isset( $urlParts[ 'query' ] ) ? '?' . $urlParts[ 'query' ] : '' )
			. ( isset( $urlParts[ 'fragment' ] ) ? '#' . $urlParts[ 'fragment' ] : '' );
	}
}