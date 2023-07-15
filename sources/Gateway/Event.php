<?php
/**
 * @brief				Gateway Event Model

 */

namespace IPS\donate\Gateway;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Event
 */
class _Event
{
	/**
	 * @brief Type
	 */
	public $type;
	
	/**
	 * @brief Parent token
	 */
	public $parentToken;
	
	/**
	 * @brief Token
	 */
	public $token;
	
	/**
	 * @brief Amount
	 */
	public $amount;
	
	/**
	 * Is valid?
	 *
	 * @return	boolean
	 */
	public function isValid()
	{
		if ( !isset( $this->type ) || !\is_string( $this->type ) || \mb_strlen( $this->type ) === 0 ||
			( isset( $this->parentToken ) && ( !\is_string( $this->parentToken ) || \mb_strlen( $this->parentToken ) === 0 ) ) ||
			( isset( $this->token ) && ( !\is_string( $this->token ) || \mb_strlen( $this->token ) === 0 ) ) ||
			( isset( $this->amount ) && ( !( $this->amount instanceof \IPS\donate\Money ) || $this->amount->amount <= 0 ) ) )
		{
			return FALSE;
		}
		
		return TRUE;
	}
}