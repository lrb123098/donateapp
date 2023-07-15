<?php
/**
 * @brief				Payment Model

 */

namespace IPS\donate;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Payment
 */
class _Payment
{
	/**
	 * @brief Status: Paid
	 */
	const STATUS_PAID = 'paid';
	
	/**
	 * @brief Status: Refunded
	 */
	const STATUS_REFUNDED = 'refunded';
	
	/**
	 * @brief Status: Reversed
	 */
	const STATUS_REVERSED = 'reversed';
	
	/**
	 * @brief Status: Partially refunded
	 */
	const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
	
	/**
	 * @brief Status: Partially reversed
	 */
	const STATUS_PARTIALLY_REVERSED = 'partially_reversed';
	
	/**
	 * @brief Id
	 */
	public $id;
	
	/**
	 * @brief Status
	 */
	public $status;
	
	/**
	 * @brief Gross amount
	 */
	public $grossAmount;
	
	/**
	 * @brief Net amount
	 */
	public $netAmount;
	
	/**
	 * @brief	Transaction
	 */
	public $transaction;
	
	/**
	 * @brief	Child transactions
	 */
	public $childTransactions;
	
	/**
	 * Create
	 *
	 * @param	\IPS\Member	$member	Member
	 * @param	\IPS\donate\Money	$amount	Amount
	 * @param	\IPS\donate\Order	$order	Order
	 * @param	string	$providerToken	Provider token
	 * @return	static
	 */
	public static function create( \IPS\Member $member, \IPS\donate\Money $amount, \IPS\donate\Order $order = NULL, $providerToken = NULL )
	{
		$transaction = \IPS\donate\Transaction::create( $member, \IPS\donate\Transaction::TYPE_PAYMENT, $amount, $order, $providerToken, NULL, static::STATUS_PAID );
		$payment = static::constructFromTransaction( $transaction );
		\IPS\donate\Log::create( $member, \IPS\donate\Log::TYPE_PAYMENT_MADE, $order, NULL, $payment, $amount->amount );
		
		$donationTotal = NULL;
		
		if ( $member->donation_total )
		{
			$donationTotal = clone $member->donation_total;
			$donationTotal->add( $amount );
		}
		else
		{
			$donationTotal = clone $amount;
		}
		
		$member->donation_total = $donationTotal;
		$member->save();
		
		return $payment;
	}
	
	/**
	 * load
	 *
	 * @param	int|string	$id	Id
	 * @param	string		$idField	Id field
	 * @return	static
	 */
	public static function load( $id, $idField = 'id' )
	{
		if ( !$id || !$idField )
		{
			return NULL;
		}
		
		$transactions = \IPS\donate\Transaction::loadGroup( $id, $idField, \IPS\donate\Transaction::TYPE_PAYMENT ) ?: array();
		$payment = static::constructFromTransactionGroup( $transactions );
		
		return $payment;
	}
	
	/**
	 * Construct from a payment transaction
	 *
	 * @param	\IPS\donate\Transaction	$transaction	Transaction
	 * @return	static
	 */
	public static function constructFromTransaction( \IPS\donate\Transaction $transaction )
	{
		if ( !$transaction || $transaction->type !== \IPS\donate\Transaction::TYPE_PAYMENT )
		{
			return NULL;
		}
		
		$payment = new static;
		$payment->id = $transaction->id;
		$payment->status = $transaction->status ?: static::STATUS_PAID;
		$payment->grossAmount = $transaction->amount;
		$payment->netAmount = clone $payment->grossAmount;
		$payment->transaction = $transaction;
		
		return $payment;
	}
	
	/**
	 * Construct from a group of transactions
	 *
	 * @param	array	$transactions	Transactions
	 * @return	static
	 */
	public static function constructFromTransactionGroup( $transactions )
	{
		if ( !$transactions || \count( $transactions ) === 0 )
		{
			return NULL;
		}
		
		$payment = NULL;
		
		foreach ( $transactions as $k => $transaction )
		{
			if ( !$payment && $transaction->type === \IPS\donate\Transaction::TYPE_PAYMENT )
			{
				$payment = static::constructFromTransaction( $transaction );
				continue;
			}
			else if ( $payment )
			{
				if ( !isset( $payment->childTransactions ) )
				{
					$payment->childTransactions = array();
				}
				
				switch ( $transaction->type )
				{
					case \IPS\donate\Transaction::TYPE_PAYMENT_REFUND:
					case \IPS\donate\Transaction::TYPE_PAYMENT_REVERSAL:
					{
						$payment->netAmount->subtract( $transaction->amount );
						break;
					}
					case \IPS\donate\Transaction::TYPE_PAYMENT_REVERSAL_CANCELLATION:
					{
						$payment->netAmount->add( $transaction->amount );
						break;
					}
				}
				
				$payment->childTransactions[] = $transaction;
			}
		}
		
		if ( !$payment || !$payment->status )
		{
			return NULL;
		}
		
		return $payment;
	}
	
	/**
	 * Get transactions
	 *
	 * @return	array
	 */
	public function getTransactions()
	{
		$transactions = array( $this->transaction );
		
		if ( isset( $this->childTransactions ) )
		{
			$transactions = array_merge( $transactions, $this->childTransactions );
		}
		
		return $transactions;
	}
	
