<?php
/**
 * @brief				Gateway Response Model

 */

namespace IPS\donate\Gateway;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Response
 */
class _Response
{
	/**
	 * @brief Provider token
	 */
	public $providerToken;
	
	/**
	 * @brief Redirect url
	 */
	public $redirectUrl;
	
	/**
	 * @brief Order
	 */
	public $order;
	
	/**
	 * Constructor
	 *
	 * @param	string	$providerToken	Provider token
	 * @param	\IPS\Http\Url	$redirectUrl	Redirect url
	 * @return	void
	 */
	public function __construct( $providerToken, \IPS\Http\Url $redirectUrl = NULL )
	{
		$this->providerToken = $providerToken;
		$this->redirectUrl = $redirectUrl;
	}
}