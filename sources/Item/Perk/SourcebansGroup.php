<?php
/**
 * @brief				Sourcebans Group Item Perk Model

 */

namespace IPS\donate\Item\Perk;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * SourcebansGroup
 */
class _SourcebansGroup extends \IPS\donate\Item\Perk
{
	/**
	 * @brief	Traits
	 */
	public static $traits = array( \IPS\donate\Item\Perk::TRAIT_STEAMID );
	
	/**
	 * @brief	Data keys
	 */
	public static $dataKeys = array( 'group_id' );
	
	/**
	 * @brief	Cached db
	 */
	protected static $_db;
	
	/**
	 * Db
	 *
	 * @return	\IPS\Db
	 */
	protected static function db()
	{
		if ( !isset( static::$_db ) )
		{
			$credentials = \IPS\donate\Application::decryptArray( array(
				'sql_host' => \IPS\Settings::i()->donate_sourcebans_db_host,
				'sql_user' => \IPS\Settings::i()->donate_sourcebans_db_user,
				'sql_pass' => \IPS\Settings::i()->donate_sourcebans_db_pass,
				'sql_database' => \IPS\Settings::i()->donate_sourcebans_db_database,
				'sql_port' => \IPS\Settings::i()->donate_sourcebans_db_port ?: NULL
			) );
			
			static::$_db = \IPS\Db::i( 'sourcebans', $credentials );
		}
		
		return static::$_db;
	}
	
	/**
	 * Set group
	 *
	 * @param	\IPS\Member	$member	Member
	 * @param	\Steam\SteamID $steamId
	 * @return	void
	 */
	protected function setGroup( \IPS\Member $member, \Steam\SteamID $steamId )
	{
		if ( !$steamId || !\IPS\Settings::i()->donate_sourcebans_groups )
		{
			return;
		}
		
		$groupSettings = json_decode( \IPS\Settings::i()->donate_sourcebans_groups, TRUE );
		$groups = $groupSettings[(string) $this->group_id]['groups'];
		$currentGroup = NULL;
		$newGroup = NULL;
		
		try
		{
			$currentGroup = $this->getGroup( $steamId );
		}
		catch ( \UnderflowException $e )
		{
			$this->createUser( $member, $steamId, $groups['Default'] );
			throw new \UnderflowException( 'Creating new user: ' . $steamId->RenderSteam2(), $e->getCode(), $e );
		}
		
		if ( $currentGroup === NULL )
		{
			return;
		}
		else if ( isset( $groups['Default'] ) && $currentGroup === '' )
		{
			$newGroup = $groups['Default'];
		}
		else
		{
			foreach ( $groups as $k => $v )
			{
				if ( $currentGroup === $k )
				{
					$newGroup = $v;
					break;
				}
			}
		}
		
		if ( $newGroup !== NULL )
		{
			$this->db()->update( 'sb_admins', array( 'srv_group' => $newGroup ), $this->steamIdQueryCondition( $steamId ) );
		}
	}
	
	/**
	 * Unset group
	 *
	 * @param	\IPS\Member	$member	Member
	 * @param	\Steam\SteamID $steamId
	 * @return	void
	 */
	protected function unsetGroup( \IPS\Member $member, \Steam\SteamID $steamId )
	{
		if ( !$steamId || !\IPS\Settings::i()->donate_sourcebans_groups )
		{
			return;
		}
		
		$groupSettings = json_decode( \IPS\Settings::i()->donate_sourcebans_groups, TRUE );
		$groupIdArray = $groupSettings[(string) $this->group_id];
		$groups = $groupIdArray['groups'];
		
		if ( $member->member_id )
		{
			$memberGroups = array_filter( explode( ',', $member->mgroup_others ) );
			
			foreach ( $groupSettings as $k => $v )
			{
				if ( array_search( (int) $k, $memberGroups ) !== FALSE )
				{
					if ( isset( $groupIdArray['unset_groups'] ) && isset(  $groupIdArray['unset_groups'][(string) $k] ) )
					{
						$groups = $groupIdArray['unset_groups'][(string) $k];
					}
				}
			}
		}
		
		$currentGroup = $this->getGroup( $steamId );
		$newGroup = NULL;
		
		if ( $currentGroup === NULL )
		{
			return;
		}
		else if ( isset( $groups['Default'] ) && $currentGroup === $groups['Default'] )
		{
			$newGroup = '';
		}
		else
		{
			foreach ( $groups as $k => $v )
			{
				if ( $currentGroup === $v )
				{
					$newGroup = $k;
					break;
				}
			}
		}
		
		if ( $newGroup !== NULL )
		{
			$this->db()->update( 'sb_admins', array( 'srv_group' => $newGroup ), $this->steamIdQueryCondition( $steamId ) );
		}
	}
	
