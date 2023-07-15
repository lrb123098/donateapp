<?php
require_once __DIR__ . '/../../../init.php';
spl_autoload_register( function( $class )
{
	include_once __DIR__ . '/../sources/3rd_party/' . str_replace('\\', '/', $class) . '.php';
} );

try
{
	$gateway = \IPS\donate\Gateway::getProvider( \IPS\Request::i()->provider );
	
	if ( !$gateway || !$gateway->eventListener )
	{
		throw new \UnexpectedValueException;
	}
	
	$gateway->eventListener->parseRequest();
	
	if ( !$gateway->eventListener->auth() )
	{
		throw new \IPS\donate\Gateway\Exception( 'auth_denied' );
	}
	
	$gateway->eventListener->process();
	\http_response_code(200);
}
catch ( \IPS\donate\Gateway\Exception $e )
{
	\IPS\Log::debug( $e, 'donate_gateway' );
	\http_response_code(200);
}
catch ( \Exception $e )
{
	\IPS\Log::log( $e, 'donate_gateway' );
	\http_response_code(403);
}