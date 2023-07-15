<?php
/**
 * @brief				Purchase Model

 */

namespace IPS\donate;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Purchase
 */
class _Purchase extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'donate_purchases';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'order' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * @brief	Extra data keys
	 */
	public static $extraDataKeys = array(
		'steam_id'
	);
	
	/**
	 * Create
	 *
	 * @param	\IPS\Member	$member	Member
	 * @param	\IPS\donate\Item	$item	Item
	 * @param	integer	$activeLength	Active length
	 * @param	\IPS\donate\Order	$order	Order
	 * @param	array	$extraData	Extra data
	 * @return	static
	 */
	public static function create( \IPS\Member $member, \IPS\donate\Item $item, $activeLength = NULL, \IPS\donate\Order $order = NULL, $extraData = NULL )
	{
		$purchase = new static;
		$purchase->member = $member;
		$purchase->item = $item;
		
		if ( $item->active_length )
		{
			$length = (int) $activeLength ?: $item->active_length;
			$purchase->deactivate_date = $purchase->date->getTimestamp() + $length;
			$purchase->active = TRUE;
		}
		
		if ( $order )
		{
			$purchase->order = $order;
		}
		
		if ( $extraData && \count( $extraData ) > 0 )
		{
			$purchase->extra_data = $extraData;
		}
		
		$purchase->save();
		$purchase->runItemPerkEvent( 'PurchaseCreated', $purchase );
		\IPS\donate\Log::create( $member, \IPS\donate\Log::TYPE_PURCHASE_CREATED, $order, $purchase );
		
		if ( $purchase->active )
		{
			$purchase->runItemPerkEvent( 'PurchaseActivated', $purchase );
			\IPS\donate\Log::create( $member, \IPS\donate\Log::TYPE_PURCHASE_ACTIVATED, $order, $purchase );
		}
		
		return $purchase;
	}
	
	/**
	 * Activate
	 *
	 * @return	void
	 */
	public function activate()
	{
		if ( $this->active )
		{
			return;
		}
		
		$this->active = TRUE;
		$this->save();
		$this->runItemPerkEvent( 'PurchaseActivated', $this );
		\IPS\donate\Log::create( $this->member, \IPS\donate\Log::TYPE_PURCHASE_ACTIVATED, $this->order ?: NULL, $this );
	}
	
	/**
	 * Renew
	 *
	 * @return	void
	 */
	public function renew()
	{
		$this->deactivate_date = $this->deactivate_date->getTimestamp() + $this->item->active_length;
		
		if ( !$this->active )
		{
			$this->active = TRUE;
		}
		
		$this->save();
		$this->runItemPerkEvent( 'PurchaseRenewed', $this );
		\IPS\donate\Log::create( $this->member, \IPS\donate\Log::TYPE_PURCHASE_RENEWED, $this->order ?: NULL, $this );
	}
	
	/**
	 * Expire
	 *
	 * @return	void
	 */
	public function expire()
	{
		$this->runItemPerkEvent( 'PurchaseExpired', $this );
		\IPS\donate\Log::create( $this->member, \IPS\donate\Log::TYPE_PURCHASE_EXPIRED, $this->order ?: NULL, $this );
		
		$this->deactivate();
		
		if ( $this->order )
		{
			$this->order->suspend();
		}
	}
	
	/**
	 * Deactivate
	 *
	 * @return	void
	 */
	public function deactivate()
	{
		if ( !$this->active )
		{
			return;
		}
		
		$this->active = FALSE;
		$this->save();
		$this->runItemPerkEvent( 'PurchaseDeactivated', $this );
		\IPS\donate\Log::create( $this->member, \IPS\donate\Log::TYPE_PURCHASE_DEACTIVATED, $this->order ?: NULL, $this );
	}
	
	/**
	 * Run item perk event
	 *
	 * @param	string|array	$event	Event
	 * @param	array	$args	Args
	 * @return	void
	 */
	public function runItemPerkEvent( $event, ...$args )
	{
		$canRun = TRUE;
		
		try
		{
			if ( \IPS\Db::i()->select( 'COUNT(*)', 'donate_purchases', array( 'id<>? AND member=? AND item=? AND active=1', $this->id, $this->member->member_id, $this->item->id ) )->first() )
			{
				$canRun = FALSE;
			}
		}
		catch ( \Exception $e )
		{
		}
		
		if ( $canRun )
		{
			foreach ( $this->item->perks as $perk )
			{
				$perk->runEvent( $event, ...$args );
			}
		}
	}
	
	/**
	 * Get steam id
	 *
	 * @return	\Steam\SteamID
	 */
	public function getSteamId()
	{
		$steamId = NULL;
		
		if ( $this->extra_data && isset( $this->extra_data['steam_id'] ) )
		{
			$steamId = $this->extra_data['steam_id'];
		}
		else if ( $this->member->steamid )
		{
			$steamId = $this->member->steamid;
		}
		
		if ( $steamId )
		{
			try
			{
				$steamId = new \Steam\SteamID( $steamId );
			}
			catch( \Exception $e )
			{
				return NULL;
			}
		}
		
		return $steamId;
	}
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	protected function setDefaultValues()
	{
		$this->_data['date'] = time();
		$this->active = FALSE;
	}
	
	/**
	 * @brief	Cached member
	 */
	protected $_member;
	
	/**
	 * Get member
	 *
	 * @return	\IPS\Member
	 */
	public function get_member()
	{
		if ( !isset( $this->_member ) )
		{
			$this->_member = \IPS\Member::load( (int) $this->_data['member'] );
		}
		
		return $this->_member;
	}
	
	/**
	 * Set member
	 *
	 * @param	\IPS\Member	$member	Member
	 * @return	void
	 */
	public function set_member( \IPS\Member $member )
	{
		$this->_member = $member;
		$this->_data['member'] = $this->_member->member_id ?: '0';
	}
	
	/**
	 * @brief	Cached date
	 */
	protected $_date;
	
	/**
	 * Get date
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_date()
	{
		if ( !isset( $this->_date ) )
		{
			$this->_date = \IPS\DateTime::ts( $this->_data['date'], TRUE );
		}
		
		return $this->_date;
	}
	
	/**
	 * Set date
	 *
	 * @param	mixed	$date	Date
	 * @return	void
	 */
	public function set_date( $date )
	{
		if ( $date instanceof \IPS\DateTime )
		{
			$this->_date = $date;
		}
		else if ( \is_int($date) )
		{
			$this->_date = \IPS\DateTime::ts( $date, TRUE );
		}
		
		$this->_data['date'] = $this->_date->getTimestamp();
	}
	
	/**
	 * @brief	Cached deactivate date
	 */
	protected $_deactivateDate;
	
	/**
	 * Get deactivate date
	 *
	 * @return	\IPS\DateTime
	 */
	public function get_deactivate_date()
	{
		if ( !isset( $this->_deactivateDate ) )
		{
			$this->_deactivateDate = \IPS\DateTime::ts( $this->_data['deactivate_date'], TRUE );
		}
		
		return $this->_deactivateDate;
	}
	
	/**
	 * Set deactivate date
	 *
	 * @param	mixed	$date	Date
	 * @return	void
	 */
	public function set_deactivate_date( $date )
	{
		if ( $date instanceof \IPS\DateTime )
		{
			$this->_deactivateDate = $date;
		}
		else if ( \is_int($date) )
		{
			$this->_deactivateDate = \IPS\DateTime::ts( $date, TRUE );
		}
		
		$this->_data['deactivate_date'] = $this->_deactivateDate->getTimestamp();
	}
	
	/**
	 * @brief	Cached order
	 */
	protected $_order;
	
	/**
	 * Get Order
	 *
	 * @return	\IPS\donate\order
	 */
	public function get_order()
	{
		if ( !isset( $this->_order ) && isset( $this->_data['order'] ) )
		{
			$this->_order = \IPS\donate\Order::load( $this->_data['order'] );
		}
		
		return $this->_order;
	}
	
	/**
	 * Set order
	 *
	 * @param	\IPS\donate\Order	$order	Order
	 */
	public function set_order( \IPS\donate\Order $order )
	{
		$this->_order = $order;
		$this->_data['order'] = $this->_order->id;
	}
	
	/**
	 * @brief	Cached item
	 */
	protected $_item;
	
	/**
	 * Get item
	 *
	 * @return	\IPS\donate\Item
	 */
	public function get_item()
	{
		if ( !isset( $this->_item ) )
		{
			$this->_item = \IPS\donate\Item::load( $this->_data['item'] );
		}
		
		return $this->_item;
	}
	
	/**
	 * Set item
	 *
	 * @param	\IPS\donate\Item	$item	Item
	 */
	public function set_item( \IPS\donate\Item $item )
	{
		$this->_item = $item;
		$this->_data['item'] = $this->_item->id;
	}
	
	/**
	 * @brief	Cached extra data
	 */
	protected $_extraData;
	
	/**
	 * Get extra data
	 *
	 * @return	array
	 */
	public function get_extra_data()
	{
		if ( !isset( $this->_extraData ) && isset( $this->_data['extra_data'] ) )
		{
			$this->_extraData = json_decode( $this->_data['extra_data'], TRUE ) ?: array();
		}
		
		return $this->_extraData;
	}
	
	/**
	 * Set extra data
	 *
	 * @param array	$data	Data
	 */
	public function set_extra_data( $data )
	{
		if ( $data )
		{
			$data = array_intersect_key( array_filter( $data ), array_flip( static::$extraDataKeys ) );
			
			if ( $json = json_encode( $data ) )
			{
				$this->_extraData = $data;
				$this->_data['extra_data'] = $json;
			}
		}
		else
		{
			$this->_extraData = NULL;
			$this->_data['extra_data'] = NULL;
		}
	}
}