<?php
/**
 * @brief				Store Credits Item Perk Model

 */

namespace IPS\donate\Item\Perk;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * StoreCredits
 */
class _StoreCredits extends \IPS\donate\Item\Perk
{
	/**
	 * @brief	Traits
	 */
	public static $traits = array( \IPS\donate\Item\Perk::TRAIT_STEAMID );
	
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
				'sql_host' => \IPS\Settings::i()->donate_csgostore_db_host,
				'sql_user' => \IPS\Settings::i()->donate_csgostore_db_user,
				'sql_pass' => \IPS\Settings::i()->donate_csgostore_db_pass,
				'sql_database' => \IPS\Settings::i()->donate_csgostore_db_database,
				'sql_port' => \IPS\Settings::i()->donate_csgostore_db_port ?: NULL
			) );
			
			static::$_db = \IPS\Db::i( 'csgostore', $credentials );
		}
		
		return static::$_db;
	}
	
	/**
	 * Add credits
	 *
	 * @param	\Steam\SteamID $steamId
	 * @param	integer $credits
	 * @return	void
	 * throws \UnderflowException
	 */
	protected function addCredits( \Steam\SteamID $steamId, $credits )
	{
		if ( !$steamId || !$credits )
		{
			return;
		}
		
		$steamId->SetAccountUniverse( 1 );
		$steamId2 = $steamId->RenderSteam2();
		
		try
		{
			$currentCredits = (int) $this->db()->select( 'credits', 'sgstore_users', array( 'steamid=?', $steamId2 ) )->first();
			$this->db()->update( 'sgstore_users', array( 'credits' => $currentCredits + $credits ), array( 'steamid=?', $steamId2 ) );
		}
		catch ( \UnderflowException $e )
		{
			throw new \UnderflowException( 'Steam ID not found: ' . $steamId2, $e->getCode(), $e );
		}
	}
	
	/**
	 * Calculate credits
	 *
	 * @param	\IPS\donate\Money $amount
	 * @return	integer
	 */
	public function calculateCredits( \IPS\donate\Money $amount )
	{
		if ( !$amount || $amount->isEqualTo( 0 ) )
		{
			return 0;
		}
		
		$amount = (float) $amount->format;
		$credits = 0;
		$ratio = 0;
		
		if ( $amount <= 10 )
		{
			$ratio = 2.5;
		}
		else if ( $amount > 10 && $amount <= 15 )
		{
			$ratio = 2.15;
		}
		else if ( $amount > 15 && $amount < 30 )
		{
			$ratio = 2;
		}
		else if ( $amount >= 30 && $amount <= 35 )
		{
			$ratio = 1.88;
		}
		else if ( $amount > 35 && $amount <= 40 )
		{
			$ratio = 1.6;
		}
		else if ( $amount > 40 && $amount <= 45 )
		{
			$ratio = 1.5;
		}
		else if ( $amount > 45 )
		{
			$ratio = 1.4275;
		}
		
		for ( $i = 0; $i < $amount; $i++ )
		{
			if ( $i > 50 )
			{
				$ratio = 1;
			}
			
			$credits += 1 / $ratio;
		}
		
		return (int) round( round( $credits, 1 ) * 1000 );
	}
	
	/**
	 * On purchase created
	 *
	 * @param	\IPS\donate\purchase	$purchase	Purchase
	 * @return	void
	 */
	public function onPurchaseCreated( \IPS\donate\Purchase $purchase )
	{
		$steamId = $purchase->getSteamId();
		
		if ( !$purchase->order || !$steamId )
		{
			return;
		}
		
		$credits = $this->calculateCredits( $purchase->order->cost );
		$this->addCredits( $steamId, $credits );
	}
}