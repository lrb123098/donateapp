<?php
 /**
 * @brief				Donation Goal Widget

 */

namespace IPS\donate\widgets;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * donationGoal Widget
 */
class _donationGoal extends \IPS\Widget\StaticCache
{
	/**
	 * @brief	Widget Key
	 */
	public $key = 'donationGoal';
	
	/**
	 * @brief	App
	 */
	public $app = 'donate';
		
	/**
	 * @brief	Plugin
	 */
	public $plugin = '';

	/**
	 * Render a widget
	 *
	 * @return	string
	 */
	public function render()
	{
		$donations = array();
		
		if ( \IPS\Settings::i()->donate_donation_goal )
		{
			$startOfMonth = new \IPS\DateTime('first day of this month 00:00:00');
			$startOfMonth = $startOfMonth->getTimestamp();
			$donations['goal'] = new \IPS\donate\Money( (float) \IPS\Settings::i()->donate_donation_goal );
			$donations['total'] = \IPS\donate\Money::constructFromBase( \IPS\Db::i()->select( 'SUM(amount)', 'donate_transactions', array( 'type=? AND status=? AND date>=?', \IPS\donate\Transaction::TYPE_PAYMENT, \IPS\donate\Payment::STATUS_PAID, $startOfMonth ) )->first() ?: 0 );
			$donations['percentage'] = $donations['total']->amount > 0 ? ceil( 100 * ( $donations['total']->amount / $donations['goal']->amount ) ) : 0;
		}
		
		return $this->output( $donations );
	}
}