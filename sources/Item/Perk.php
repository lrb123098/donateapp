<?php
/**
 * @brief				Item Perk Model

 */

namespace IPS\donate\Item;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Perk
 */
abstract class _Perk implements \JsonSerializable
{
	/**
	 * @brief	List of perks
	 */
	const PERKS = array(
		'member_group' => 'MemberGroup',
		'sb_group' => 'SourcebansGroup',
		'store_credits' => 'StoreCredits'
	);
	
	/**
	 * @brief	List of events
	 */
	const EVENTS = array(
		'PurchaseCreated',
		'PurchaseActivated',
		'PurchaseRenewed',
		'PurchaseExpired',
		'PurchaseDeactivated'
	);
	
	/**
	 * @brief	Trait: IPS
	 */
	const TRAIT_IPS = 'ips';
	
	/**
	 * @brief	Trait: Steam id
	 */
	const TRAIT_STEAMID = 'steamid';
	
	/**
	 * @brief	Traits
	 */
	public static $traits;
	
	/**
	 * @brief	Data keys
	 */
	public static $dataKeys;
	
	/**
	 * @brief	Id
	 */
	protected $_id;
	
	/**
	 * Get id
	 *
	 * @return	string
	 */
	public function get_id()
	{
		return $this->_id;
	}
	
	/**
	 * @brief	Data
	 */
	protected $_data;
	
	/**
	 * Constructor
	 *
	 * @param	string	$key	Key
	 * @param	array	$data	Data
	 * @return	void
	 */
	public function __construct( $id, $data = NULL )
	{
		$this->_id = $id;
		
		if ( $data && isset( static::$dataKeys ) )
		{
			$this->_data = array_intersect_key( array_filter( $data ), array_flip( static::$dataKeys ) );
		}
		else
		{
			$this->_data = array();
		}
	}
	
	/**
	 * Construct from id
	 *
	 * @param	string	$key	Key
	 * @param	array	$data	Data
	 * @return	static
	 */
	public static function constructFromId( $id, $data = NULL )
	{
		if ( !$id || !isset( \IPS\donate\Item\Perk::PERKS[$id] ) )
		{
			return NULL;
		}
		
		$classname = '\IPS\donate\Item\Perk\\' . \IPS\donate\Item\Perk::PERKS[$id];
		
		if ( !class_exists( $classname ) )
		{
			return NULL;
		}
		
		return new $classname( $id, $data ?: NULL );
	}
	
	/**
	 * Run event
	 *
	 * @param	string|array	$event	Event
	 * @param	array	$args	Args
	 * @return	void
	 */
	public function runEvent( $event, ...$args )
	{
		if ( ( !\is_string( $event ) || \mb_strlen( $event ) === 0 ) && ( !\is_array( $event ) || array_search( $event[0], static::EVENTS, TRUE ) === FALSE ) )
		{
			return;
		}
		
		if ( \is_array( $event ) && isset( static::$traits ) && array_search( $event[1], static::$traits, TRUE ) === FALSE )
		{
			return;
		}
		
		$eventName = \is_array( $event ) ? $event[0] : $event;
		$eventMethod = 'on' . $eventName;
		
		if ( method_exists( $this, $eventMethod ) )
		{
			try
			{
				$this->{$eventMethod}( ...$args );
			}
			catch ( \Exception $e )
			{
				\IPS\Log::log( $e, 'donate_perkevent' );
			}
		}
	}
	
	/**
	 * Get class value
	 *
	 * @param	mixed	$key	Key
	 * @return	mixed	Value
	 */
	public function __get( $key )
	{
		if ( method_exists( $this, 'get_' . $key ) )
		{
			return $this->{'get_' . $key}();
		}
		else if ( isset( $this->_data[$key] ) )
		{
			return $this->_data[$key];
		}
		
		return NULL;
	}
	
	/**
	 * JSON serialize map
	 *
	 * @return	array
	 */
	public function jsonSerialize()
	{
		$map = array( 'id' => $this->_id );
		
		if ( \count( $this->_data ) > 0 )
		{
			$map['data'] = $this->_data;
		}
		
		return $map;
	}
}