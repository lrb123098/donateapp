<?php
/**
 * @brief				Gateway Model

 */

namespace IPS\donate;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Gateway
 */
abstract class _Gateway
{
	/**
	 * @brief	List of providers
	 * @note	'Id' => 'Class Name'
	 */
	const PROVIDERS = array(
		'paypal' => 'PayPal'
	);
	
	/**
	 * @brief Multitons
	 */
	protected static $multitons = array();
	
	/**
	 * @brief Id
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
	 * @brief Event listener
	 */
	protected $_eventListener;
	
	/**
	 * Constructor
	 *
	 * @param	string	$id	Id
	 * @return	void
	 */
	public function __construct( $id )
	{
		$this->_id = $id;
		$this->init();
	}
	
	/**
	 * Initialise
	 *
	 * @return	void
	 */
	protected function init()
	{
		return;
	}
	
	/**
	 * Providers
	 *
	 * @return	array
	 */
	public static function providers()
	{
		$providers = array();
		
		foreach ( \IPS\donate\Gateway::PROVIDERS as $k => $v )
		{
			$providers[] = static::getProvider( $k );
		}
		
		return $providers;
	}
	
	/**
	 * Get provider
	 *
	 * @param	string	$provider	Provider
	 * @return	\IPS\donate\Gateway
	 */
	public static function getProvider( $provider )
	{
		if ( $provider === NULL || !\is_string( $provider ) || !isset( static::PROVIDERS[$provider] ) )
		{
			return NULL;
		}
		
		if ( !isset( static::$multitons[$provider] ) )
		{
			$classname = '\IPS\donate\Gateway\\' . static::PROVIDERS[$provider];
			
			if ( class_exists( $classname ) )
			{
				static::$multitons[$provider] = new $classname( $provider );
			}
		}
		
		return static::$multitons[$provider];
	}
	
	/**
	 * Settings form
	 *
	 * @param	\IPS\Helpers\Form	$form	Form
	 * @return	void
	 */
	public function settings( &$form )
	{
		return;
	}
	
	/**
	 * Format settings form values for save
	 *
	 * @param	array	$values	Values
	 * @return	array
	 */
	public function formatSettingsValues( $values )
	{
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
		return;
	}
	
	/**
	 * Format item add/edit form values for save
	 *
	 * @param	array	$values	Values
	 * @return	array
	 */
	public function formatItemDataValues( $values )
	{
		return $values;
	}
	
	/**
	 * Get button styling
	 *
	 * @return	array
	 */
	public function getButton()
	{
		return array();
	}
	
	/**
	 * Create an order
	 *
	 * @param	\IPS\donate\Order\Request	$request	Request
	 * @return	\IPS\donate\Gateway\Response
	 * throws \IPS\donate\Gateway\Exception
	 */
	public function createOrder( \IPS\donate\Order\Request $request )
	{
		$this->validateOrderRequest( $request );
		$response = NULL;
		
		switch ( $request->paymentType )
		{
			case \IPS\donate\Order::PAYMENT_TYPE_ONETIME:
			{
				$response = $this->createOneTimeOrder( $request );
				break;
			}
			case \IPS\donate\Order::PAYMENT_TYPE_SUBSCRIPTION:
			{
				$response = $this->createSubscriptionOrder( $request );
				break;
			}
		}
		
		$this->validateResponse( $response, TRUE );
		$order = \IPS\donate\Order::createFromRequest( $request, $this, $response->providerToken );
		$this->validateOrder( $order );
		$response->order = $order;
		
		return $response;
	}
	
	/**
	 * Confirm an order
	 *
	 * @param	\IPS\donate\Order	$order	Order
	 * @return	void
	 * throws \IPS\donate\Gateway\Exception
	 */
	public function confirmOrder( \IPS\donate\Order $order )
	{
		$this->validateOrder( $order, \IPS\donate\Order::STATUS_PENDING );
		$response = FALSE;
		
		switch ( $order->payment_type )
		{
			case \IPS\donate\Order::PAYMENT_TYPE_ONETIME:
			{
				$response = $this->confirmOneTimeOrder( $order );
				break;
			}
			case \IPS\donate\Order::PAYMENT_TYPE_SUBSCRIPTION:
			{
				$response = $this->confirmSubscriptionOrder( $order );
				break;
			}
		}
		
		$this->validateResponse( $response );
	}
	
