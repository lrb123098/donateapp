<?php
/**
 * @brief				Item Model

 */

namespace IPS\donate;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Item
 */
class _Item extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'donate_items';
	
	/**
	 * @brief	[ActiveRecord] Attempt to load from cache
	 */
	protected static $loadFromCache = TRUE;
	
	/**
	 * [ActiveRecord] Attempt to load cached data
	 *
	 * @return	mixed
	 */
	public static function getStore()
	{
		if ( !isset( \IPS\Data\Store::i()->donate_items ) )
		{
			\IPS\Data\Store::i()->donate_items = parent::getStore();
		}
		
		return \IPS\Data\Store::i()->donate_items;
	}
	
	/**
	 * Reset cached data
	 *
	 * @return	mixed
	 */
	public static function resetCache()
	{
		if ( isset( \IPS\Data\Store::i()->donate_items ) )
		{
			unset( \IPS\Data\Store::i()->donate_items );
			static::getStore();
		}
	}
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	protected function setDefaultValues()
	{
		$this->enabled = TRUE;
	}
	
	/**
	 * @brief	Cached items
	 */
	protected static $_items;
	
	/**
	 * Get items
	 *
	 * @return	array
	 */
	public static function items()
	{
		if ( !isset( static::$_items ) )
		{
			static::$_items = iterator_to_array( new \IPS\Patterns\ActiveRecordIterator( new \ArrayIterator( static::getStore() ), 'IPS\donate\Item' ) );
		}
		
		return static::$_items;
	}
	
	/**
	 * @brief	Cached price
	 */
	protected $_price;
	
	/**
	 * Get price
	 *
	 * @return	\IPS\donate\Money
	 */
	public function get_price()
	{
		if ( !isset( $this->_price ) )
		{
			$this->_price = \IPS\donate\Money::constructFromBase( $this->_data['price'] );
		}
		
		return $this->_price;
	}
	
	/**
	 * Set price
	 *
	 * @param	\IPS\donate\Money	$amount	Amount
	 * @return	void
	 */
	public function set_price( \IPS\donate\Money $amount )
	{
		$this->_price = $amount;
		$this->_data['price'] = $this->_price->amount;
	}
	
	/**
	 * @brief	Cached active length
	 */
	protected $_activeLength;
	
	/**
	 * Get active length
	 *
	 * @return	int
	 */
	public function get_active_length()
	{
		if ( !isset( $this->_activeLength ) && isset( $this->_data['active_length'] ) )
		{
			$this->_activeLength = $this->calcActiveLength( $this->get_active_length_array() );
		}
		
		return $this->_activeLength;
	}
	
	/**
	 * Get active length array
	 *
	 * @return	string
	 */
	public function get_active_length_array()
	{
		return explode( ',', $this->_data['active_length'] ) ?: NULL;
	}
	
	/**
	 * Set active length
	 *
	 * @param	array	$activeLength	Active length
	 * @return	void
	 */
	public function set_active_length( $activeLength )
	{
		if ( $activeLength )
		{
			$this->_activeLength = $this->calcActiveLength( $activeLength );
			$this->_data['active_length'] = implode( ',', $activeLength );
		}
		else
		{
			$this->_activeLength = NULL;
			$this->_data['active_length'] = NULL;
		}
	}
	
	/**
	 * calcActiveLength
	 *
	 * @param	array	$activeLength	Active length
	 * @return	int
	 */
	protected function calcActiveLength( $activeLength )
	{
		$interval = ( new \DateTime )->add( new \DateInterval( 'P' . implode( '', $activeLength ) ) )->diff( new \DateTime );
		return $interval->days * 86400;
	}
	
	/**
	 * @brief	Cached payment options
	 */
	protected $_paymentOptions;
	
	/**
	 * Get payment options
	 *
	 * @return	array
	 */
	public function get_payment_options()
	{
		if ( !isset( $this->_paymentOptions ) )
		{
			$options = json_decode( $this->_data['payment_options'], TRUE ) ?: array();
			
			foreach ( $options as $k => $option )
			{
				if ( !isset( $option['payment_type'] ) || !$option['payment_type'] || !isset( $option['payment_type'] ) || !$option['price_type'] )
				{
					continue;
				}
				
				$options[$k] = new \IPS\donate\Item\PaymentOption( $option['payment_type'], $option['price_type'] );
			}
			
			$this->_paymentOptions = $options;
		}
		
		return $this->_paymentOptions;
	}
	
	/**
	 * Set payment options
	 *
	 * @param	array	$options	Options
	 * @return	void
	 */
	public function set_payment_options( $options )
	{
		if ( $json = json_encode( $options ) )
		{
			$this->_paymentOptions = $options;
			$this->_data['payment_options'] = $json;
		}
	}
	
	/**
	 * Has payment option
	 *
	 * @param	string	$paymentType	Payment type
	 * @param	string	$priceType	Price type
	 * @return	boolean
	 */
	public function hasPaymentOption( $paymentType = NULL, $priceType = NULL )
	{
		if ( !$paymentType && !$priceType )
		{
			return FALSE;
		}
		
		foreach ( $this->payment_options as $option )
		{
			if ( $paymentType && $priceType )
			{
				if ( $option->paymentType === $paymentType && $option->priceType === $priceType )
				{
					return TRUE;
				}
			}
			else
			{
				if ( ( $paymentType && $option->paymentType === $paymentType ) || ( $priceType && $option->priceType === $priceType ) )
				{
					return TRUE;
				}
			}
		}
		
		return FALSE;
	}
	
	/**
	 * @brief	Cached provider data
	 */
	protected $_providerData;
	
	/**
	 * Get provider data
	 *
	 * @param	string	$providerId	Provider id
	 * @return	array
	 */
	public function getProviderData( $providerId )
	{
		if ( !isset( $this->_providerData ) && isset( $this->_data['provider_data'] ) )
		{
			$this->_providerData = json_decode( $this->_data['provider_data'], TRUE ) ?: array();
		}
		
		$data = NULL;
		
		if ( isset( $this->_providerData ) && isset( $this->_providerData[$providerId] ) )
		{
			$data = $this->_providerData[$providerId];
		}
		
		return $data;
	}
	
	/**
	 * Set provider data
	 *
	 * @param	string	$providerId	Provider id
	 * @param	mixed	$data	Data
	 * @param	string	$key	Key
	 * @return	void
	 */
	public function setProviderData( $providerId, $data, $key = NULL )
	{
		if ( !isset( $this->_providerData ) )
		{
			$this->_providerData = array();
		}
		
		if ( !isset( $this->_providerData[$providerId] ) || !$this->_providerData[$providerId] )
		{
			$this->_providerData[$providerId] = array();
		}
		
		if ( $key )
		{
			$this->_providerData[$providerId][$key] = $data;
		}
		else
		{
			$this->_providerData[$providerId] = $data;
		}
		
		if ( $json = json_encode( $this->_providerData ) )
		{
			$this->provider_data = $json;
		}
	}
	
	/**
	 * @brief	Cached perks
	 */
	protected $_perks;
	
	/**
	 * Get perks
	 *
	 * @return	array
	 */
	public function get_perks()
	{
		if ( !isset( $this->_perks ) && isset( $this->_data['perks'] ) )
		{
			$perks = json_decode( $this->_data['perks'], TRUE ) ?: array();
			
			foreach ( $perks as $k => $perk )
			{
				if ( !isset( $perk['id'] ) || !$perk['id'] )
				{
					continue;
				}
				
				if ( !$perks[$k] = \IPS\donate\Item\Perk::constructFromId( $perk['id'], isset( $perk['data'] ) ? $perk['data'] : NULL ) )
				{
					unset( $perks[$k] );
				}
			}
			
			$this->_perks = $perks;
		}
		
		return $this->_perks;
	}
	
	/**
	 * Set perks
	 *
	 * @param	array	$perks	Perks
	 * @return	void
	 */
	public function set_perks( $perks )
	{
		if ( $json = json_encode( $perks ) )
		{
			$this->_perks = $perks;
			$this->_data['perks'] = $json;
		}
	}
	
	/**
	 * Has perk
	 *
	 * @param	string	$id	Id
	 * @return	\IPS\donate\Item\Perk
	 */
	public function getPerk( $id )
	{
		if ( !$id )
		{
			return NULL;
		}
		
		foreach ( $this->perks as $perk )
		{
			if ( $perk->id === $id )
			{
				return $perk;
			}
		}
		
		return NULL;
	}
	
	/**
	 * @brief	Cached perks desc
	 */
	protected $_perksDesc;
	
	/**
	 * Get perks desc
	 *
	 * @return	mixed
	 */
	public function get_perks_desc()
	{
		if ( !isset( $this->_perksDesc ) && isset( $this->_data['perks_desc'] ) )
		{
			if ( $this->_data['perks_desc'][0] === '[' || $this->_data['perks_desc'][0] === '{' )
			{
				$lastChar = \mb_strlen( $this->_data['perks_desc'] ) - 1;
				
				if ( $this->_data['perks_desc'][$lastChar] === ']' || $this->_data['perks_desc'][$lastChar] === '}' )
				{
					$this->_perksDesc = json_decode( $this->_data['perks_desc'], TRUE ) ?: array();
				}
			}
			else
			{
				$this->_perksDesc = $this->_data['perks_desc'];
			}
		}
		
		return $this->_perksDesc;
	}
	
	/**
	 * Set perks desc
	 *
	 * @param	mixed	$perksDesc	Perks desc
	 * @return	void
	 */
	public function set_perks_desc( $perksDesc )
	{
		if ( \is_array( $perksDesc ) )
		{
			if ( $json = json_encode( $perksDesc ) )
			{
				$this->_data['perks_desc'] = $json;
			}
		}
		elseif ( \is_string( $perksDesc ) || \is_numeric( $perksDesc ) )
		{
			$this->_data['perks_desc'] = (string) $perksDesc;
		}
		else
		{
			return;
		}
		
		$this->_perksDesc = $this->_data['perks_desc'];
	}
}