<?php
/**
 * @brief				Item Payment Option Model

 */

namespace IPS\donate\Item;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * PaymentOption
 */
class _PaymentOption implements \JsonSerializable
{	
	/**
	 * @brief	Payment type
	 */
	public $paymentType;
	
	/**
	 * @brief	Price type
	 */
	public $priceType;
	
	/**
	 * Constructor
	 *
	 * @param	string	$paymentType	Payment type
	 * @param	string	$priceType	Price type
	 * @return	void
	 */
	public function __construct( $paymentType, $priceType )
	{
		$this->paymentType = $paymentType;
		$this->priceType = $priceType;
	}
	
	/**
	 * String cast
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return implode( ',', array( $this->paymentType, $this->priceType) );
	}
	
	/**
	 * JSON serialize map
	 *
	 * @return	array
	 */
	public function jsonSerialize()
	{
		return array(
			'payment_type' => $this->paymentType,
			'price_type' => $this->priceType
		);
	}
}