	/**
	 * Add refund
	 *
	 * @param	\IPS\donate\Money	$amount	Amount
	 * @param	string	$providerToken	Provider token
	 * @return	\IPS\donate\Transaction
	 */
	public function addRefund( \IPS\donate\Money $amount, $providerToken )
	{
		if ( !$this->transaction->order )
		{
			return NULL;
		}
		
		$transaction = \IPS\donate\Transaction::create( $this->transaction->member, \IPS\donate\Transaction::TYPE_PAYMENT_REFUND, $amount, $this->transaction->order, $providerToken, $this->transaction );
		$this->childTransactions[] = $transaction;
		$this->netAmount->subtract( $amount );
		
		\IPS\donate\Log::create( $this->transaction->member, \IPS\donate\Log::TYPE_PAYMENT_REFUNDED, $this->transaction->order, NULL, $this, $amount->amount );
		$this->updateStatus();
		
		return $transaction;
	}
	
	/**
	 * Add reversal
	 *
	 * @param	\IPS\donate\Money	$amount	Amount
	 * @param	string	$providerToken	Provider token
	 * @return	\IPS\donate\Transaction
	 */
	public function addReversal( \IPS\donate\Money $amount, $providerToken )
	{
		if ( !$this->transaction->order )
		{
			return NULL;
		}
		
		$transaction = \IPS\donate\Transaction::create( $this->transaction->member, \IPS\donate\Transaction::TYPE_PAYMENT_REVERSAL, $amount, $this->transaction->order, $providerToken, $this->transaction );
		$this->childTransactions[] = $transaction;
		$this->netAmount->subtract( $amount );
		
		\IPS\donate\Log::create( $this->transaction->member, \IPS\donate\Log::TYPE_PAYMENT_REVERSED, $this->transaction->order, NULL, $this, $amount->amount );
		$this->updateStatus();
		
		return $transaction;
	}
	
	/**
	 * Add reversal cancellation
	 *
	 * @param	\IPS\donate\Money	$amount	Amount
	 * @param	string	$providerToken	Provider token
	 * @return	\IPS\donate\Transaction
	 */
	public function addReversalCancellation( \IPS\donate\Money $amount, $providerToken )
	{
		if ( !$this->transaction->order )
		{
			return NULL;
		}
		
		$transaction = \IPS\donate\Transaction::create( $this->transaction->member, \IPS\donate\Transaction::TYPE_PAYMENT_REVERSAL_CANCELLATION, $amount, $this->transaction->order, $providerToken, $this->transaction );
		$this->childTransactions[] = $transaction;
		$this->netAmount->add( $amount );
		
		\IPS\donate\Log::create( $this->transaction->member, \IPS\donate\Log::TYPE_PAYMENT_REVERSAL_CANCELED, $this->transaction->order, NULL, $this, $amount->amount );
		$this->updateStatus();
		
		return $transaction;
	}
	
	/**
	 * Update status
	 *
	 * @return	void
	 */
	protected function updateStatus()
	{
		$oldStatus = $this->status;
		$newStatus = NULL;
		
		if ( $this->netAmount->isEqualTo( $this->grossAmount ) )
		{
			$newStatus = static::STATUS_PAID;
		}
		else if ( isset( $this->childTransactions ) )
		{			
			$partial = NULL;
			
			if ( $this->netAmount->isGreaterThan( 0 ) && $this->netAmount->isLessThan( $this->grossAmount ) )
			{
				$partial = TRUE;
			}
			else if ( $this->netAmount->isEqualTo( 0 ) )
			{
				$partial = FALSE;
			}
			else
			{
				return;
			}
			
			$refundTotal = new \IPS\donate\Money( 0 );
			$reversalTotal = new \IPS\donate\Money( 0 );
			
			foreach ( $this->childTransactions as $transaction )
			{
				switch ( $transaction->type )
				{
					case \IPS\donate\Transaction::TYPE_PAYMENT_REFUND:
					{
						$refundTotal->add( $transaction->amount );
						break;
					}
					case \IPS\donate\Transaction::TYPE_PAYMENT_REVERSAL:
					{
						$reversalTotal->add( $transaction->amount );
						break;
					}
					case \IPS\donate\Transaction::TYPE_PAYMENT_REVERSAL_CANCELLATION:
					{
						$reversalTotal->subtract( $transaction->amount );
						break;
					}
				}
			}
			
			if ( $refundTotal->isGreaterThan( $reversalTotal ) )
			{
				$newStatus = $partial ? static::STATUS_PARTIALLY_REFUNDED : static::STATUS_REFUNDED;
			}
			else if ( $reversalTotal->isEqualTo( $refundTotal ) || $reversalTotal->isGreaterThan( $refundTotal ) )
			{
				$newStatus = $partial ? static::STATUS_PARTIALLY_REVERSED : static::STATUS_REVERSED;
			}
		}
		
		if ( $newStatus !== $oldStatus )
		{
			$this->status = $newStatus;
			$this->transaction->status = $newStatus;
			$this->transaction->save();
		}
	}
}