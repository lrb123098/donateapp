<?php
/**
 * @brief				Donate Application Class

 */

namespace IPS\donate;

/**
 * Donate Application Class
 */
class _Application extends \IPS\Application
{
	/**
	 * @brief List of 3rd party libraries to autoload
	 */
	const LIBRARIES = array(
		'BraintreeHttp',
		'PayPal',
		'Steam'
	);
	
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		foreach ( static::LIBRARIES as $library )
		{
			if ( !isset( \IPS\IPS::$PSR0Namespaces[$library] ) )
			{
				\IPS\IPS::$PSR0Namespaces[$library] = \IPS\ROOT_PATH . '/applications/donate/sources/3rd_party/' . $library;
			}
		}
	}
	
	/**
	 * Install 'other' items.
	 *
	 * @return void
	 */
	public function installOther()
	{
		if ( !\IPS\Db::i()->checkForColumn( 'core_members', 'donation_total' ) )
		{
			\IPS\Db::i()->addColumn( 'core_members', array(
				'name' => 'donation_total',
				'type' => 'BIGINT',
				'length' => 20,
				'allow_null' => TRUE,
				'unsigned' => TRUE
			) );
		}
	}
	
	/**
	 * Generate a unique token
	 *
	 * @param	string	$dbTable	Db table
	 * @param	string	$dbColumn	Db column
	 * @return	string
	 */
	public static function generateUniqueToken( $dbTable = NULL, $dbColumn = 'token' )
	{
		$alphabet = str_split( 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' );
		$maxRange = \count( $alphabet ) - 1;
		shuffle( $alphabet );
		
		for ( $i = 0, $maxTries = 25; $i < $maxTries; $i++ )
		{
			$id = '';
			
			for ( $j = 0; $j < 18; $j++ )
			{
				$id .= random_int( 1, 5 ) <= 4 ? random_int( 0, 9 ) : $alphabet[random_int( 0, $maxRange )];
			}
			
			if ( !$dbTable )
			{
				return $id;
			}
			else
			{
				if ( !\IPS\Db::i()->select( 'COUNT(*)', $dbTable, array( $dbTable . '.' . $dbColumn . '=?', $id ) )->first() )
				{
					return $id;
				}
			}
		}
		
		return NULL;
	}
	
	/**
	 * Encrypt array
	 *
	 * @param	array	$array	Array
	 * @return	array
	 */
	public static function encryptArray( $array )
	{
		foreach ( $array as $k => $v )
		{
			if ( \is_string( $v ) && !empty( $v ) )
			{
				$array[$k] = \IPS\Text\Encrypt::fromPlaintext( $v )->cipher;
			}
		}
		
		return $array;
	}
	
	/**
	 * Decrypt array
	 *
	 * @param	array	$array	Array
	 * @return	array
	 */
	public static function decryptArray( $array )
	{
		foreach ( $array as $k => $v )
		{
			if ( \is_string( $v ) && !empty( $v ) )
			{
				$array[$k] = \IPS\Text\Encrypt::fromCipher( $v )->decrypt();
			}
		}
		
		return $array;
	}
	
	/**
	 * Seconds format
	 *
	 * @param	int	$seconds	Seconds
	 * @param	bool	$asArray	As array
	 * @param	bool	$forcePluralize	Force pluralize
	 * @param	bool	$addToLangStack	Add to lang stack
	 * @return	string|array
	 */
	public static function secondsFormat( $seconds, $asArray = FALSE, $forcePluralize = FALSE, $addToLangStack = TRUE )
	{
		$seconds = (int) $seconds;
		$number = 0;
		$word = '';
		
		if ( $seconds < 60 )
		{
			$number = $seconds;
			$word = 'second';
		}
		else if ( $seconds < 3600 )
		{
			$number = $seconds / 60;
			$word = 'minute';
		}
		else if ( $seconds < 86400 )
		{
			$number = $seconds / 3600;
			$word = 'hour';
		}
		else if ( $seconds < 604800 )
		{
			$number = $seconds / 86400;
			$word = 'day';
		}
		else if ( $seconds < 2419200 )
		{
			$number = $seconds / 604800;
			$word = 'week';
		}
		else if ( $seconds < 31536000 )
		{
			$number = $seconds / 2629800;
			$word = 'month';
		}
		else
		{
			$number = $seconds / 31536000;
			$word = 'year';
		}
		
		$number = fmod( $number, 1 ) === 0.0 ? (int) $number : round( $number, 1, PHP_ROUND_HALF_EVEN );
		$word = $word . ( $forcePluralize ? 's' : ( $number > 1 ? 's' : '' ) );
		
		if ( $addToLangStack )
		{
			$word = \IPS\Member::loggedIn()->language()->addToStack( $word );
		}
		
		if ( $asArray )
		{
			return array(
				'number' => $number,
				'word' => $word
			);
		}
		else
		{
			return $number . ' ' . $word;
		}
	}
	
	/**
	 * [Node] Get Node Icon
	 *
	 * @return	string
	 */
	protected function get__icon()
	{
		return 'money';
	}
}