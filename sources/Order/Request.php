<?php
/**
 * @brief				Order Request Model

 */

namespace IPS\donate\Order;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Request
 */
class _Request
{	
	/**
	 * @brief	Member
	 */
	public $member;
	
	/**
	 * @brief Item
	 */
	public $item;
	
	/**
	 * @brief Cost
	 */
	public $cost;
	
	/**
	 * @brief	Payment type
	 */
	public $paymentType;
	
	/**
	 * @brief	Price type
	 */
	public $priceType;
	
	/**
	 * Set payment option
	 *
	 * @param \IPS\donate\Item\PaymentOption	$option	Payment option
	 * @return	void
	 */
	public function set_paymentOption( \IPS\donate\Item\PaymentOption $option )
	{
		$this->paymentType = $option->paymentType;
		$this->priceType = $option->priceType;
	}
	
	/**
	 * @brief	Giftee
	 */
	public $giftee;
	
	/**
	 * @brief	Extra data
	 */
	public $extraData = array();
	
	/**
	 * Is valid?
	 *
	 * @return	boolean
	 */
	public function isValid()
	{
		if ( !isset( $this->member ) || !( $this->member instanceof \IPS\Member ) ||
			!isset( $this->item ) || !( $this->item instanceof \IPS\donate\Item ) ||
			!isset( $this->cost ) || !( $this->cost instanceof \IPS\donate\Money ) ||
			!isset( $this->paymentType ) || !\is_string( $this->paymentType ) || \mb_strlen( $this->paymentType ) === 0 ||
			!isset( $this->priceType ) || !\is_string( $this->priceType ) || \mb_strlen( $this->priceType ) === 0 ||
			( isset( $this->giftee ) && !( $this->giftee instanceof \IPS\Member ) ) ||
			!isset( $this->extraData ) || !\is_array( $this->extraData ) )
		{
			return FALSE;
		}
		
		return TRUE;
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
	
	/**
	 * Set class value
	 *
	 * @param	mixed	$key	Key
	 * @param	mixed	$value	Value
	 * @return	void
	 */
	public function __set( $key, $value )
	{
		$key = 'set_' . $key;
		
		if ( method_exists( $this, $key ) )
		{
			$this->{$key}( $value );
		}
	}
}