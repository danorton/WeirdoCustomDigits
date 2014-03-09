<?php
/** @file WeirdoCustomDigits.php - Classes to convert arbitrary radix/digit characters
 *
 * @copyright
 * © 2014 Daniel Norton d/b/a WeirdoSoft - www.weirdosoft.com
 *
 * @section LICENSE
 * License: CC BY-SA 3.0
 *   This work is licensed under the Creative Commons
 *   Attribution-ShareAlike 3.0 Unported License. To view a copy of
 *   this license, visit http://creativecommons.org/licenses/by-sa/3.0/
 *   or send a letter to Creative Commons, 444 Castro Street, Suite 900,
 *   Mountain View, California, 94041, USA.
 *
 * @section REQUIREMENTS
 *  - PHP 5.3 or later
 *  - If using multi-byte or UTF-8 digits, PCRE UTF-8 support is required.
 *
 * @section LIMITATIONS
 *  - Only non-negative integers ("whole numbers") are supported.
 *
 * @file
 */

abstract class WeirdoCustomDigits {

	/** Digits for radixes up to base 70 (including bc and gmp up to base 36) */
	const DIGITS_70 =     '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ~!*()_.-';

	/** Digits compatibile with gmp from base 37 to base 62 */
	const DIGITS_62_GMP = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

	/** Digits up to base 51 less likely to be misread
	 *  These consists of the typical base-62 digits, less these eleven digits: 0O 1Ll 2Z 5S 8B
	 */
	const DIGITS_51_READABLE = 'wkW34679AabCcDdEeFfGgHhiJjKLMmNnoPpQqRrsTtUuVvXxYyz';

	/** Arabic decimal digits (UTF-8) */
	const DIGITS_10_ARABIC_EAST = '٠١٢٣٤٥٦٧٨٩';

	/** Set to true only if openssl random is not crypto safe */
	public $allowNonCryptoRandom;

	/**
	 * The maximum value of a number
	 *
	 * If not set or null, there is no practical limit. Otherwise, this is the internally
	 * represented maximum number allowed. For the integer subclass, WeirdoCustomDigitsInt,
	 * for example, this value is min(0x07ffffffffffffff,PHP_INT_MAX).
	 */
	public static $maximumValue;

	/**
	 * Class constructor
	 *
	 * @param[in] string|array $digits  case-sensitive characters which form the digits. The first
	 *                                  digit is zero, &c. If an array is passed, each element
	 *                                  represents a single digit, but numbers with multi-CHARACTER
	 *                                  digits cannot be passed as an input to this class (although
	 *                                  multi-BYTE Unicode characters are just fine).
	 *                                  Default is WeirdoCustomDigits::DIGITS_70.
	 * @param[in] int    $radix   number of digits (i.e. the radix). Default is the number of digits
	 *                            provided in $digits.
	 * @param[in] bool   $use_uc  true if to allow Unicode characters in the radix, else allow ASCII
	 *                            only. Default is true.
	 *
	 * Example:
	 * @code
	 *   $wrdx = new WeirdoCustomDigitInt(WeirdoCustomDigit::DIGITS_70,10);
	 * @endcode
	 */
	public function __construct( $digits = null, $radix = null, $use_uc = true ) {
		$this->allowNonCryptoRandom = false;
		// set the parameter-variant instance properties (which can be changed, later)
		$this->init( $digits, $radix, $use_uc );
	}

	/**
	 * Class instance initializer
	 *
	 * This can be called after first-time initialization to change the previous settings,
	 * which will be discarded.
	 *
	 * Same parameters as __construct().
	 */
	public function init( $digits = null, $radix = null, $use_uc = true ) {

		// set default character set for the digits
		if ( $digits === null ) {
			$digits = self::DIGITS_70;
		}

		// clear properties built with different parameters
		$this->_rangeNeededForDigits = array();
		$this->_bitsNeededForRange = array();
		$this->_randomBitsNeeded = array();

		// use unicode if requested
		$this->_is_using_uc = $use_uc;
		if ( $this->_is_using_uc ) {
			$this->_fn['str_split'] = function( $str ) { return WeirdoCustomDigits::uc_str_split( $str ); };
		} else {
			// support single-byte characters, only
			$this->_fn['str_split'] = function( $str ) { return str_split( $str ); };
		}

		// get the digits as an array ( if not given an array )
		if ( is_array( $digits ) ) {
			$digitsArray = $digits;
		} else {
			$digitsArray = $this->str_split( $digits );
		}

		// get the ordinal value of each digit (performance shortcut)
		// PHP coerces keys to integers, if possible, so we need to make sure the array key isn't
		// a decimal number, to preserve the keys when the array is flipped.
		$digitValues =
			array_flip(
				array_map(
					function ( $digit ) {
						return "#$digit";
					},
						$digitsArray
					)
			);

		// verify that each digit is unique
		if ( count( $digitsArray ) != count( $digitValues ) ) {
			throw new ErrorException(
				sprintf( 'Error detected by %s(): radix digits are not all unique.', __METHOD__ )
			);
		}

		// get the radix, if not already set
		if ( $radix === null ) {
			$radix = count( $digitValues );
		}

		// verify that radix is in range for the given digits
		if ( $radix < 1 || $radix > count( $digitValues ) ) {
			throw new ErrorException(
				sprintf( 'Error detected by %s(): radix is out of range of available digits.', __METHOD__ )
			);
		}

		// set properties
		$this->_digitsArray = array_slice( $digitsArray, 0, $radix );
		$this->_digitValues = array_slice( $digitValues, 0, $radix );
		$this->_radix = $radix;

	}

