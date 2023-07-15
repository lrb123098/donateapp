<?php
/**
 * @brief				Expired Purchases Task

 */

namespace IPS\donate\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * expiredpurchases
 */
class _expiredpurchases extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		$gracePeriod = 21600;
		$select = \IPS\Db::i()->select( '*', 'donate_purchases', array( 'active=1 AND deactivate_date<?', time() - $gracePeriod ) );
		$purchases = iterator_to_array( new \IPS\Patterns\ActiveRecordIterator( $select, '\IPS\donate\Purchase' ) ) ?: array();
		
		foreach ( $purchases as $purchase )
		{
			$purchase->expire();
		}
		
		return NULL;
	}
}