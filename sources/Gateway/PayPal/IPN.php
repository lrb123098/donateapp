<?php
/**
 * @brief				PayPal IPN Event Listener Model

 */

namespace IPS\donate\Gateway\PayPal;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * IPN
 */
class _IPN extends \IPS\donate\Gateway\EventListener
{
	/**
	 * [EventListener] Auth
	 *
	 * @return	boolean
	 */
	public function auth()
	{
		if ( !isset( $this->_request['receiver_email'] ) || $this->_request['receiver_email'] !== \IPS\Settings::i()->donate_paypal_email )
		{
			return FALSE;
		}
		
		$url = NULL;
		
		if ( isset( $this->_request['test_ipn'] ) && (boolean) $this->_request['test_ipn'] )
		{
			$url = \IPS\Http\Url::external( 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' );
		}
		else
		{
			$url = \IPS\Http\Url::external( 'https://ipnpb.paypal.com/cgi-bin/webscr' );
		}
		
		$verifyRequest = $url->request( 15 );
		$verifyRequest->setHeaders( array(
			'User-Agent' => 'PHP-IPN-Verification-Script',
			'Connection' => 'Close'
		) );
		
		$postData = 'cmd=_notify-validate&' . $this->_body;
		$verifyResponse = $verifyRequest->post( $postData );
		
		if ( (int) $verifyResponse->httpResponseCode === 200 && $verifyResponse->content === 'VERIFIED' )
		{
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * [EventListener] Get event
	 *
	 * @return	\IPS\donate\Gateway\Event
	 */
	protected function getEvent()
	{
		$event = new \IPS\donate\Gateway\Event;
		$event->type = $this->getEventTypeFromMap();
		
		switch ( $event->type )
		{
			case static::EVENT_PAYMENT:
			{
				if ( !isset( $this->_request['payment_status'] ) || $this->_request['payment_status'] !== 'Completed' )
				{
					return NULL;
				}
				
				if ( $this->_request['txn_type'] === 'express_checkout' )
				{
					$event->parentToken = $this->_request['txn_id'];
				}
				else
				{
					$event->parentToken = $this->getSubscriptionId();
				}
				
				$event->token = $this->_request['txn_id'];
				$event->amount = new \IPS\donate\Money( (float) $this->_request['mc_gross'] );
				
				if ( $this->_request['mc_currency'] !== $event->amount->currencyCode )
				{
					return NULL;
				}
				
				break;
			}
			case static::EVENT_PAYMENT_REFUND:
			case static::EVENT_PAYMENT_REVERSAL:
			case static::EVENT_PAYMENT_REVERSAL_CANCELLATION:
			{
				$event->parentToken = $this->_request['parent_txn_id'];
				$event->token = $this->_request['txn_id'];
				$event->amount = new \IPS\donate\Money( abs( (float) $this->_request['mc_gross'] ) );
				break;
			}
			case static::EVENT_SUBSCIPTION_SUSPENSION:
			case static::EVENT_SUBSCIPTION_CANCELLATION:
			{
				$event->token = $this->getSubscriptionId();
				break;
			}
		}
		
		return $event;
	}
	
	/**
	 * Get event type from map
	 *
	 * @return	string
	 */
	protected function getEventTypeFromMap()
	{
		$eventMap = array(
			'txn_type' => array(
				'express_checkout' => static::EVENT_PAYMENT,
				'recurring_payment' => static::EVENT_PAYMENT,
				'subscr_payment' => static::EVENT_PAYMENT,
				'recurring_payment_suspended' => static::EVENT_SUBSCIPTION_SUSPENSION,
				'recurring_payment_profile_cancel' => static::EVENT_SUBSCIPTION_SUSPENSION,
				'subscr_cancel' => static::EVENT_SUBSCIPTION_SUSPENSION
			),
			'payment_status' => array(
				'Refunded' => static::EVENT_PAYMENT_REFUND,
				'Reversed' => static::EVENT_PAYMENT_REVERSAL,
				'Canceled_Reversal' => static::EVENT_PAYMENT_REVERSAL_CANCELLATION
			)
		);
		
		foreach ( $eventMap as $k => $v )
		{
			if ( isset( $this->_request[$k] ) && isset( $v[$this->_request[$k]] ) )
			{
				return $v[$this->_request[$k]];
			}
		}
	}
	
	/**
	 * Get subscription id
	 *
	 * @return	string
	 */
	protected function getSubscriptionId()
	{
		if ( isset( $this->_request['recurring_payment_id'] ) )
		{
			return $this->_request['recurring_payment_id'];
		}
		else if ( isset( $this->_request['subscr_id'] ) )
		{
			if ( isset( $this->_request['item_number'] ) )
			{
				return $this->_request['item_number'];
			}
			else
			{
				return $this->_request['subscr_id'];
			}
		}
	}
}