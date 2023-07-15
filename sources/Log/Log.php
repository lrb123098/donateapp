<?php
/**
 * @brief				Log Model

 */

namespace IPS\donate;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Log
 */
class _Log extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Type: Order created
	 */
	const TYPE_ORDER_CREATED = 'order_created';
	
	/**
	 * @brief	Type: Order completed
	 */
	const TYPE_ORDER_COMPLETED = 'order_completed';
	
	/**
	 * @brief	Type: Order renewed
	 */
	const TYPE_ORDER_RENEWED = 'order_renewed';
	
	/**
	 * @brief	Type: Order suspended
	 */
	const TYPE_ORDER_SUSPENDED = 'order_suspended';
	
	/**
	 * @brief	Type: Order canceled
	 */
	const TYPE_ORDER_CANCELED = 'order_canceled';
	
	/**
	 * @brief	Type: Purchase created
	 */
	const TYPE_PURCHASE_CREATED = 'purchase_created';
	
	/**
	 * @brief	Type: Purchase activated
	 */
	const TYPE_PURCHASE_ACTIVATED = 'purchase_activated';
	
	/**
	 * @brief	Type: Purchase renewed
	 */
	const TYPE_PURCHASE_RENEWED = 'purchase_renewed';
	
	/**
	 * @brief	Type: Purchase expired
	 */
	const TYPE_PURCHASE_EXPIRED = 'purchase_expired';
	
	/**
	 * @brief	Type: Purchase deactivated
	 */
	const TYPE_PURCHASE_DEACTIVATED = 'purchase_deactivated';
	
	/**
	 * @brief	Type: Payment made
	 */
	const TYPE_PAYMENT_MADE = 'payment_made';
	
	/**
	 * @brief	Type: Payment refunded
	 */
	const TYPE_PAYMENT_REFUNDED = 'payment_refunded';
	
	/**
	 * @brief	Type: Payment reversed
	 */
	const TYPE_PAYMENT_REVERSED = 'payment_reversed';
	
	/**
	 * @brief	Type: Payment reversal canceled
	 */
	const TYPE_PAYMENT_REVERSAL_CANCELED = 'payment_reversal_canceled';
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'donate_logs';
	
	/**
	 * Create
	 *
	 * @param	\IPS\Member	$member	Member
	 * @param	string	$type	Type
	 * @param	\IPS\donate\Order	$order	Order
	 * @param	\IPS\donate\Purchase	$purchase	Purchase
	 * @param	\IPS\donate\Payment	$payment	Payment
	 * @param	mixed	$message	Message
	 * @return	static
	 */
	public static function create( \IPS\Member $member, $type, \IPS\donate\Order $order = NULL, \IPS\donate\Purchase $purchase = NULL, \IPS\donate\Payment $payment = NULL, $message = NULL )
	{
		if ( (boolean) !\IPS\Settings::i()->donate_log_enabled )
		{
			return NULL;
		}
		
		$log = new static;
		$log->member = $member;
		$log->type = $type;
		
		if ( $order )
		{
			$log->order = $order->id;
		}
		
		if ( $purchase )
		{
			$log->purchase = $purchase->id;
		}
		
		if ( $payment )
		{
			$log->payment = $payment->id;
		}
		
		if ( $message )
		{
			if ( \is_array( $message ) )
			{
				$log->message = json_encode( $message );
			}
			else
			{
				$log->message = $message;
			}
		}
		
		$log->save();
		return $log;
	}
	
	/**
	 * Set Default Values
	 *
	 * @return	void
	 */
	protected function setDefaultValues()
	{
		$this->_data['date'] = time();
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
	 * @brief	Cached message
	 */
	protected $_message;
	
	/**
	 * Get message
	 *
	 * @return	mixed
	 */
	public function get_message()
	{
		if ( !isset( $this->_message ) && isset( $this->_data['message'] ) )
		{
			if ( $this->_data['message'][0] === '[' || $this->_data['message'][0] === '{' )
			{
				$lastChar = \mb_strlen( $this->_data['message'] ) - 1;
				
				if ( $this->_data['message'][$lastChar] === ']' || $this->_data['message'][$lastChar] === '}' )
				{
					$this->_message = json_decode( $this->_data['message'], TRUE ) ?: array();
				}
			}
			else
			{
				if ( \is_numeric( $this->_data['message'] ) )
				{
					if ( \mb_strpos( $value, '.' ) !== FALSE )
					{
						$this->_message = (float) $this->_data['message'];
					}
					else
					{
						$this->_message = (int) $this->_data['message'];
					}
				}
				else
				{
					$this->_message = $this->_data['message'];
				}
			}
		}
		
		return $this->_message;
	}
}