	/**
	 * Validate a single digit of a custom number
	 *
	 * If the validation fails, this method throws an ErrorException error.
	 *
	 * @param[in] string $customNumberDigit digit to validate
	 */
	public function validateCustomDigit( $customNumberDigit ) {
		if ( !isset( $this->_digitValues["#$customNumberDigit"] ) ) {
			throw new ErrorException( sprintf( 'Error detected by %s(): digit is not in the custom set of digits ( %s ).', __METHOD__, $customNumberDigit ) );
		}
	}

	/**
	 * Validate a custom-number string
	 *
	 * Validate each digit in the string
	 *
	 * If the validation fails, this method throws an ErrorException error.
	 *
	 * @param[in] string $customNumber custom number to validate
	 */
	public function validateCustomNumber( $customNumber ) {
		foreach ( $this->str_split( $customNumber ) as $digit ) {
			$this->validateCustomDigit( $digit );
		}
	}

	/**
	 * Convert a decimal number/string to a binary-number string of zero (0) and one (1) characters.
	 *
	 * Unlike the PHP library function, there is no limit to the number of digits in the given
	 * number, or in the resulting binary number.
	 *
	 * @param[in] string  $decNumber string of decimal digits (0-9)
	 * @returns   string  binary-number string of zero (0) and one (1) characters.
	 */
	public static function decbin( $decNumber ) {
		return static::hexbin( static::dechex( $decNumber ) );
	}

	/**
	 * Convert a hexadecimal string to a binary-number string of zero (0) and one (1) characters.
	 *
	 * @param[in] string $hexNumber string of hexadecimal digits [0-9a-fA-F]
	 * @returns   string  binary-number string of zero (0) and one (1) characters.
	 */
	public static function hexbin( $hexNumber ) {
		// trim leading zeros
		if ( $hexNumber[0] === '0' ) {
			$hexNumber = ltrim( $hexNumber, '0' );
			if ( $hexNumber === '' ) {
				return '0'; // quick exit for the degenerate case
			}
		}

		$sum = array();
		$hexDigits = array_reverse( str_split( $hexNumber, static::$_hexdecChunkSize ) );

		do {
			$sum[] = decbin( hexdec( array_pop( $hexDigits ) ) );
		} while ( count( $hexDigits ) );

		return join( '', $sum );
	}

	/**
	 * Convert a decimal number to a custom-number string.
	 *
	 * @param[in] int|string  $decimalNumber integer or string of decimal digits [0-9]. e.g. '139874'
	 * @param[in] int         $minCustomDigits zero-fill the result to this many digits.
	 *                        Default is 1.
	 *                        (Note that a zero isn't necessarily '0'!)
	 * @returns   string      custom-number string of custom digit characters
	 */
	abstract public function customFromDecimal( $decimalNumber, $minCustomDigits=1 );

	/**
	 * Convert a hexadecimal number to a custom-number string.
	 *
	 * @param[in] string  $hexNumber string of hexadecimal digits [0-9a-fA-F]. Example: 'F3E8'
	 * @param[in] int     $minCustomDigits zero-fill the result to this many digits. Default is 1.
	 *                    (Note that a zero isn't necessarily '0'!)
	 * @returns   string  custom-number string of custom digit characters
	 */
	abstract public function customFromHex( $hexNumber, $minCustomDigits=1 );

	/**
	 * Convert an internally represented number to a custom-number string.
	 *
	 * @param[in] any     $internal a value returned from another method (e.g. from internalFrom...())
	 * @param[in] int     $minCustomDigits zero-fill the result to this many digits
	 *                    (Note that a zero isn't necessarily '0'!)
	 * @returns   string  custom-number string of custom digit characters
	 */
	abstract public function customFromInternal( $internal, $minCustomDigits=1 );

	/**
	 * Convert a binary-number string to a decimal-number string
	 *
	 * @param[in] string  $binNumber a binary-number string of zeros and ones
	 * @returns   string  decimal-number string of decimal digits
	 */
	public static function bindec( $binNumber ) {
		return static::hexdec( static::binhex( $decNumber ) );
	}

