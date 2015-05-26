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
		Tab-order
		Encrypt entry data by login password
		Refactor into class hierarchy: appBase <- authentication <- passwordManager
		Missing functionality: email field, password confirmation, password generation,
			password security analysis, limit failed logins, change email address,
			import/export, browser integration ( plugin to auto-capture credentials )
		Back-end support: FULLTEXT, SQLlite
		Internationalisation

	Template ( default theme ) to do:
		Bug: Glitch in transition between highest width mode and middle one
		Front-end Javascript enhancements
		CSS improvements ( see pwm.css )
		Continue searching for the owner of the image and check permission.
			( I expect it's okay, it's Tux and GPL software. )
*/

require_once 'constants.php';
require_once 'authentication.php';

class pwm extends authentication
{
	private $pwmTable = '';
	private $selected = 0;
	private $showPassword = false;
	private $urlLink = '';
	public $fields = array ( 'entry_id', 'label', 'username', 'password', 'url', 'notes' );
	public $entries = array ( );
	public $entry = array ( );

	public function __construct()
	{
		parent::__construct();
	}

	# Set up database
	public function install()
	{
		# Supply default table configuration
		# Save shortcut to table name
		$this->pwmTable = $this->supplyTableDefaults( 'pwm', 'pwm', '(
`entry_id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
`user_id` INT( 11 ) UNSIGNED NOT NULL ,
`label` VARCHAR( 128 ) NOT NULL ,
`username` VARCHAR( 128 ) NOT NULL ,
`password` VARCHAR( 128 ) NOT NULL ,
`url` VARCHAR( 128 ) NOT NULL ,
`notes` VARCHAR( 1024 ) NOT NULL
) ENGINE = InnoDB' );

		return parent::install();
	}
	
	/* Password manager */
	
	public function loadEntries( $search = '' )
	{
		# To do: InnoDB fulltext index ( Requires MySQL 5.6+ )
		$queryParams = array ( 'user_id' => $_SESSION[ 'user_id' ] );
		$searchQuery = '';
		if( $search )
		{
			$searchQuery = ' AND ( label LIKE :search OR username LIKE :search OR password LIKE :search '
				. 'OR url LIKE :search OR notes LIKE :search )';
			$queryParams[ 'search' ] = '%' . $search . '%';
		}
		
		$query = $this->database->prepare( 'SELECT entry_id, label FROM ' . $this->pwmTable
			. ' WHERE user_id = :user_id' . $searchQuery . ' ORDER BY label' );
		$query->execute( $queryParams );
		$result = $query->fetchAll();
		if( false != $result )
		{
			$this->entries = $result;
		}
	}

	public function entryIsMine( $selected )
	{
		$query = $this->database->prepare( 'SELECT user_id FROM ' . $this->pwmTable
			. ' where entry_id = ? LIMIT 1' );
		$query->execute( array ( $selected ) );
		$result = $query->fetch();
		if( false == $result )
		{
			$this->alert( 'Entry not found', ALERT_ERROR );
			return false;
		}		
		
		if( $result[ 'user_id' ] != $_SESSION[ 'user_id' ] )
		{
			$this->alert( 'The selected entry does not belong to you', ALERT_DENY );
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
	
	public function entryChanged( &$entry )
	{
		if( empty( $entry[ 'entry_id' ] ) )
		{
			$this->alert( 'entryChanged(): entry has no entry_id', ALERT_DEBUG );
			return true;
		}

		$fields = $this->fields;
		if( ! is_array( $fields ) )
		{
			throw new Exception( 'entryChanged(): fields is not an array' );
		}

		if( ! $this->loadEntry( $entry[ 'entry_id' ] ) )
		{
			$this->alert( 'entryChanged(): failed to load entry', ALERT_DEBUG );
			return true;
		}

		foreach( $fields as $field )
		{
			if( ! isset( $entry[ $field ] ) )
			{
				$this->alert( 'entryChanged(): entry is missing field(s)', ALERT_DEBUG );
				return true;
			}

			if( ! isset( $entry[ $field ] ) )
			{
				$this->alert( 'entryChanged(): entry from loadEntry() is missing field(s)', ALERT_DEBUG );
				return true;
			}			

			if( $this->entry[ $field ] != $entry[ $field ] )
			{
				# Entry was changed
				return true;
			}
		}

		# Entry matches database
		return false;
	}
	
	public function loadEntry( $selected )
	{
		$selected = (int) $selected;
		if( ! $selected || ! $this->entryIsMine( $selected ) )
		{
			return false;
		}
		$query = $this->database->prepare( 'SELECT * FROM ' . $this->pwmTable
			. ' where entry_id = ? LIMIT 1' );
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
			$this->alert( 'Entries must have a label', ALERT_DENY );
			return false;
		}
		
		if( ! empty( $entry[ 'url' ] ) )
		{
			# Validate it
			$checkURL = $entry[ 'url' ];
			if( $this->validURL( $checkURL ) )
			{
				if( $checkURL != $entry[ 'url' ] )
				{
					$this->alert( 'Your website was adjusted to ' . htmlspecialchars( $checkURL ), ALERT_NOTE );
					$entry[ 'url' ] = $checkURL;
					$this->alert( 'Set entry to ' . htmlspecialchars( $entry[ 'url' ] ), ALERT_DEBUG );
				}
			} else {
				# I guess we have to save their nonsense
				$this->alert( 'Saving invalid URL ' . htmlspecialchars( $entry[ 'url' ] ), ALERT_DEBUG );
			}
		}

		$this->alert( 'Entry to save ' . htmlspecialchars( $entry[ 'url' ] ), ALERT_DEBUG );
		if( empty( $entry[ 'entry_id' ] ) )
		{
			# Insert entry
			$user_id = $_SESSION[ 'user_id' ];
			$query = $this->database->prepare( 'INSERT INTO ' . $this->pwmTable
				. ' (user_id, label, username, password, url, notes) VALUES (?, ?, ?, ?, ?, ?)' );
			$query->execute( array ( $_SESSION[ 'user_id' ], $entry[ 'label' ], $entry[ 'username' ],
				$entry[ 'password' ], $entry[ 'url' ], $entry[ 'notes' ] ) );
			$_SESSION[ 'selected' ] = $this->selected = $this->database->lastInsertId();
			$this->alert( 'Created ' . htmlspecialchars( $entry[ 'label' ] ), ALERT_NOTE );
		} else {
			$entry_id = $entry[ 'entry_id' ];
			if( ! $this->entryIsMine( $entry_id ) )
			{
				return false;
			}
			# Update entry
			$query = $this->database->prepare( 'UPDATE ' . $this->pwmTable
				. ' SET label = ?, username = ?, password = ?, url = ?, notes = ? WHERE entry_id = ?' );
			$query->execute( array ( $entry[ 'label' ], $entry[ 'username' ],
				$entry[ 'password' ], $entry[ 'url' ], $entry[ 'notes' ], $entry_id ) );
			$this->alert( 'Updated ' . htmlspecialchars( $entry[ 'label' ] ), ALERT_NOTE );
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
				$this->alert( 'Entry data incomplete', ALERT_ERROR );
				return false;
			}

			$entry_id = $entry[ 'entry_id' ] = (int) $entry[ 'entry_id' ];
			switch( $_POST[ 'edit' ] )
			{
				case ENTRY_CREATE:
					if( $entry_id )
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
					if( ! $this->deleteEntry( $entry_id ) )
					{
						return false;
					}
					$this->selected = 0;
					break;

				case ENTRY_SHOW:
					if( $entry_id && $this->entryChanged( $entry ) )
					{
						$this->alert( ERROR_UNSAVED_ENTRY, ALERT_ERROR );
					}
					$_SESSION[ 'show_password' ] = $this->showPassword = true;
					break;

				case ENTRY_HIDE:
					if( $entry_id && $this->entryChanged( $entry ) )
					{
						$this->alert( ERROR_UNSAVED_ENTRY, ALERT_ERROR );
					}
					$_SESSION[ 'show_password' ] = $this->showPassword = false;
					break;

				case ENTRY_GO:
					# Check the database incase they changed it
					if( empty( $entry[ 'url' ] ) )
					{
						$this->alert( ERROR_NO_WEBSITE, ALERT_ERROR );
					} else
					{
						if( $entry_id && $this->entryChanged( $entry ) )
						{
							$this->alert( ERROR_UNSAVED_ENTRY, ALERT_ERROR );
						}
						/*
						$urlChanged = $entry[ 'entry_id' ]
								&& $this->loadEntry( $entry[ 'entry_id' ] )
								&& ! empty( $this->entry[ 'url' ] )
								&& ( $this->entry[ 'url' ] != $entry[ 'url' ] );

						if( $urlChanged )
						{
							$this->alert( ERROR_UNSAVED_ENTRY, ALERT_ERROR );
							$this->alert( 'database url=' . htmlspecialchars( $this->entry[ 'url' ] ) . BR
								. 'rest url=' . htmlspecialchars( $entry[ 'url' ] ), ALERT_DEBUG );
						}
						*/
						if( $this->validURL( $entry[ 'url' ] ) )
						{
							$this->alert( 'Click [Go!] to open ' . htmlspecialchars( $entry[ 'url' ] ),
								ALERT_NOTE );
							# Populate text box with new one, so it visually matches ?
							$this->entry[ 'url' ] = $entry[ 'url' ];
							$this->urlLink = $entry[ 'url' ];
						}
					}
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
		$this->content[ 'menu' ][ 'Change password' ] = $this->content[ 'rel_path' ] . 'change';
		$this->content[ 'menu' ][ 'Log out' ] = $this->content[ 'rel_path' ] . 'logout';

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

		$this->alert( 'Search: ' . htmlspecialchars( $search ) . ' Selected: '
			. (int) $this->selected, ALERT_DEBUG );

		# Could optimise to check if already loaded, but will load it twice if necessary
		if( $newEntry || ! $this->loadEntry( $this->selected ) )
		{
			$this->selected = 0;
			$newEntry = true;
			$this->entry = array_fill_keys( $this->fields, '' );
			$this->alert( 'New entry', ALERT_DEBUG );
		}
		$_SESSION[ 'selected' ] = $this->selected;

		$main = 
'		<form id="search-form" action="' . $this->content[ 'rel_path' ]
			. 'search" method="post" class="pure-form">
			<div class="textinput-bar">
				<input type="text" id="search" name="search" value="'
				. htmlspecialchars( $search ) . '" placeholder="Search" />
				<span class="nowrap">
					<input type="submit" id="search-button" value="Search" />
					<input type="submit" name="reset" value="X" />
				</span>
			</div>
		</form>
		<form id="selector-form" action="'
			. $this->content[ 'rel_path' ] . 'select" method="post" class="pure-form">
			<select id="selector" name="selected" size="5">
';

		foreach( $this->entries as $entry )
		{
#			$this->alert( 'Entry: ' . $index . ' => ' . var_export( $entry, true ), ALERT_DEBUG );
			
			$entry_id = $entry[ 'entry_id' ];
			$label = $entry[ 'label' ];
			$main .= 
'					<option value="' . $entry_id . '" title="' . $label . '"'
				. ( $entry_id == $this->selected ? ' selected="selected"' : '' ) . '>'
				. $label . '</option>
';
		}

		/*
		Label
		Username - Copy to clipboard
		Password - Show / Copy to clipboard / Generate 
		Password confirmation - Match indicator
		URL - Copy to clipboard / Open site
		Notes
		*/
		
		if( ! isset( $_SESSION[ 'show_password' ] ) )
		{
			$_SESSION[ 'show_password' ] = false;
		} else {
			$this->showPassword = $_SESSION[ 'show_password' ];
		}
		
		if( $this->urlLink
			&& $this->entry[ 'url' ] != $this->urlLink )
		{
			# This is not the database saved data, this was handled previously in 'case ENTRY_GO'
			$this->entry[ 'url' ] = $this->urlLink;
		}

		$goButton = ( $this->urlLink
			? '<a href="' . $this->urlLink . '" target="_blank"><input type="button" value="' . ENTRY_GO . '!" class="button2" /></a>'
			: '<input type="submit" name="edit" value="' . ENTRY_GO . '" class="button2" />' );
		
		$main .= 
'			</select>
			<div id="selector-buttons" class="button-bar">
				<input id="select-entry" type="submit" value="Select" />
				<input type="submit"' . ( ! (int) $this->selected ? ' class="hidden"' : '' )
				. ' name="new" value="New" />
			</div>
		</form>
		<form id="entry-form" action="' . $this->content[ 'rel_path' ]
			. 'edit" method="post" class="pure-form">
			<input type="hidden" name="entry_id" value="' . $this->selected . '" />
			<label for="label">Label: </label><span class="compress-field zero-button">
				<input type="text" id="label" name="label" value="'
					. $this->entry[ 'label' ] . '" autocomplete="off" />
				</span>
			<label for="username">Username: </label><span class="compress-field">
				<input type="button" value="Copy" />
				<input type="text" id="username" name="username" value="'
					. $this->entry[ 'username' ] . '" autocomplete="off" />
				</span>
			<label for="password">Password: </label><span class="compress-field two-button">
				<input type="button" value="Copy" /><input type="submit" name="edit" value="'
					. ( $this->showPassword ? ENTRY_HIDE : ENTRY_SHOW ) . '" class="button2" />
				<input type="' . ( $this->showPassword ? 'text' : 'password' )
					. '" id="password" name="password" value="'
					. $this->entry[ 'password' ] . '" autocomplete="off" />
				</span>
			<label for="url">Website: </label><span class="compress-field two-button">
				<input type="button" value="Copy" />' . $goButton . '
				<input type="text" id="url" name="url" value="' 
					. $this->entry[ 'url' ] . '" autocomplete="off"' . ( $this->urlLink ? ' readonly="readonly"' : '' ) . ' />
				</span>
			<label for="notes" class="textarea-label">Notes: </label><br />
			<textarea id="notes" name="notes">' . $this->entry[ 'notes' ] . '</textarea>
			<div class="button-bar">
				<input type="submit" name="edit" value="' . ( $newEntry ? ENTRY_CREATE : ENTRY_UPDATE )
				. '" />
				<input type="submit" name="edit" value="' . ENTRY_DELETE . '"' . ( $newEntry ? ' style="display: none"' : '' ) . ' />
			</div>
		</form>';

		$this->content[ 'main' ] = $main;
	}
}