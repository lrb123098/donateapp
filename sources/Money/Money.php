<?php
/**
 * @brief				Money Model

 */

namespace IPS\donate;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Money
 */
class _Money
{
	/**
	 * @brief	Currency code
	 */
	protected $_currencyCode = 'USD';
	
	/**
	 * @brief	Currency symbol
	 */
	protected $_currencySymbol = '$';
	
	/**
	 * @brief	Currency base unit. 1 USD = 100 Cents
	 */
	protected $_baseUnit = 100;
	
	/**
	 * @brief	Amount
	 */
	protected $_amount;
	
	/**
	 * Constructor
	 *
	 * @param	mixed	$amount	Amount
	 * @param	boolean	$convertToBase	Convert to base unit?
	 * @return	void
	 */
	public function __construct( $amount, $convertToBase = TRUE )
	{
		$this->_amount = $this->parse( $amount, $convertToBase );
	}
	
	/**
	 * Construct from a base unit value
	 *
	 * @param	integer	$amount	Amount
	 * @return	static
	 */
	public static function constructFromBase( $amount )
	{
		return new static( $amount, FALSE );
	}
	
	/**
	 * Get currency code
	 *
	 * @return	string
	 */
	public function get_currencyCode()
	{
		return $this->_currencyCode;
	}
	
	/**
	 * Get currency symbol
	 *
	 * @return	string
	 */
	public function get_currencySymbol()
	{
		return $this->_currencySymbol;
	}
	
	/**
	 * Get base unit
	 *
	 * @return	integer
	 */
	public function get_baseUnit()
	{
		return $this->_baseUnit;
	}
	
	/**
	 * Get amount
	 *
	 * @return	integer
	 */
	public function get_amount()
	{
		return $this->_amount;
	}
	
	/**
	 * Set amount
	 *
	 * @param	integer	$value	Value to set
	 * @return	void
	 */
	public function set_amount( $value )
	{
		if ( !static::isValid( $value ) )
		{
			return;
		}
		
		$this->_amount = $this->parse( $value, FALSE );
	}
	
	/**
	 * Format as a string
	 *
	 * @return	string
	 */
	public function get_rawFormat()
	{
		return (string) ( $this->_amount / $this->_baseUnit );
	}
	
	/**
	 * Format as a readable string
	 *
	 * @return	string
	 */
	public function get_format()
	{
		return \IPS\Member::loggedIn()->language()->formatNumber( $this->rawFormat, $this->_amount % $this->_baseUnit ? log10( $this->_baseUnit ) : 0 );
	}
	
	/**
	 * String cast
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return $this->format;
	}
	
	/**
	 * Addition
	 *
	 * @param	mixed	$value	Value to add
	 * @return	void
	 */
	public function add( $value )
	{
		$value = $this->parse( $value );
		
		if ($value === 0)
		{
			return;
		}
		
		$result = $this->_amount + $value;
		
		if ( static::isValid( $result ) && $result > 0 )
		{
			$this->_amount = $result;
		}
	}
	
	/**
	 * Subtraction
	 *
	 * @param	mixed	$value	Value to subtract
	 * @return	void
	 */
	public function subtract( $value )
	{
		$value = $this->parse( $value );
		
		if ($value === 0)
		{
			return;
		}
		
		$result = $this->_amount - $value;
		
		if ( static::isValid( $result ) )
		{
			$this->_amount = $result;
		}
	}
	
	/**
	 * Multiplication
	 *
	 * @param	mixed	$value	Value to multiply by
	 * @return	void
	 */
	public function multiply( $value )
	{
		$value = $this->parse( $value, FALSE );
		
		if ($value === 0)
		{
			return;
		}
		
		$result = $this->_amount * $value;
		
		if ( static::isValid( $result ) && $result > 0 )
		{
			$this->_amount = $result;
		}
	}
	
	/**
	 * Division
	 *
	 * @param	mixed	$value	Value to divide by
	 * @return	void
	 */
	public function divide( $value )
	{
		$value = $this->parse( $value, FALSE );
		
		if ($value === 0)
		{
			return;
		}
		
		$result = intdiv( $this->_amount, $value );
		
		if ( static::isValid( $result ) && $result > 0 )
		{
			$this->_amount = $result;
		}
	}
	
	/**
	 * Equal to comparison
	 *
	 * @param	mixed	$value	Value to compare to
	 * @return	boolean
	 */
	public function isEqualTo( $value )
	{
		return $this->_amount === $this->parse( $value );
	}
	
	/**
	 * Less than comparison
	 *
	 * @param	mixed	$value	Value to compare to
	 * @return	boolean
	 */
	public function isLessThan( $value )
	{
		return $this->_amount < $this->parse( $value );
	}
	
	/**
	 * Less than or equal to comparison
	 *
	 * @param	mixed	$value	Value to compare to
	 * @return	boolean
	 */
	public function isLessThanOrEqualTo( $value )
	{
		return $this->_amount <= $this->parse( $value );
	}
	
	/**
	 * Greater than comparison
	 *
	 * @param	mixed	$value	Value to compare to
	 * @return	boolean
	 */
	public function isGreaterThan( $value )
	{
		return $this->_amount > $this->parse( $value );
	}
	
	/**
	 * Greater than or equal to comparison
	 *
	 * @param	mixed	$value	Value to compare to
	 * @return	boolean
	 */
	public function isGreaterThanOrEqualTo( $value )
	{
		return $this->_amount >= $this->parse( $value );
	}
	
	/**
	 * Parse into an valid monetary value
	 *
	 * @param	mixed	$value	Value to parse
	 * @param	boolean	$convertToBase	Convert to base unit?
	 * @return	integer
	 */
	public function parse( $value, $convertToBase = TRUE )
	{
		if ( $value instanceof static )
		{
			return $value->amount;
		}
		else if ( !static::isValid( $value ) )
		{
			return 0;
		}
		
		$result = 0;
		
		if ( \is_int( $value ) || \is_float( $value ) )
		{
			$result = $convertToBase ? $value * $this->_baseUnit : $value;
		}
		else if ( \is_string( $value ) )
		{			
			if ( $convertToBase && $decimalPoint = \mb_strpos( $value, '.' ) )
			{
				$decimalDigits = (int) floor( log10( $this->_baseUnit ) );
				$left = \mb_substr( $value, 0, $decimalPoint );
				$right = \mb_substr( $value, $decimalPoint + 1, $decimalDigits );
				$result = $left . $right;
			}
			else
			{
				$result = $convertToBase ? (int) $value * $this->_baseUnit : $value;
			}
		}
		
		$result = (int) $result;
		return static::isValid( $result ) ? $result : 0;
	}
	
	/**
	 * Are we able to parse the value?
	 *
	 * @param	mixed	$value	Value to evaluate
	 * @return	boolean
	 */
	public static function isValid( $value )
	{
		if ( \is_numeric( $value ) )
		{
			return $value >= 0;
		}
		else
		{
			return $value instanceof static;
		}
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