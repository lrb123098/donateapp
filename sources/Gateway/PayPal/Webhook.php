<?php
/**
 * @brief				PayPal Webhook Event Listener Model

 */

namespace IPS\donate\Gateway\PayPal;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Webhook
 */
class _Webhook extends \IPS\donate\Gateway\EventListener
{
	/**
	 * @brief Environment
	 */
	protected $_environment;
	
	/**
	 * @brief Client
	 */
	protected $_client;
	
	/**
	 * @brief Webhook id
	 */
	protected $_webhookId;
	
	/**
	 * Constructor
	 *
	 * @param	\PayPal\Core\PayPalEnvironment $environment	Environment
	 * @param	\PayPal\Core\PayPalHttpClient $client	Client
	 * @param	string $webhookId	Webhook id
	 * @return	void
	 */
	public function __construct( \PayPal\Core\PayPalEnvironment $environment, \PayPal\Core\PayPalHttpClient $client, $webhookId )
	{		
		$this->_environment = $environment;
		$this->_client = $client;
		$this->_webhookId = $webhookId;
	}
	
	/**
	 * [EventListener] Parse request
	 *
	 * @return	void
	 * @throws	\IPS\donate\Gateway\Exception
	 */
	public function parseRequest()
	{
		parent::parseRequest();
		
		if ( !isset( $this->_headers['PAYPAL_AUTH_ALGO'] ) || !isset( $this->_headers['PAYPAL_CERT_URL'] ) || !isset( $this->_headers['PAYPAL_TRANSMISSION_ID'] ) || 
			!isset( $this->_headers['PAYPAL_TRANSMISSION_SIG'] ) || !isset( $this->_headers['PAYPAL_TRANSMISSION_TIME'] ) || !$this->_environment || !$this->_client or
			!$this->_webhookId )
		{
			throw new \IPS\donate\Gateway\Exception( 'invalid_listener_request' );
		}
		
		$this->_request = json_decode( $this->_body, TRUE );
	}
	
	/**
	 * [EventListener] Auth
	 *
	 * @return	boolean
	 */
	public function auth()
	{
		// We have to manually construct the JSON object because of an issue with JSON string serialisation
		// Reference: https://github.com/paypal/PayPal-node-SDK/issues/294
		$apiRequest = new \BraintreeHttp\HttpRequest( '/v1/notifications/verify-webhook-signature', 'POST' );
		$apiRequest->headers['Content-Type'] = 'application/json';
		$apiRequest->body = '{';
		$apiRequest->body .= '"auth_algo":"' . $this->_headers['PAYPAL_AUTH_ALGO'] . '",';
		$apiRequest->body .= '"cert_url":"' . $this->_headers['PAYPAL_CERT_URL'] . '",';
		$apiRequest->body .= '"transmission_id":"' . $this->_headers['PAYPAL_TRANSMISSION_ID'] . '",';
		$apiRequest->body .= '"transmission_sig":"' . $this->_headers['PAYPAL_TRANSMISSION_SIG'] . '",';
		$apiRequest->body .= '"transmission_time":"' . $this->_headers['PAYPAL_TRANSMISSION_TIME'] . '",';
		$apiRequest->body .= '"webhook_id":"' . $this->_webhookId . '",';
		$apiRequest->body .= '"webhook_event":' . $this->_body;
		$apiRequest->body .= '}';
		
		if ( $apiResponse = $this->_client->execute( $apiRequest ) && $apiResponse->statusCode === 200 )
		{
			if ( $apiResponse->result->verification_status === 'SUCCESS' )
			{
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	/**
	 * [EventListener] Process
	 *
	 * @return	void
	 */
	public function process()
	{
		// WIP
	}
}