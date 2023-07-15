<?php
/**
 * @brief				Gateway Event Listener Model

 */

namespace IPS\donate\Gateway;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * EventListener
 */
abstract class _EventListener
{
	/**
	 * @brief Event: Payment
	 */
	const EVENT_PAYMENT = 'payment';
	
	/**
	 * @brief Event: Payment refund
	 */
	const EVENT_PAYMENT_REFUND = 'payment_refund';
	
	/**
	 * @brief Event: Payment reversal
	 */
	const EVENT_PAYMENT_REVERSAL = 'payment_reversal';
	
	/**
	 * @brief Event: Payment reversal cancellation
	 */
	const EVENT_PAYMENT_REVERSAL_CANCELLATION = 'payment_reversal_cancellation';
	
	/**
	 * @brief Event: Subscription suspension
	 */
	const EVENT_SUBSCIPTION_SUSPENSION = 'subscription_suspension';
	
	/**
	 * @brief Event: Subscription cancellation
	 */
	const EVENT_SUBSCIPTION_CANCELLATION = 'subscription_cancellation';
	
	/**
	 * @brief Headers
	 */
	protected $_headers;
	
	/**
	 * @brief Body
	 */
	protected $_body;
	
	/**
	 * @brief Request
	 */
	protected $_request = array();
	
	/**
	 * Parse request
	 *
	 * @return	void
	 * @throws	\IPS\donate\Gateway\Exception
	 */
	public function parseRequest()
	{
		$headers = array();
		$headerCount = \count( $_SERVER );
		$body = file_get_contents( 'php://input' );
		$bodySize = \mb_strlen( $body, '8bit' );
		
		if ( $headerCount === 0 || $headerCount > 50 || $bodySize === 0 || $bodySize > 5120 )
		{
			throw new \IPS\donate\Gateway\Exception( 'invalid_listener_request' );
		}
		
		foreach ( $_SERVER as $k => $v )
		{
			if ( \mb_strpos( $k, 'HTTP_' ) !== FALSE )
			{
				$headers[\mb_substr( $k, 5 )] = $v;
			}
		}
		
		$this->_headers = $headers;
		$this->_body = $body;
		$this->_request = iterator_to_array( \IPS\Request::i(), TRUE );
	}
	
	/**
	 * Auth
	 *
	 * @return	boolean
	 */
	public function auth()
	{
		return FALSE;
	}
	
	/**
	 * Process
	 *
	 * @return	void
	 * @throws	\IPS\donate\Gateway\Exception
	 */
	public function process()
	{
		$event = $this->getEvent();
		
		if ( !$event || !$event->isValid() )
		{
			throw new \IPS\donate\Gateway\Exception( 'invalid_listener_event' );
		}
		
		switch ( $event->type )
		{
			case static::EVENT_PAYMENT:
			{
				if ( \IPS\donate\Transaction::exists( $event->token, 'provider_token' ) )
				{
					throw new \IPS\donate\Gateway\Exception( 'duplicate_listener_event' );
				}
				
				$order = \IPS\donate\Order::load( $event->parentToken, 'provider_token' );
				
				if ( !$event->amount->isEqualTo( $order->cost ) )
				{
					throw new \IPS\donate\Gateway\Exception( 'invalid_listener_event' );
				}
				
				$order->addPayment( $event->amount, $event->token );
				
				switch ( $order->status )
				{
					case \IPS\donate\Order::STATUS_PENDING:
					{
						$order->complete();
						break;
					}
					case \IPS\donate\Order::STATUS_COMPLETED:
					case \IPS\donate\Order::STATUS_SUSPENDED:
					{
						$order->renew();
						break;
					}
				}
				
				break;
			}
			case static::EVENT_PAYMENT_REFUND:
			{
				if ( \IPS\donate\Transaction::exists( $event->token, 'provider_token' ) )
				{
					throw new \IPS\donate\Gateway\Exception( 'duplicate_listener_event' );
				}
				
				$payment = \IPS\donate\Payment::load( $event->parentToken, 'provider_token' );
				$payment->addRefund( $event->amount, $event->token );
				break;
			}
			case static::EVENT_PAYMENT_REVERSAL:
			{
				if ( \IPS\donate\Transaction::exists( $event->token, 'provider_token' ) )
				{
					throw new \IPS\donate\Gateway\Exception( 'duplicate_listener_event' );
				}
				
				$payment = \IPS\donate\Payment::load( $event->parentToken, 'provider_token' );
				$payment->addReversal( $event->amount, $event->token );
				break;
			}
			case static::EVENT_PAYMENT_REVERSAL_CANCELLATION:
			{
				if ( \IPS\donate\Transaction::exists( $event->token, 'provider_token' ) )
				{
					throw new \IPS\donate\Gateway\Exception( 'duplicate_listener_event' );
				}
				
				$payment = \IPS\donate\Payment::load( $event->parentToken, 'provider_token' );
				$payment->addReversalCancellation( $event->amount, $event->token );
				break;
			}
			case static::EVENT_SUBSCIPTION_SUSPENSION:
			{
				$order = \IPS\donate\Order::load( $event->token, 'provider_token' );
				
				if ( $order->status === \IPS\donate\Order::STATUS_SUSPENDED )
				{
					throw new \IPS\donate\Gateway\Exception( 'duplicate_listener_event' );
				}
				
				$order->suspend();
				break;
			}
			case static::EVENT_SUBSCIPTION_CANCELLATION:
			{
				$order = \IPS\donate\Order::load( $event->token, 'provider_token' );
				
				if ( $order->status === \IPS\donate\Order::STATUS_CANCELED )
				{
					throw new \IPS\donate\Gateway\Exception( 'duplicate_listener_event' );
				}
				
				$order->cancel();
				break;
			}
		}
	}
	
	/**
	 * Get event
	 *
	 * @return	\IPS\donate\Gateway\Event
	 */
	protected function getEvent()
	{
		return NULL;
	}
}