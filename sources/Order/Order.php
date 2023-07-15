<?php
/**
 * @brief				Order Model

 */

namespace IPS\donate;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Order
 */
class _Order extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Payment type: One-Time
	 */
	const PAYMENT_TYPE_ONETIME = 'onetime';
	
	/**
	 * @brief	Payment type: Subscription
	 */
	const PAYMENT_TYPE_SUBSCRIPTION = 'subscription';
	
	/**
	 * @brief	Price type: Fixed
	 */
	const PRICE_TYPE_FIXED = 'fixed';
	
	/**
	 * @brief	Price type: Variable
	 */
	const PRICE_TYPE_VARIABLE = 'variable';
	
	/**
	 * @brief	Status: Pending
	 */
	const STATUS_PENDING = 'pending';
	
	/**
	 * @brief	Status: Completed
	 */
	const STATUS_COMPLETED = 'completed';
	
	/**
	 * @brief	Status: Suspended
	 */
	const STATUS_SUSPENDED = 'suspended';
	
	/**
	 * @brief	Status: Canceled
	 */
	const STATUS_CANCELED = 'canceled';
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'donate_orders';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'token', 'provider_token' );
	
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
	 * Create from a request
	 *
	 * @param	\IPS\donate\Order\Request	$request Request
	 * @param	\IPS\donate\Gateway	$provider Provider
	 * @param	string	$providerToken Provider token
	 * @return	static
	 */
	public static function createFromRequest( \IPS\donate\Order\Request $request, \IPS\donate\Gateway $provider = NULL, $providerToken = NULL )
	{
		if ( !$request || !$request->isValid() )
		{
			return NULL;
		}
		
		$order = new static;
		$order->token = \IPS\donate\Application::generateUniqueToken( static::$databaseTable );
		
		if ( $provider && $providerToken )
		{
			$order->provider = $provider->id;
			$order->provider_token = $providerToken;
		}
		
		$order->member = $request->member;
		$order->item = $request->item;
		$order->cost = clone $request->cost;
		$order->payment_type = $request->paymentType;
		$order->price_type = $request->priceType;
		
		if ( $request->giftee )
		{
			$order->giftee = $request->giftee;
		}
		
		if ( $request->extraData && \count( $request->extraData ) > 0 )
		{
			$order->extra_data = $request->extraData;
		}
		
		$order->save();
		\IPS\donate\Log::create( $order->member, \IPS\donate\Log::TYPE_ORDER_CREATED, $order );
		
		return $order;
	}
	
	/**
	 * Complete
	 *
	 * @return	void
	 */
	public function complete()
	{
		if ( $this->status !== static::STATUS_PENDING )
		{
			return;
		}
		
		$this->status = static::STATUS_COMPLETED;
		$this->save();
		\IPS\donate\Log::create( $this->member, \IPS\donate\Log::TYPE_ORDER_COMPLETED, $this );
		
		$activeLength = NULL;
		
		if ( $this->item->active_length && $this->price_type === static::PRICE_TYPE_VARIABLE && $this->cost->isGreaterThan( $this->item->price ) )
		{
			$activeLength = static::calcActiveLengthByCost( $this->item, $this->cost );
		}
		
		$this->purchase = \IPS\donate\Purchase::create( $this->giftee ?: $this->member, $this->item, $activeLength, $this, $this->extra_data );
		
		try
		{
			$notification = new \IPS\Notification( \IPS\Application::load( 'donate' ), 'order_completed', NULL, array(), array( 'item' => $this->item->id ) ); 
			$notification->recipients->attach( $this->member );
			$notification->send();
			
			if ( $this->giftee && $this->giftee->member_id )
			{
				$notification = new \IPS\Notification( \IPS\Application::load( 'donate' ), 'gifted', NULL, array(), array( 'item' => $this->item->id, 'gifter' => $this->member->member_id ) ); 
				$notification->recipients->attach( $this->giftee );
				$notification->send();
			}
		}
		catch ( \Exception $e )
		{
		}
	}
	
	/**
	 * Renew
	 *
	 * @return	void
	 */
	public function renew()
	{
		if ( ( $this->status !== static::STATUS_COMPLETED && $this->status !== static::STATUS_SUSPENDED ) || !$this->purchase )
		{
			return;
		}
		
		if ( $this->status === static::STATUS_SUSPENDED )
		{
			$this->status = static::STATUS_COMPLETED;
			$this->save();
		}
		
		\IPS\donate\Log::create( $this->member, \IPS\donate\Log::TYPE_ORDER_RENEWED, $this );
		$this->purchase->renew();
	}
	
	/**
	 * Suspend
	 *
	 * @return	void
	 */
	public function suspend()
	{
		if ( $this->status !== static::STATUS_COMPLETED )
		{
			return;
		}
		
		$this->status = static::STATUS_SUSPENDED;
		$this->save();
		\IPS\donate\Log::create( $this->member, \IPS\donate\Log::TYPE_ORDER_SUSPENDED, $this );
	}
	
	/**
	 * Cancel
	 *
	 * @return	void
	 */
	public function cancel()
	{
		switch ( $this->status )
		{
			case static::STATUS_CANCELED:
			{
				return;
			}
			case static::STATUS_PENDING:
			case static::STATUS_SUSPENDED:
			case static::STATUS_COMPLETED:
			{
				if ( $this->purchase )
				{
					$this->purchase->deactivate();
				}
				
				break;
			}
		}
		
		$this->status = static::STATUS_CANCELED;
		$this->save();
		\IPS\donate\Log::create( $this->member, \IPS\donate\Log::TYPE_ORDER_CANCELED, $this );
	}
	
	/**
	 * Add a payment
	 *
	 * @param	\IPS\donate\Money	$amount	Amount
	 * @param	string	$providerToken	Provider token
	 * @return	\IPS\donate\Payment
	 */
	public function addPayment( \IPS\donate\Money $amount, $providerToken )
	{
		$payment = \IPS\donate\Payment::create( $this->member, $amount, $this, $providerToken );
		
		if ( isset( $this->_payments ) )
		{
			$this->_payments[] = $payment;
		}
		
		return $payment;
	}
	
	/**
	 * Calc active length by cost
	 *
	 * @param	\IPS\donate\Item	$item	Item
	 * @param	\IPS\donate\Money	$cost	Cost
	 * @return	int
	 */
	public static function calcActiveLengthByCost( \IPS\donate\Item $item, \IPS\donate\Money $cost )
	{
		$multiplier = $cost->amount / $item->price->amount ?: 1;
		return (int) ( $item->active_length * $multiplier );
	}
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	protected function setDefaultValues()
	{
		$this->_data['date'] = time();
		$this->status = static::STATUS_PENDING;
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
			$this->_member = \IPS\Member::load( $this->_data['member'] );
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
		$this->_data['member'] = $this->_member->member_id;
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
	 * @return	void
	 */
	public function set_item( \IPS\donate\Item $item )
	{
		$this->_item = $item;
		$this->_data['item'] = $this->_item->id;
	}
	
	/**
	 * @brief	Cached cost
	 */
	protected $_cost;
	
	/**
	 * Get cost
	 *
	 * @return	\IPS\donate\Money
	 */
	public function get_cost()
	{
		if ( !isset( $this->_cost ) )
		{
			$this->_cost = \IPS\donate\Money::constructFromBase( $this->_data['cost'] );
		}
		
		return $this->_cost;
	}
	
	/**
	 * Set cost
	 *
	 * @param	\IPS\donate\Money	$amount	Amount
	 * @return	void
	 */
	public function set_cost( \IPS\donate\Money $amount )
	{
		$this->_cost = $amount;
		$this->_data['cost'] = $this->_cost->amount;
	}
	
	/**
	 * @brief	Cached giftee
	 */
	protected $_giftee;
	
	/**
	 * Get giftee
	 *
	 * @return	\IPS\Member
	 */
	public function get_giftee()
	{
		if ( !isset( $this->_giftee ) && isset( $this->_data['giftee'] ) )
		{
			$this->_giftee = \IPS\Member::load( (int) $this->_data['giftee'] );
		}
		
		return $this->_giftee;
	}
	
	/**
	 * Set giftee
	 *
	 * @param	\IPS\Member	$giftee	giftee
	 * @return	void
	 */
	public function set_giftee( \IPS\Member $giftee)
	{
		$this->_giftee = $giftee;
		$this->_data['giftee'] = $this->_giftee->member_id ?: '0';
	}
	
	/**
	 * @brief	Cached purchase
	 */
	protected $_purchase;
	
	/**
	 * Get purchase
	 *
	 * @return	\IPS\donate\Purchase
	 */
	public function get_purchase()
	{
		if ( !isset( $this->_purchase ) )
		{
			try
			{
				$this->_purchase = \IPS\donate\Purchase::load( $this->id, 'order' );
			}
			catch ( \Exception $e )
			{
				$this->_purchase = NULL;
			}
		}
		
		return $this->_purchase;
	}
	
	/**
	 * Set purchase
	 *
	 * @param	\IPS\donate\purchase	$purchase	Purchase
	 * @return	void
	 */
	public function set_purchase( \IPS\donate\Purchase $purchase )
	{
		$this->_purchase = $purchase;
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
	 * @return	void
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
	
	/**
	 * @brief	Cached transactions
	 */
	protected $_transactions;
	
	/**
	 * Get transactions
	 *
	 * @return	array
	 */
	public function get_transactions()
	{
		if ( !isset( $this->_transactions ) )
		{
			$this->_transactions = \IPS\donate\Transaction::loadGroup( $this->id, 'order' ) ?: array();
		}
		
		return $this->_transactions;
	}
	
	/**
	 * @brief	Cached payments
	 */
	protected $_payments;
	
	/**
	 * Get payments
	 *
	 * @return	array
	 */
	public function get_payments()
	{
		if ( !isset( $this->_payments ) )
		{
			$transactions = \IPS\donate\Transaction::loadGroup( $this->id, 'order', \IPS\donate\Transaction::TYPE_PAYMENT ) ?: array();
			$transactionGroups = \IPS\donate\Transaction::getSortedGroups( $transactions );
			$this->_payments = array();
			
			foreach ( $transactionGroups as $group )
			{
				if ( $payment = \IPS\donate\Payment::constructFromTransactionGroup( $group ) )
				{
					$this->_payments[] = $payment;
				}
			}
		}
		
		return $this->_payments;
	}
}