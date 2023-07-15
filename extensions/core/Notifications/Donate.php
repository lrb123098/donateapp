<?php
 /**
 * @brief				Notification Options - Donate

 */

namespace IPS\donate\extensions\core\Notifications;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Notification Options
 */
class _Donate
{
	/**
	 * Get configuration
	 *
	 * @param	\IPS\Member	$member	The member
	 * @return	array
	 */
	public function getConfiguration( $member )
	{
		return array(
			'order_completed' => array( 'default' => array( 'inline' ), 'disabled' => array( 'email' ) ),
			'gifted' => array( 'default' => array( 'inline' ), 'disabled' => array( 'email' ) )
		);
	}
	
	/**
	 * Parse notification: order_completed
	 *
	 * @param	\IPS\Notification\Inline	$notification	The notification
	 * @return	array
	 */
	public function parse_order_completed( \IPS\Notification\Inline $notification )
	{
		$item = \IPS\donate\Item::load( (int) $notification->extra['item'] );
		
		if ( !$item )
		{
			throw new \OutOfRangeException;
		}
		
		return array(
			'title' => \IPS\Member::loggedIn()->language()->addToStack( 'notification__order_completed', FALSE, array( 'sprintf' => $item->name ) ),
			'url' => \IPS\Http\Url::internal( 'app=donate&module=donate&controller=mydonations', 'front', 'donate_mydonations' )
		);
	}
	
	/**
	 * Parse notification: gifted
	 *
	 * @param	\IPS\Notification\Inline	$notification	The notification
	 * @return	array
	 */
	public function parse_gifted( \IPS\Notification\Inline $notification )
	{
		$item = \IPS\donate\Item::load( (int) $notification->extra['item'] );
		$gifter = \IPS\Member::load( (int) $notification->extra['gifter'] );
		
		if ( !$item || !$gifter )
		{
			throw new \OutOfRangeException;
		}
		
		return array(
			'title' => \IPS\Member::loggedIn()->language()->addToStack( 'notification__gifted', FALSE, array( 'sprintf' => array( $gifter->name, $item->name ) ) ),
			'url' => \IPS\Http\Url::internal( 'app=donate&module=donate&controller=mydonations', 'front', 'donate_mydonations' ),
			'author' => $gifter
		);
	}
}