	/**
	 * Convert a binary-number string to a decimal-number string
	 *
	 * @param[in] string  $customNumber a custom-number string
	 * @returns   string  decimal-number string of decimal digits
	 */
	abstract public function decimalFromCustom( $customNumber );

	/**
	 * Convert an internally represented number to a decimal-number string
	 *
	 * @param[in] any     $internal a value returned from another method (e.g. from internalFrom...())
	 * @returns   string  decimal-number string of decimal digits
	 */
	abstract public function decimalFromInternal( $internal );

	/**
	 * Convert a binary-number string to a hexadecimal-number string
	 *
	 * There is no practical limit on the number of digits in the input or the result.
	 *
	 * @param[in] any     $internal a value returned from another method (e.g. from internalFrom...())
	 * @returns   string  hexadecimal-number string of hexadecimal digits
	 */
	public static function binhex( $binNumber ) {
		// trim leading zeros
		if ( $binNumber[0] === '0' ) {
			$binNumber = ltrim( $binNumber, '0' );
			if ( $binNumber === '' ) {
				return '0';   // quick exit for the degenerate case
			}
		}

		$sum = array();
		// break up the string into chunks that each represent values less than PHP_INT_MAX
		$binDigits = array_reverse( str_split( strrev( $binNumber ), static::$_bindecChunkSize ) );
		do {
			$sum[] = dechex( bindec( strrev( array_pop( $binDigits ) ) ) );
		} while ( count( $binDigits ) );
		return join( '', array_reverse( $sum ) );
	}

	/**
	 * Convert a custom-number string to a hexadecimal-number string
	 *
	 * @param[in] string  $customNumber custom-number string
	 * @returns   string  hexadecimal-number string
	 */
	abstract public function hexFromCustom( $customNumber );

	/**
	 * Convert an internally represented number to a hexadecimal-number string
	 *
	 * @param[in] any     $internal a value returned from another method (e.g. from internalFrom...())
	 * @returns   string  hexadecimal-number string of hexadecimal digits
	 */
	abstract public function hexFromInternal( $internal );

	/**
	 * Convert a custom-number string to an internally represented (opaque) number
	 *
	 * @param[in] string  $customNumber custom-number string
	 * @returns   any     internally represented (opaque) number
	 */
	abstract public function internalFromCustom( $customNumber );

	/**
	 * Convert a decimal-number string to an internally represented (opaque) number
	 *
	 * @param[in] int|string  $decimalNumber integer or string of decimal digits [0-9]. e.g. '139874'
	 * @returns   any     internally represented (opaque) number
	 */
	abstract public function internalFromDecimal( $decimalNumber );

	/**
	 * Convert a hexadecimal-number string to an internally represented (opaque) number
	 *
	 * @param[in] string  $hexNumber string of hexadecimal digits [0-9a-fA-F]. Example: 'F3E8'
	 * @returns   any     internally represented (opaque) number
	 */
	abstract public function internalFromHex( $hexNumber );

	/**
	 * Get random bits, represented as a hexadecimal string
	 *
	 * The function returns random bits. Normally, the returned value is crypto-safe, but
	 * if the allowNonCryptoRandom property is set, the return value might not be crypto-safe.
	 * For additional details, see the PHP library function openssl_random_pseudo_bytes().
	 *
	 * @param[in] int     $nBits the number of random bits to obtain
	 * @returns   string  hexadecimal digits.
	 */
	public function hexFromRandomBits( $nBits ) {
		// fetch random bytes
		$bits = openssl_random_pseudo_bytes( ( $nBits + 7 ) >> 3, $crypto );
		if ( !$crypto && !$this->allowNonCryptoRandom ) {
			throw new ErrorException( sprintf( 'Error detected by %s(): opensll lacks crypto-quality random generator.', __METHOD__, $customNumberDigit ) );
		}
		// knock off the unwanted bits
		$bits[0] = chr( ord( $bits[0] ) & ( ( ( 1 << $nBits ) >> ( ( $nBits - 1 ) & ~0x07 ) ) - 1 ) );
		// return the hex result
		return bin2hex( $bits );
	}

	/**
	 * Get random bits, represented as a string in the custom base
	 *
	 * @param[in] int     $nDigits the number of random digits to obtain
	 * @returns   string  custom-number digits
	 */
	abstract public function customRandomDigits( $nDigits );

	/**
	 * Get random bits, represented as a string in the custom base
	 *
	 * @param[in] string  $rangeInternal an internally represented (opaque) number, specifying
	 *                    the range of random numbers to obtain (from zero to this number minus one)
	 * @returns   string  custom-number digits
	 */
	abstract public function customRandomFromInternalRange( $rangeInternal );