	/**
	 * Get group
	 *
	 * @param	\Steam\SteamID $steamId
	 * @return	string
	 */
	protected function getGroup( \Steam\SteamID $steamId )
	{
		return (string) $this->db()->select( 'srv_group', 'sb_admins', $this->steamIdQueryCondition( $steamId ) )->first();
	}
	
	/**
	 * Create user
	 *
	 * @param	\IPS\Member	$member	Member
	 * @param	\Steam\SteamID $steamId
	 * @param	string $group
	 * @return	void
	 */
	protected function createUser( \IPS\Member $member, \Steam\SteamID $steamId, $group )
	{
		$steamId->SetAccountUniverse( 0 );
		$steamId2 = $steamId->RenderSteam2();
		$this->db()->insert( 'sb_admins', array( 'user' => $member->name, 'authid' => $steamId2, 'password' => '1fcc1a43dfb4a474abb925f54e65f426e932b59e', 'gid' => '-1', 'email' => '', 'extraflags' => '0', 'srv_group' => $group, 'srv_flags' => NULL, 'srv_password' => NULL ) );
		$aid = $this->db()->select( 'aid', 'sb_admins', array( 'authid=?', $steamId2 ) )->first();
		$gid = $this->db()->select( 'id', 'sb_srvgroups', array( 'name=?', $group ) )->first();
		$this->db()->insert( 'sb_admins_servers_groups', array( 'admin_id' => $aid, 'group_id' => $gid, 'srv_group_id' => '1', 'server_id' => '-1' ) );
	}
	
	/**
	 * SteamId query condition
	 *
	 * @param	\Steam\SteamID $steamId
	 * @return	array
	 */
	protected function steamIdQueryCondition( \Steam\SteamID $steamId )
	{
		$steamId->SetAccountUniverse( 0 );
		$steamId2U0 = $steamId->RenderSteam2();
		$steamId->SetAccountUniverse( 1 );
		$steamId2U1 = $steamId->RenderSteam2();
		
		return array( 'authid=? OR authid=?', $steamId2U0, $steamId2U1 );
	}
	
	/**
	 * On purchase activated
	 *
	 * @param	\IPS\donate\purchase	$purchase	Purchase
	 * @return	void
	 */
	public function onPurchaseActivated( \IPS\donate\Purchase $purchase )
	{
		$steamId = $purchase->getSteamId();
		
		if ( !$steamId )
		{
			return;
		}
		
		$this->setGroup( $purchase->member, $steamId );
	}
	
	/**
	 * On purchase renewed
	 *
	 * @param	\IPS\donate\purchase	$purchase	Purchase
	 * @return	void
	 */
	public function onPurchaseRenewed( \IPS\donate\Purchase $purchase )
	{
		$steamId = $purchase->getSteamId();
		
		if ( !$steamId )
		{
			return;
		}
		
		$this->setGroup( $purchase->member, $steamId );
	}
	
	/**
	 * On purchase deactivated
	 *
	 * @param	\IPS\donate\purchase	$purchase	Purchase
	 * @return	void
	 */
	public function onPurchaseDeactivated( \IPS\donate\Purchase $purchase )
	{
		$steamId = $purchase->getSteamId();
		
		if ( !$steamId )
		{
			return;
		}
		
		$this->unsetGroup( $purchase->member, $steamId );
	}
}