	/**
	 * Complete an order
	 *
	 * @param	\IPS\donate\Order	$order	Order
	 * @return	void
	 * throws \IPS\donate\Gateway\Exception
	 */
	public function completeOrder( \IPS\donate\Order $order )
	{
		$this->validateOrder( $order, \IPS\donate\Order::STATUS_PENDING );
		$order->complete();
		$this->verifyOrderCompletion( $order );
	}
	
	/**
	 * Cancel an order
	 *
	 * @param	\IPS\donate\Order	$order	Order
	 * @return	void
	 * throws \IPS\donate\Gateway\Exception
	 */
	public function cancelOrder( \IPS\donate\Order $order )
	{
		$this->validateOrder( $order, \IPS\donate\Order::STATUS_PENDING );
		$order->cancel();
		$this->validateOrder( $order, \IPS\donate\Order::STATUS_CANCELED );
	}
	
	/**
	 * Create one time order
	 *
	 * @param	\IPS\donate\Order\Request	$request	Request
	 * @return	\IPS\donate\Gateway\Response
	 */
	protected function createOneTimeOrder( \IPS\donate\Order\Request $request )
	{
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
		return FALSE;
	}
	
	/**
	 * Create subscription order
	 *
	 * @param	\IPS\donate\Order\Request	$request	Request
	 * @return	\IPS\donate\Gateway\Response
	 */
	protected function createSubscriptionOrder( \IPS\donate\Order\Request $request )
	{
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
		return FALSE;
	}
	
	/**
	 * Verify order completion
	 *
	 * @param	\IPS\donate\Order	$order	Order
	 * @return	void
	 * throws \IPS\donate\Gateway\Exception
	 */
	public function verifyOrderCompletion( \IPS\donate\Order $order )
	{
		$this->validateOrder( $order );
		
		if ( $order->status !== \IPS\donate\Order::STATUS_COMPLETED )
		{
			throw new \IPS\donate\Gateway\Exception( 'completion_pending' );
		}
	}
	
	/**
	 * Validate request
	 *
	 * @param	\IPS\donate\Order\Request	$request	Request
	 * @return	void
	 * throws \IPS\donate\Gateway\Exception
	 */
	protected function validateOrderRequest( \IPS\donate\Order\Request $request )
	{
		if ( !$request || !$request->isValid() )
		{
			throw new \IPS\donate\Gateway\Exception( 'invalid_order_request' );
		}
	}
	
	/**
	 * Validate order
	 *
	 * @param	\IPS\donate\Order	$order	Order
	 * @param	boolean	$status	What should the order status be?
	 * @return	void
	 * throws \IPS\donate\Gateway\Exception
	 */
	protected function validateOrder( \IPS\donate\Order $order, $status = NULL )
	{
		if ( !$order || !$order->token || \mb_strlen( $order->token ) === 0 )
		{
			throw new \IPS\donate\Gateway\Exception( 'invalid_order' );
		}
		
		if ( $status )
		{
			if ( ( \is_array( $status ) && array_search( $order->status, $status, TRUE ) !== FALSE ) || $order->status !== $status ) 
			{
				throw new \IPS\donate\Gateway\Exception( 'invalid_order_status' );
			}
		}
	}
	
	/**
	 * Validate response
	 *
	 * @param	mixed	$response	Response
	 * @return	void
	 * throws \IPS\donate\Gateway\Exception
	 */
	protected function validateResponse( $response )
	{
		if ( !$response )
		{
			throw new \IPS\donate\Gateway\Exception( 'invalid_response' );
		}
		
		if ( $response instanceof \IPS\donate\Gateway\Response )
		{
			if ( !$response->providerToken || !\is_string( $response->providerToken ) || \mb_strlen( $response->providerToken ) === 0 )
			{
				throw new \IPS\donate\Gateway\Exception( 'invalid_response_token' );
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
		$key = 'get_' . $key;
		
		if ( method_exists( $this, $key ) )
		{
			return $this->{$key}();
		}
		
		return NULL;
	}
}