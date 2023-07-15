<?php
/**
 * @brief				Pending Orders Task

 */

namespace IPS\donate\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * pendingorders
 */
class _pendingorders extends \IPS\Task
{
	/**
	 * Execute
	 *
	 * @return	mixed	Message to log or NULL
	 * @throws	\IPS\Task\Exception
	 */
	public function execute()
	{
		$timeout = 86400;
		$select = \IPS\Db::i()->select( '*', 'donate_orders', array( 'status=? AND date<?', \IPS\donate\Order::STATUS_PENDING, time() - $timeout ) );
		$orders = iterator_to_array(  new \IPS\Patterns\ActiveRecordIterator( $select, '\IPS\donate\Order' ) ) ?: array();
		
		foreach ( $orders as $order )
		{
			$order->cancel();
		}
		
		return NULL;
	}
}