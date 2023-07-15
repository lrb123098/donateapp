//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class donate_hook_member extends _HOOK_CLASS_
{
	/**
	 * @brief	Cached donation total
	 */
	protected $_donationTotal;
	
	/**
	 * Get donation total
	 *
	 * @return	\IPS\donate\Money
	 */
	public function get_donation_total()
	{
		if ( !isset( $this->_donationTotal ) && isset( $this->_data['donation_total'] ) )
		{
			$this->_donationTotal = \IPS\donate\Money::constructFromBase( $this->_data['donation_total'] );
		}
		
		return $this->_donationTotal;
	}
	
	/**
	 * Set donation total
	 *
	 * @param	\IPS\donate\Money	$amount	Amount
	 * @return	void
	 */
	public function set_donation_total( \IPS\donate\Money $amount )
	{
		$this->_donationTotal = $amount;
		$this->_data['donation_total'] = $this->_donationTotal->amount;
	}
}