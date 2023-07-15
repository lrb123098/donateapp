<?php
/**
 * @brief				Transaction Model

 */

namespace IPS\donate;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Transaction
 */
class _Transaction extends \IPS\Patterns\ActiveRecord
{
	/**
	 * @brief	Type: Payment
	 */
	const TYPE_PAYMENT = 'payment';
	
	/**
	 * @brief	Type: Payment refund
	 */
	const TYPE_PAYMENT_REFUND = 'payment_refund';
	
	/**
	 * @brief	Type: Payment reversal
	 */
	const TYPE_PAYMENT_REVERSAL = 'payment_reversal';
	
	/**
	 * @brief	Type: Payment reversal cancellation
	 */
	const TYPE_PAYMENT_REVERSAL_CANCELLATION = 'payment_reversal_cancellation';
	
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;

	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'donate_transactions';
	
	/**
	 * @brief	[ActiveRecord] Database ID Fields
	 */
	protected static $databaseIdFields = array( 'token', 'provider_token' );
	
	/**
	 * @brief	[ActiveRecord] Multiton Map
	 */
	protected static $multitonMap	= array();
	
	/**
	 * Create
	 *
	 * @param	\IPS\Member	$member	Member
	 * @param	string	$type	Type
	 * @param	\IPS\donate\Money	$amount	Amount
	 * @param	\IPS\donate\Order	$order	Order
	 * @param	string	$providerToken	Provider token
	 * @param	\IPS\donate\Transaction	$parent	Parent
	 * @param	string	$status	Status
	 * @return	static
	 */
	public static function create( \IPS\Member $member, $type, \IPS\donate\Money $amount, \IPS\donate\Order $order = NULL, $providerToken = NULL, \IPS\donate\Transaction $parent = NULL, $status = NULL )
	{
		$transaction = new static;
		$transaction->member = $member;
		$transaction->type = $type;
		$transaction->amount = $amount;
		$transaction->token = \IPS\donate\Application::generateUniqueToken( static::$databaseTable );
		
		if ( $order )
		{
			$transaction->order = $order;
		}
		
		if ( $providerToken )
		{
			$transaction->provider_token = $providerToken;
		}
		
		if ( $parent )
		{
			$transaction->parent = $parent;
		}
		
		if ( $status )
		{
			$transaction->status = $status;
		}
		
		$transaction->save();
		return $transaction;
	}
	
	/**
	 * Load group
	 *
	 * @param	int|string	$id	Id
	 * @param	string		$idField	Id field
	 * @param	string		$parentType	Parent type
	 * @return	array
	 */
	public static function loadGroup( $id, $idField = 'id', $parentType = NULL )
	{
		if ( !$id || !$idField )
		{
			return NULL;
		}
		
		$table = array( static::$databaseTable, 'transaction' );
		$where = array(  'transaction.' . $idField . '=? AND transaction.parent IS NULL', $id );
		
		if ( $parentType )
		{
			$where[0] .= ' AND transaction.type=?';
			$where[] = $parentType;
		}
		
		$transactionSelect = \IPS\Db::i()->select( 'transaction.*', $table, $where );
		$childSelect = \IPS\Db::i()->select( 'child.*', $table, $where );
		$childSelect->join( array( $table[0], 'child' ), 'child.parent=transaction.id', 'INNER' );
		$union = \IPS\Db::i()->union( array( $transactionSelect, $childSelect ), 'id ASC', NULL );
		$transactions = iterator_to_array( new \IPS\Patterns\ActiveRecordIterator( $union, '\IPS\donate\Transaction' ) ) ?: NULL;
		
		return $transactions;
	}
	
	/**
	 * Exists?
	 *
	 * @param	int|string	$id	Id
	 * @param	string		$idField	Id field
	 * @return	boolean
	 */
	public static function exists( $id, $idField = 'id' )
	{
		if ( !$id || !$idField )
		{
			return FALSE;
		}
		
		if ( \IPS\Db::i()->select( 'COUNT(*)', static::$databaseTable, array( static::$databaseTable . '.' . $idField . '=?', $id ) )->first() )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * Get sorted groups
	 *
	 * @param	array	$transactions	Transactions
	 * @return	array
	 */
	public static function getSortedGroups( $transactions )
	{
		$groups = array();
		
		foreach ( $transactions as $transaction )
		{			
			$id = NULL;
			
			if ( $transaction->parent )
			{
				$id = $transaction->parent->id;
			}
			else
			{
				$id = $transaction->id;
			}
			
			if ( !isset( $groups[$id] ) )
			{
				$groups[$id] = array();
			}
			
			$groups[$id][] = $transaction;
		}
		
		if ( \count( $groups ) > 0 )
		{
			return $groups;
		}
		else
		{
			return NULL;
		}
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
	 * @brief	Cached order
	 */
	protected $_order;
	
	/**
	 * Get order
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
	 * @brief	Cached amount
	 */
	protected $_amount;
	
	/**
	 * Get amount
	 *
	 * @return	\IPS\donate\Money
	 */
	public function get_amount()
	{
		if ( !isset( $this->_amount ) && isset( $this->_data['amount'] ) )
		{
			$this->_amount = \IPS\donate\Money::constructFromBase( $this->_data['amount'] );
		}
		
		return $this->_amount;
	}
	
	/**
	 * Set amount
	 *
	 * @param	\IPS\donate\Money	$amount	Amount
	 * @return	void
	 */
	public function set_amount( \IPS\donate\Money $amount )
	{
		$this->_amount = $amount;
		$this->_data['amount'] = $this->_amount->amount;
	}
	
	/**
	 * @brief	Cached parent
	 */
	protected $_parent;
	
	/**
	 * Get parent
	 *
	 * @return	\IPS\donate\Transaction
	 */
	public function get_parent()
	{
		if ( !isset( $this->_parent ) && isset( $this->_data['parent'] ) )
		{
			$this->_parent = \IPS\donate\Transaction::load( $this->_data['parent'] );
		}
		
		return $this->_parent;
	}
	
	/**
	 * Set parent
	 *
	 * @param	\IPS\donate\Transaction	$transaction	Transaction
	 */
	public function set_parent( \IPS\donate\Transaction $transaction )
	{
		$this->_parent = $transaction;
		$this->_data['parent'] = $this->_parent->id;
	}
}