<?php
/**
 * @brief				Gateway Exception Class

 */

namespace IPS\donate\Gateway;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Exception
 */
class _Exception extends \RuntimeException
{
	/**
	 * @brief Completion pending
	 */
	const COMPLETION_PENDING = 'completion_pending';
	
	/**
	 * @brief Auth denied
	 */
	const AUTH_DENIED = 'auth_denied';
	
	/**
	 * @brief Invalid listener request
	 */
	const INVALID_LISTENER_REQUEST = 'invalid_listener_request';
	
	/**
	 * @brief Invalid listener event
	 */
	const INVALID_LISTENER_EVENT = 'invalid_listener_event';
	
	/**
	 * @brief Duplicate listener event
	 */
	const DUPLICATE_LISTENER_EVENT = 'duplicate_listener_event';
	
	/**
	 * @brief Invalid order
	 */
	const INVALID_ORDER = 'invalid_order';
	
	/**
	 * @brief Invalid order request
	 */
	const INVALID_ORDER_REQUEST = 'invalid_order_request';
	
	/**
	 * @brief Invalid order status
	 */
	const INVALID_ORDER_STATUS = 'invalid_order_status';
	
	/**
	 * @brief Invalid response
	 */
	const INVALID_RESPONSE = 'invalid_response';
	
	/**
	 * @brief Invalid response token
	 */
	const INVALID_RESPONSE_TOKEN = 'invalid_response_token';
}