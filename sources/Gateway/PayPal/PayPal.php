<?php
/**
 * @brief				PayPal Gateway Model

 */

namespace IPS\donate\Gateway;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PayPal
 */
class _PayPal extends \IPS\donate\Gateway
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
	 * @brief Urls
	 */
	protected $_urls;
	
	/**
	 * Initialise
	 *
	 * @return	void
	 */
	protected function init()
	{
		if ( class_exists( 'IPS\Dispatcher\Admin', FALSE ) )
		{
			return;
		}
		
		$envClass = '';
		$clientId = '';
		$secret = '';
		
		if ( (boolean) \IPS\Settings::i()->donate_paypal_api_sandbox_enabled )
		{
			$envClass = '\PayPal\Core\SandboxEnvironment';
			$clientId = \IPS\Settings::i()->donate_paypal_api_sandbox_clientid;
			$secret = \IPS\Settings::i()->donate_paypal_api_sandbox_secret;
		}
		else
		{
			$envClass = '\PayPal\Core\ProductionEnvironment';
			$clientId = \IPS\Settings::i()->donate_paypal_api_live_clientid;
			$secret = \IPS\Settings::i()->donate_paypal_api_live_secret;
		}
		
		$credentials = \IPS\donate\Application::decryptArray( array(
			'clientid' => $clientId,
			'secret' => $secret,
			'webhookid' => \IPS\Settings::i()->donate_paypal_webhook_id
		) );
		
		$this->_environment = new $envClass( $credentials['clientid'], $credentials['secret'] );
		$this->_client = new \PayPal\Core\PayPalHttpClient( $this->_environment );
		$this->_webhookId = $credentials['webhookid'];
		
		if ( class_exists( 'IPS\Dispatcher', FALSE ) )
		{
			$this->_urls = array(
				'cancel' => (string) \IPS\Http\Url::internal( 'app=donate&module=donate&controller=checkout&do=cancel', 'front', 'donate_checkout_cancel' )->csrf(),
				'return' => (string) \IPS\Http\Url::internal( 'app=donate&module=donate&controller=checkout&do=confirm', 'front', 'donate_checkout_confirm' )->csrf()
			);
		}
	}
	
	/**
	 * [Gateway] Get event listener
	 *
	 * @return	\IPS\donate\Gateway\EventListener
	 */
	public function get_eventListener()
	{
		if ( !isset( $this->_eventListener ) )
		{
			if ( (boolean) \IPS\Settings::i()->donate_paypal_webhook_enabled )
			{
				$this->_eventListener = new \IPS\donate\Gateway\PayPal\Webhook( $this->_environment, $this->_client, $this->_webhookId );
			}
			else if ( (boolean) \IPS\Settings::i()->donate_paypal_ipn_enabled )
			{
				$this->_eventListener = new \IPS\donate\Gateway\PayPal\IPN;
			}
		}
		
		return $this->_eventListener;
	}
	
	/**
	 * [Gateway] Settings form
	 *
	 * @param	\IPS\Helpers\Form	$form	Form
	 * @return	void
	 */
	public function settings( &$form )
	{
		$form->addHeader( 'donate_settings_paypal_settings' );
		$form->add( new \IPS\Helpers\Form\Text( 'donate_paypal_email', \IPS\Settings::i()->donate_paypal_email ) );
		$form->add( new \IPS\Helpers\Form\Radio( 'donate_settings_paypal_eventlistener', \IPS\Settings::i()->donate_paypal_ipn_enabled ? 'ipn' : 'webhook', FALSE, array(
			'options' => array(
				'ipn' => 'donate_paypal_ipn_enabled',
				'webhook' => 'donate_paypal_webhook_enabled'
			),
			'toggles' => array(
				'webhook'	=> array( 'donate_paypal_webhook_id' )
			)
		) ) );
		
		$form->add( new \IPS\Helpers\Form\Text( 'donate_paypal_webhook_id', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_paypal_webhook_id )->decrypt(), FALSE, array(), NULL, NULL, NULL, 'donate_paypal_webhook_id' ) );
		
		$form->addHeader( 'donate_settings_paypal_api' );
		$form->add( new \IPS\Helpers\Form\Text( 'donate_paypal_api_live_clientid', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_paypal_api_live_clientid )->decrypt() ) );
		$form->add( new \IPS\Helpers\Form\Password( 'donate_paypal_api_live_secret', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_paypal_api_live_secret )->decrypt() ) );
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'donate_paypal_api_sandbox_enabled', \IPS\Settings::i()->donate_paypal_api_sandbox_enabled, FALSE, array( 'togglesOn' => array( 'donate_paypal_api_sandbox_clientid', 'donate_paypal_api_sandbox_secret' ) ) ) );
		$form->add( new \IPS\Helpers\Form\Text( 'donate_paypal_api_sandbox_clientid', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_paypal_api_sandbox_clientid )->decrypt(), FALSE, array(), NULL, NULL, NULL, 'donate_paypal_api_sandbox_clientid' ) );
		$form->add( new \IPS\Helpers\Form\Password( 'donate_paypal_api_sandbox_secret', \IPS\Text\Encrypt::fromCipher( \IPS\Settings::i()->donate_paypal_api_sandbox_secret )->decrypt(), FALSE, array(), NULL, NULL, NULL, 'donate_paypal_api_sandbox_secret' ) );
	}
	
	/**
	 * [Gateway] Format settings form values for save
	 *
	 * @param	array	$values	Values
	 * @return	array
	 */
	public function formatSettingsValues( $values )
	{
		if ( $values['donate_settings_paypal_eventlistener'] )
		{
			switch ( $values['donate_settings_paypal_eventlistener'] )
			{
				case 'ipn':
				{
					$values['donate_paypal_ipn_enabled'] = 1;
					$values['donate_paypal_webhook_enabled'] = 0;
					break;
				}
				case 'webhook':
				{
					$values['donate_paypal_ipn_enabled'] = 0;
					$values['donate_paypal_webhook_enabled'] = 1;
					break;
				}
			}
			
			unset( $values['donate_settings_paypal_eventlistener'] );
		}
		
		if ( $values['donate_paypal_webhook_id'] )
		{
			$values['donate_paypal_webhook_id'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_paypal_webhook_id'] )->cipher;
		}
		
		if ( $values['donate_paypal_api_live_clientid'] )
		{
			$values['donate_paypal_api_live_clientid'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_paypal_api_live_clientid'] )->cipher;
		}
		
		if ( $values['donate_paypal_api_live_secret'] )
		{
			$values['donate_paypal_api_live_secret'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_paypal_api_live_secret'] )->cipher;
		}
		
		if ( $values['donate_paypal_api_sandbox_clientid'] )
		{
			$values['donate_paypal_api_sandbox_clientid'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_paypal_api_sandbox_clientid'] )->cipher;
		}
		
		if ( $values['donate_paypal_api_sandbox_secret'] )
		{
			$values['donate_paypal_api_sandbox_secret'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_paypal_api_sandbox_secret'] )->cipher;
		}
		
		return $values;
	}
	
	/**
	 * Item add/edit form
	 *
	 * @param	\IPS\Helpers\Form	$form	Form
	 * @param	array	$data	Data
	 * @return	void
	 */
	public function itemData( &$form, $data )
	{
		$form->add( new \IPS\Helpers\Form\Text( 'donate_items_paypal_planid', \IPS\Text\Encrypt::fromCipher( $data['plan_id'] )->decrypt() ) );
	}
	
	/**
	 * Format item add/edit form values for save
	 *
	 * @param	array	$values	Values
	 * @return	array
	 */
	public function formatItemDataValues( $values )
	{
		$data = array();
		
		if ( $values['donate_items_paypal_planid'] )
		{
			$data['plan_id'] = \IPS\Text\Encrypt::fromPlaintext( $values['donate_items_paypal_planid'] )->cipher;
		}
		
		return $data;
	}
	
	/**
	 * Get button styling
	 *
	 * @return	array
	 */
	public function getButton()
	{
		return array(
			'background' => '#009cde',
			'image' => 'paypal_logo_small.png'
		);
	}
	
	/**
	 * [Gateway] Create one time order
	 *
	 * @param	\IPS\donate\Order\Request	$request	Request
	 * @return	\IPS\donate\Gateway\Response
	 */
	protected function createOneTimeOrder( \IPS\donate\Order\Request $request )
	{
		$apiRequest = new \BraintreeHttp\HttpRequest('/v2/checkout/orders/', 'POST');
		$apiRequest->headers['Content-Type'] = 'application/json';
		$apiRequest->body = array(
			'intent' => 'CAPTURE',
			'purchase_units' => array(
				array(
					'description' => $request->item->name,
					'amount' => array(
						'value' => $request->cost->rawFormat,
						'currency_code' => $request->cost->currencyCode
					)
				)
			),
			'application_context' => array(
				'brand_name' => '',
				'shipping_preference' => 'NO_SHIPPING',
				'user_action' => 'PAY_NOW',
				'payment_method' => array(
					'payee_preferred' => 'UNRESTRICTED'
				),
				'cancel_url' => $this->_urls['cancel'],
				'return_url' => $this->_urls['return']
			)
		);
		
		if ( $apiResponse = $this->_client->execute( $apiRequest ) )
		{
			if ( $apiResponse->statusCode === 201 && $apiResponse->result->status === 'CREATED' )
			{
				if ( $approveLink = $this->getResponseLink( $apiResponse, 'approve' ) )
				{
					return new \IPS\donate\Gateway\Response( $apiResponse->result->id, $approveLink );
				}
			}
		}
		
		return NULL;
	}
	
	/**
	 * Confirm one time order
	 *
	 * @param	\IPS\donate\Order	$order	Order
	 * @return	boolean
	 */
	protected function confirmOneTimeOrder( \IPS\donate\Order $order )
	{
		$apiRequest = new \BraintreeHttp\HttpRequest('/v2/checkout/orders/' . $order->provider_token . '/capture', 'POST');
		$apiRequest->headers['Content-Type'] = 'application/json';
		$apiRequest->headers['Prefer'] = 'return=representation';
		
		if ( $apiResponse = $this->_client->execute( $apiRequest ) )
		{
			if ( $apiResponse->statusCode === 201 && $apiResponse->result->status === 'COMPLETED' )
			{
				$order->provider_token = $apiResponse->result->purchase_units[0]->payments->captures[0]->id;
				$order->save();
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	/**
	 * [Gateway] Create subscription order
	 *
	 * @param	\IPS\donate\Order\Request	$request	Request
	 * @return	\IPS\donate\Gateway\Response
	 */
	protected function createSubscriptionOrder( \IPS\donate\Order\Request $request )
	{
		$providerData = $request->item->getProviderData( $this->id );
		
		if ( !$providerData || !isset( $providerData['plan_id'] ) )
		{
			return NULL;
		}
		
		$apiRequest = new \BraintreeHttp\HttpRequest('/v1/billing/subscriptions', 'POST');
		$apiRequest->headers['Content-Type'] = 'application/json';
		$apiRequest->body = array(
			'plan_id' => \IPS\Text\Encrypt::fromCipher( $providerData['plan_id'] )->decrypt(),
			'auto_renewal' => true,
			'application_context' => array(
				'brand_name' => '',
				'shipping_preference' => 'NO_SHIPPING',
				'user_action' => 'SUBSCRIBE_NOW',
				'payment_method' => array(
					'payee_preferred' => 'UNRESTRICTED'
				),
				'cancel_url' => $this->_urls['cancel'],
				'return_url' => $this->_urls['return']
			)
		);
		
		if ( $apiResponse = $this->_client->execute( $apiRequest ) )
		{
			if ( $apiResponse->statusCode === 201 && $apiResponse->result->status === 'APPROVAL_PENDING' )
			{
				if ( $approveLink = $this->getResponseLink( $apiResponse, 'approve' ) )
				{
					return new \IPS\donate\Gateway\Response( $apiResponse->result->id, $approveLink );
				}
			}
		}
		
		return NULL;
	}
	
	/**
	 * Confirm subscription order
	 *
	 * @param	\IPS\donate\Order	$order	Order
	 * @return	boolean
	 */
	protected function confirmSubscriptionOrder( \IPS\donate\Order $order )
	{
		$apiRequest = new \BraintreeHttp\HttpRequest('/v1/billing/subscriptions/' . $order->provider_token, 'GET');
		$apiRequest->headers['Content-Type'] = 'application/json';
		
		if ( $apiResponse = $this->_client->execute( $apiRequest ) )
		{
			if ( $apiResponse->statusCode === 200 && ( $apiResponse->result->status === 'APPROVED' || $apiResponse->result->status === 'ACTIVE' ) )
			{
				return TRUE;
			}
		}
		
		return FALSE;
	}
	
	/**
	 * Get response link
	 *
	 * @param	object	$response	Response
	 * @param	string	$rel	Rel
	 * @return	\IPS\Http\Url
	 */
	protected function getResponseLink( $response, $rel )
	{
		foreach ( $response->result->links as $link )
		{
			if ( $link->rel === $rel )
			{
				return \IPS\Http\Url::external( $link->href );
			}
		}
		
		return NULL;
	}
}