	/**
	 * Split a unicode string into an array of its individual characters.
	 *
	 * (Like the similarly named PHP library function str_split, but with Unicode support.)
	 * @param[in] string  $str string of Unicode characters
	 * @returns   array   array of single-Unicode-character strings
	 */
	public static function uc_str_split( $str ) {
		return preg_split( '/(?<!^)(?!$)/u', $str );
	}

	/**
	 * Split a string into an array of its individual characters, depending on Unicode property
	 *
	 * This function invokes either the PHP library function str_split or this class's
	 * method uc_str_split(), depending on if this instance was created or initialized
	 * last with the $use_uc flag true or false.
	 *
	 * @param[in] string  $str string of characters
	 * @returns   array   array of single-character strings
	 */
	protected function str_split( $str ) {
		return $this->_fn['str_split']( $str );
	}

	/**
	 * Get the range of numbers needed for specified number of custom-number digits.
	 *
	 * This function returns the limit of the range of numbers that can be expressed by the
	 * specified number of digits of the custom number's radix. Numbers can be from zero to
	 * this limit.
	 *
	 * @param[in] int     $nDigits number of custom-number digits
	 * @returns   any     internally represented limit value
	 */
	abstract protected function _getRangeNeededForCustomDigits( $nDigits );

	/**
	 * Get the number of binary bits needed to represent a number less than the specified limit.
	 *
	 * @param[in] any     $rangeInternal internally represented value of the range limit
	 * @returns   int     the number of bits needed to represent numbers in the entire range
	 */
	abstract protected function _getBitsNeededForRangeInternal( $rangeInternal );

	/**
	 * Trim an array, removing least-recently added elements
	 *
	 * The specified array is always appended to, so we simply remove
	 * a "chunk" of elements from the beginning when the array exceeeds
	 * the specified maximum.
	 *
	 * @param[in,out] array $array the array to trim (if needed)
	 * @param[in]     int   $maxCount the maximum allowed size of the array
	 * @param[in]     int   $chunkSize the number of elements in a "chunk"
	 */
	protected function _trim_array_lru( &$array, $maxCount, $chunkSize ) {
		if ( count( $array ) > $maxCount ) {
			$chunkLeft = $chunkSize;
			// ( We can't use array_splice, as it clobbers the remaining keys. )
			foreach ( array_keys( $array ) as $k ) {
				unset( $array[$k] );
				if ( --$chunkLeft <= 0 ) {
					break;
				}
			}
		}
	}

	/**
	 * Initialize this class's static properties.
	 *
	 * PHP only allows variable declarations with simple constants, so we have this
	 * function for more complex initialization of statics. Although "public" in
	 * construction, it is usable in this source file, only, immediately after this
	 * class is declared. Any attempt to invoke this method a second time will throw
	 * an ErrorException.
	 *
	 * @private
	 */
	public static function _initStatic() {
		if ( !self::$_hexdecChunkSize ) {
			self::$_hexdecChunkSize = strlen( dechex( PHP_INT_MAX>>8 ) );
			self::$_bindecChunkSize = self::$_hexdecChunkSize << 2;
		} else {
			throw new ErrorException( sprintf( 'Invalid invocation of %s().', __METHOD__ ) );
		}
	}

	/**
	 * An array of custom-number digits.
	 *
	 * The first element is zero, the second is unity, ...
	 */
	protected $_digitsArray;

	/**
	 * An associative array that maps custom-number digits to their numerical values.
	 */
	protected $_digitValues;

	/**
	 * The radix of the custom number.
	 */
	protected $_radix;

	/**
	 * Custom number ranges by number of digits.
	 *
	 * This is an array that maps the number of digits in a custom number to the internal
	 * representation of the limit for that number as expressed by the number of digits
	 * of the custom number. (e.g. 4 digits in base 10 has a limit of 10000.)
	 */
	protected $_rangeNeededForDigits;

	/**
	 * Number of bits by range of numbers.
	 *
	 * This ia an array that maps a range of numbers to the number of bits require to
	 * represent any number in that range. (Actually stored is the limit of the range,
	 * which starts at zero and ends at limit minus one.)
	 */
	protected $_bitsNeededForRange;

	/**
	 * The number of digits in a hexadecimal number that cannot possibly exceed PHP_INT_MAX.
	 */
	protected static $_hexdecChunkSize ;

	/**
	 * The number of digits in a binary number that cannot possibly exceed PHP_INT_MAX.
	 */
	protected static $_bindecChunkSize ;

	/**
	 * Function vector for mapable class functions.
	 */
	private $_fn;

	/**
	 * Boolean indicator if Unicode is supported by this instance. (See initialization
	 * parameter $use_uc.)
	 */
	private $_is_using_uc;


}
// Once-only invocation to initialize static properties
WeirdoCustomDigits::_initStatic();
