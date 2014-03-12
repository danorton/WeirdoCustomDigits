<?php
/**
 * @addtogroup WeirdoCustomDigits
 *
 * This module provides the WeirdoCustomDigits abstract PHP class and implementation subclasses,
 * which perform radix conversion on numbers with arbitrary digits. The digits of a such a
 * **custom number** can be any Unicode character, and is not limited to ASCII alphanumerics.
 * 
 * For example, a base-4 custom number could have the digits, "X", "Y", "Z" & "T", where "X"
 * represents zero (decimal "0"), "Y" represents unity (decimal "1"), "Z" represents the
 * next natural digit (decimal "2") &c. The decimal number 105 expressed as such a custom
 * number would be "YZZY". Expanding the digits as a polynomial produces
 *
 *  -<span style="font-size: 150%">"Y"×4³ + "Z"×4² + "Z"×4¹ + "Y"×4⁰ = 1×64 + 2×16 + 2×4 + 1×1 = 105</span>
 *
 * As "X" is the zero digit, it can be repeated any number of times before a number, just
 * as with leading decimal zeros before a decimal number. For example, "YZZY" is numerically
 * identical to "XYZZY".
 *
 * Code example:
 * @code{.php}
 * require_once('WeirdoCustomDigitsInt.php') ;
 * $converter = WeirdoCustomDigitsInt('XYZT') ;
 * $number = $converter->customFromDecimal(105) ;
 * var_dump($number) ;
 * @endcode
 * Output:
 * @code
 * string(4) "YZZY"
 * @endcode
 *
 * When working with numbers from bases in the range from 2 to 36, it might be more practical to
 * use the PHP function <a href="http://php.net/base_convert">base_convert()</a> and then
 * substitute the characters using <a href="http://php.net/base_convert">str_replace()</a>. (If
 * the gmp extension is available, you could do a similar conversion for bases up to base 62.)
 * Consequently, this module is more practical for number bases above 36 (or above base 62, if gmp
 * is available).
 *
 * Otherwise, there is no practical limit on the radix, provided each digit in the radix can be
 * represented by a different Unicode character.
 *
 * There is one important limitation on numbers: <b>Only non-negative integers are supported by this
 * module</b>.
 *
 * @section subclasses Implementation subclasses
 *
 * The WeirdoCustomDigitsInt subclass implements the functions using basic PHP arithmetic.
 * It is limited to numbers that range from zero to the smaller of:
 *   -# @b PHP_INT_MAX and
 *   -# The maximum of the contiguous set of integers that can be expressed as a float.
 *
 * Typical PHP implementations use IEEE 754 for implementing floating point numbers, so the
 * typical limit for 32-bit environments is 2³¹ (0x7FFFFFFF) and for 64-bit environments, the
 * limit is 2⁵³ (0x1FFFFFFFFFFFF).
 *
 * The bc- and gmp-based implementations (WeirdoCustomDigitsBc and WeirdoCustomDigitsGmp) remove
 * the limitation on the ranges of numeric values.
 *
 * The classes also provide methods for producing random numbers of uniform distribution,
 * expressed as a custom number.
 *
 * The module provides several pre-defined radix sets:
 *   - WeirdoCustomDigits::DIGITS_70 - Digits for radixes up to base 70 (compatible with @b base_convert and gmp up to base 36)
 *   - WeirdoCustomDigits::DIGITS_62_GMP - Digits for radixes up to base 62 (compatible with gmp from base 37 to base 62)
 *   - WeirdoCustomDigits::DIGITS_10_ARABIC_EAST - The Eastern Arabic numerals (٠١٢٣٤٥٦٧٨٩).
 *   - WeirdoCustomDigits::DIGITS_51_READABLE - digits that are less likely to be confused with other digits.
 *
 * @section Requirements
 *  - PHP 5.3 or later
 *  - If using multi-byte or UTF-8 digits, PCRE UTF-8 support is required.
 *
 * @section Limitations
 *  - Only non-negative integers ("whole numbers") are supported.
 * @{
 *
 * @file
 * @{
 * @copyright
 *   © 2014 Daniel Norton d/b/a WeirdoSoft - www.weirdosoft.com
 *
 * @section License
 *    - <b>CC BY-SA 3.0</b> -
 *   This work is licensed under the Creative Commons
 *   Attribution-ShareAlike 3.0 Unported License. To view a copy of
 *   this license, visit http://creativecommons.org/licenses/by-sa/3.0/
 *   or send a letter to Creative Commons, 444 Castro Street, Suite 900,
 *   Mountain View, California, 94041, USA.
 * @}
 */

/**
 * Abstract class for converting custom numbers with arbitrary radixes and digit characters
 *
 */
abstract class WeirdoCustomDigits {

	/** Class version */
	const VERSION = '1.0.1' ;

	/** Digits for radixes up to base 70 (compatible with base_convert and gmp up to base 36) */
	const DIGITS_70 =     '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_.*~!()-' ;

	/** Digits compatibile with gmp from base 37 to base 62 */
	const DIGITS_62_GMP = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz' ;

	/** Digits up to base 51 less likely to be misread
	 *  These consists of a subset of the typical base-62 digits, less these eleven digits:
	 *  0O 1Ll 2Z 5S 8B
	 */
	const DIGITS_51_READABLE = 'wkW34679AabCcDdEeFfGgHhiJjKLMmNnoPpQqRrsTtUuVvXxYyz' ;

	/** Arabic decimal digits (UTF-8) */
	const DIGITS_10_ARABIC_EAST = '٠١٢٣٤٥٦٧٨٩' ;

	/** Precision of IEEE 754 floating point number */
	const IEEE_754_SIGNIFICAND_BITS = 53 ;

	/** Set to @c true only if openssl random is not crypto safe */
	public $allowNonCryptoRandom ;

	/** Maximum of contiguous range of integers that PHP supports for arithmetic */
	public static $phpIntegerMaxBits = WeirdoCustomDigits::IEEE_754_SIGNIFICAND_BITS ;

	/**
	 * The maximum value of a number
	 *
	 * If not set or @c null, there is no practical limit. Otherwise, this is the internally
	 * represented maximum number allowed. For the integer subclass WeirdoCustomDigitsInt,
	 * for example, this value is PHP_INT_MAX ;
	 */
	public static $maximumValue = null ;

	/**
	 * Class constructor
	 *
	 * @param[in] string|array $digits  set of case-sensitive-distinct characters that form the digits
	 *                                  of a custom number. The first digit is zero, &c. If an array
	 *                                  is passed, each element represents a single digit. (Numbers
	 *                                  with multi-CHARACTER digits can be rendered, but not parsed,
	 *                                  although multi-BYTE Unicode characters are just fine). Default
	 *                                  is WeirdoCustomDigits::DIGITS_70.
	 * @param[in] int    $radix   number of digits (i.e. the radix). Default is the number of digits
	 *                            provided in @b $digits.
	 * @param[in] bool   $use_uc  @c true if to allow Unicode characters in the custom number's set of
	 *                            digits, else allow ASCII only. Default is @c true.
	 *
	 * @b Example:
	 * @code
	 * // Manipulate base-65 numbers that have the digits [0-9a-zA-Z_.*].
	 * $wrdx = new WeirdoCustomDigitInt(WeirdoCustomDigits::DIGITS_70,65) ;
	 * @endcode
	 */
	public function __construct( $digits = null, $radix = null, $use_uc = true ) {
		$this->allowNonCryptoRandom = false ;
		// set the parameter-variant instance properties (which can be changed, later)
		$this->init( $digits, $radix, $use_uc ) ;
	}

	/**
	 * Class instance initializer
	 *
	 * This can be called after first-time initialization to change the previous settings
	 * (which will be discarded).
	 *
	 * Same parameters as __construct().
	 */
	public function init( $digits = null, $radix = null, $use_uc = true ) {

		// set default character set for the digits
		if ( $digits === null ) {
			$digits = self::DIGITS_70 ;
		}

		// clear properties built with different parameters
		$this->_rangeNeededForDigits = array() ;
		$this->_bitsNeededForRange = array() ;
		$this->_randomBitsNeeded = array() ;

		// use unicode if requested
		$this->_is_using_uc = $use_uc ;
		if ( $this->_is_using_uc ) {
			$this->_fn['str_split'] =
				function( $str ) {
					return WeirdoCustomDigits::uc_str_split( $str ) ;
				} ;
		} else {
			// support single-byte characters, only
			$this->_fn['str_split'] =
				function( $str ) {
					return str_split( $str ) ;
				} ;
		}

		// get the digits as an array ( if not given an array )
		if ( is_array( $digits ) ) {
			$digitsArray = $digits ;
		} else {
			$digitsArray = $this->str_split( $digits ) ;
		}

		// get the ordinal value of each digit (performance shortcut)
		// PHP coerces keys to integers, if possible, so we need to make sure the array key isn't
		// a decimal number, to preserve the keys when the array is flipped.
		$digitValues =
			array_flip(
				array_map(
					function ( $digit ) {
						return "#$digit" ;
					},
						$digitsArray
					)
			) ;

		// verify that each digit is unique
		if ( count( $digitsArray ) != count( $digitValues ) ) {
			throw new ErrorException(
				sprintf( 'Error detected by %s(): radix digits are not all unique.', __METHOD__ )
			) ;
		}

		// get the radix, if not already set
		if ( $radix === null ) {
			$radix = count( $digitValues ) ;
		}

		// verify that radix is in range for the given digits
		if ( $radix < 1 || $radix > count( $digitValues ) ) {
			throw new ErrorException(
				sprintf( 'Error detected by %s(): radix is out of range of available digits.', __METHOD__ )
			) ;
		}

		// set properties
		$this->_digitsArray = array_slice( $digitsArray, 0, $radix ) ;
		$this->_digitValues = array_slice( $digitValues, 0, $radix ) ;
		$this->_radix = $radix ;

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
			throw new ErrorException(
				sprintf( 'Error detected by %s(): digit is not in the set of custom digits ( %s ).',
					__METHOD__, $customNumberDigit
				)
			) ;
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
			$this->validateCustomDigit( $digit ) ;
		}
	}

	/**
	 * Convert a decimal number/string to a binary-number string of zero (0) and one (1) characters.
	 * @abstract
	 *
	 * @param[in] string  $decimalNumber string of decimal digits (0-9)
	 * @returns           binary-number string of zero (0) and one (1) characters.
	 */
	public static function binFromDecimal( $decimalNumber ) {
		throw new ErrorException(
			sprintf( 'Error detected by %s(): unimplemented abstract function invoked.', __METHOD__ )
		) ;
	}

	/**
	 * Convert a hexadecimal string to a binary-number string of zero (0) and one (1) characters.
	 *
	 * This method imposes no limit on the number of digits in the given number or in the resulting
	 * binary number.
	 *
	 * @param[in] string $hexNumber string of hexadecimal digits [0-9a-fA-F]
	 * @returns          binary-number string of zero (0) and one (1) characters.
	 */
	public static function binFromHex( $hexNumber ) {

		// trim leading zeros
		if ( $hexNumber[0] === '0' ) {
			$hexNumber = ltrim( $hexNumber, '0' ) ;
			if ( $hexNumber === '' ) {
				return '0'; // quick exit for the degenerate case
			}
		}

		// split the number into chunks that we can pass to decbin(hexdec($chunk))
		$hexChunks = str_split( strrev($hexNumber), self::$_hexbinChunkSize ) ;

		// convert the highest-order chunk to binary and put into the first element of an array
		$binaryChunks = array( decbin( hexdec( strrev( array_pop( $hexChunks ) ) ) ) ) ;

		// convert and pad the remaining hex chunks to equal-length binary chunks
		while ( count( $hexChunks ) ) {
			$binaryChunks[] = sprintf(self::$_binChunkPaddingFormat, // left zero pad
					decbin( hexdec( strrev( array_pop( $hexChunks ) ) ) )
				) ;
		}

		// join all the binary chunks into a string
		return join( '', $binaryChunks ) ;
	}


	/**
	 * Convert a decimal number to a custom-number string.
	 *
	 * @param[in] int|string  $decimalNumber integer or string of decimal digits [0-9]. e.g. '139874'
	 * @param[in] int         $minCustomDigits zero-fill the result to this many digits.
	 *                        Default is 1.
	 *                        (Note that a zero isn't necessarily the ASCII character '0'!)
	 * @returns               custom-number string of custom digit characters
	 */
	abstract public function customFromDecimal($decimalNumber, $minCustomDigits=1 ) ;

	/**
	 * Convert a hexadecimal number to a custom-number string.
	 *
	 * @param[in] string  $hexNumber string of hexadecimal digits [0-9a-fA-F]. Example: 'F3E8'
	 * @param[in] int     $minCustomDigits zero-fill the result to this many digits. Default is 1.
	 *                    (Note that a zero isn't necessarily the ASCII character '0'!)
	 * @returns           custom-number string of custom digit characters
	 */
	abstract public function customFromHex( $hexNumber, $minCustomDigits=1 ) ;

	/**
	 * Convert an internally represented number to a custom-number string.
	 *
	 * @param[in] any     $internal a value returned from another method (e.g. from internalFrom...())
	 * @param[in] int     $minCustomDigits zero-fill the result to this many digits
	 *                    (Note that a zero isn't necessarily the ASCII character '0'!)
	 * @returns           custom-number string of custom digit characters
	 */
	abstract public function customFromInternal( $internal, $minCustomDigits=1 ) ;

	/**
	 * Convert a binary-number string to a decimal-number string
	 * @abstract
	 *
	 * @param[in] string  $binNumber a binary-number string of zeros and ones
	 * @returns           decimal-number string of decimal digits
	 */
	public static function decimalFromBin( $binNumber ) {
		throw new ErrorException(
			sprintf( 'Error detected by %s(): unimplemented abstract function invoked.', __METHOD__ )
		) ;
	}

	/**
	 * Convert a binary-number string to a decimal-number string
	 * @abstract
	 *
	 * @param[in] string  $customNumber a custom-number string
	 * @returns           decimal-number string of decimal digits
	 */
	abstract public function decimalFromCustom( $customNumber ) ;

	public static function decimalFromHex( $hexNumber ) {
		throw new ErrorException(
			sprintf( 'Error detected by %s(): unimplemented abstract function invoked.', __METHOD__ )
		) ;
	}

	/**
	 * Convert an internally represented number to a decimal-number string
	 *
	 * @param[in] any     $internal a value returned from another method (e.g. from internalFrom...())
	 * @returns           decimal-number string of decimal digits
	 */
	abstract public function decimalFromInternal( $internal ) ;

	/**
	 * Convert a binary-number string to a hexadecimal string
	 *
	 * This method imposes no limit on the number of digits in the given number or in the resulting
	 * hexadecimal number.
	 *
	 * @param[in] string  $binNumber a binary-number string of zeros and ones
	 * @returns           hexadecimal string of hexadecimal digits
	 */
	public static function hexFromBin( $binNumber ) {

		// trim leading zeros
		if ( $binNumber[0] === '0' ) {
			$binNumber = ltrim( $binNumber, '0' ) ;
			if ( $binNumber === '' ) {
				return '0';   // quick exit for the degenerate case
			}
		}

		// split the number into chunks that we can pass to dechex(bindec($chunk))
		$binChunks = str_split( strrev($binNumber), self::$_binhexChunkSize ) ;

		// convert the highest-order chunk to hex and put into the first element of an array
		$hexChunks = array( dechex( bindec( strrev( array_pop( $binChunks ) ) ) ) ) ;

		// convert and pad the remaining binary chunks to equal-length hex chunks
		while ( count( $binChunks ) ) {
			$hexChunks[] = sprintf(self::$_hexChunkPaddingFormat, // left zero pad
					dechex( bindec( strrev( array_pop( $binChunks ) ) ) )
				) ;
		}
		// join all the hex chunks into a string
		return join( '', $hexChunks ) ;
	}

	/**
	 * Convert a custom-number string to a hexadecimal string
	 *
	 * @param[in] string  $customNumber custom-number string
	 * @returns           hexadecimal string
	 */
	abstract public function hexFromCustom( $customNumber ) ;

	/**
	 * Convert a decimal-number string to a hexadecimal string
	 * @abstract
	 *
	 * @param[in] string  $decimalNumber decimal-number string
	 * @returns           hexadecimal string
	 */
	public static function hexFromDecimal( $decimalNumber ) {
		throw new ErrorException(
			sprintf( 'Error detected by %s(): unimplemented abstract function invoked.', __METHOD__ )
		) ;
	}

	/**
	 * Convert an internally represented number to a hexadecimal string
	 *
	 * @param[in] any     $internal a value returned from another method (e.g. from internalFrom...())
	 * @returns           hexadecimal string of hexadecimal digits
	 */
	abstract public function hexFromInternal( $internal ) ;

	/**
	 * Convert a custom-number string to an internally represented (opaque) number
	 *
	 * @param[in] string  $customNumber custom-number string
	 * @returns           internally represented (opaque) number
	 */
	abstract public function internalFromCustom( $customNumber ) ;

	/**
	 * Convert a decimal-number string to an internally represented (opaque) number
	 *
	 * @param[in] int|string  $decimalNumber integer or string of decimal digits [0-9]. e.g. '139874'
	 * @returns           internally represented (opaque) number
	 */
	abstract public function internalFromDecimal( $decimalNumber ) ;

	/**
	 * Convert a hexadecimal string to an internally represented (opaque) number
	 *
	 * @param[in] string  $hexNumber string of hexadecimal digits [0-9a-fA-F]. Example: 'F3E8'
	 * @returns           internally represented (opaque) number
	 */
	abstract public function internalFromHex( $hexNumber ) ;

	/**
	 * Get random bits, represented as a hexadecimal string
	 *
	 * The function returns random bits. Normally, the returned value is crypto-safe, but
	 * if the allowNonCryptoRandom property is set, the return value might not be crypto-safe.
	 * For additional details, see the PHP library function
	 * <a href="http://php.net/openssl_random_pseudo_bytes">openssl_random_pseudo_bytes()</a>.
	 *
	 * @param[in] int     $nBits the number of random bits to obtain
	 * @returns           hexadecimal digits.
	 */
	public function hexFromRandomBits( $nBits ) {
		// fetch random bytes
		$bits = openssl_random_pseudo_bytes( ( $nBits + 7 ) >> 3, $crypto ) ;
		if ( !$crypto && !$this->allowNonCryptoRandom ) {
			throw new ErrorException(
				sprintf( 'Error detected by %s(): PHP lacks crypto-quality random generator.',
					__METHOD__, $customNumberDigit
				)
			) ;
		}
		// knock off the unwanted bits
		$maskBits = ($nBits & 7) ;
		if ( $maskBits ) {
			$bits[0] = chr( ord( $bits[0] ) & ( ( 1 << $maskBits ) - 1 ) ) ;
		}

		// return the hex result
		return bin2hex( $bits ) ;
	}

	/**
	 * Get a specified number of random custom-number digits
	 *
	 * @param[in] int     $nDigits the number of random digits to obtain
	 * @returns           string of custom-number digits
	 */
	abstract public function customRandomDigits( $nDigits ) ;

	/**
	 * Get a random custom number within specified range
	 *
	 * @param[in] string  $rangeInternal an internally represented (opaque) number, specifying
	 *                    the range of random numbers to obtain (from zero to this number minus one)
	 * @returns           string of custom-number digits
	 */
	abstract public function customRandomFromInternalRange( $rangeInternal ) ;

	/**
	 * Split a unicode string into an array of its individual characters.
	 *
	 * (Like the similarly named PHP library function str_split, but with Unicode support.)
	 * @param[in] string  $str string of Unicode characters
	 * @returns           array of single-Unicode-character strings
	 */
	public static function uc_str_split( $str ) {
		return preg_split( '/(?<!^)(?!$)/u', $str ) ;
	}

	/**
	 * Split a string into an array of its individual characters, depending on Unicode property
	 *
	 * This function invokes either the PHP library function str_split or this class's
	 * method uc_str_split(), depending on if this instance was created or initialized
	 * last with the @b $use_uc flag @c true or @c false.
	 *
	 * @param[in] string  $str string of characters
	 * @returns           array of single-character strings
	 */
	protected function str_split( $str ) {
		return $this->_fn['str_split']( $str ) ;
	}

	/**
	 * Get the range of numbers needed for the specified number of custom-number digits.
	 *
	 * This function returns the limit of the range of numbers that can be expressed by the
	 * specified number of digits of the custom number's radix. Numbers can be from zero to
	 * this limit.
	 *
	 * @param[in] int     $nDigits number of custom-number digits
	 * @returns           internally represented limit value
	 */
	abstract protected function _getRangeNeededForCustomDigits( $nDigits ) ;

	/**
	 * Get the number of binary bits needed to represent a number less than the specified limit.
	 *
	 * @param[in] any     $rangeInternal internally represented value of the range limit
	 * @returns           integer number of bits needed to represent numbers in the entire range
	 */
	abstract protected function _getBitsNeededForRangeInternal( $rangeInternal ) ;

	/**
	 * Trim an array, removing least-recently added elements
	 *
	 * The specified array is always appended to, so we simply remove
	 * a "chunk" of elements from the beginning when the array exceeeds
	 * the specified maximum.
	 *
	 * @param[in,out] array $array the array to trim (if needed)
	 * @param[in]     int   $maxCount the maximum number of elements allowed in the array
	 * @param[in]     int   $chunkSize the number of elements in a "chunk"
	 */
	protected function _trim_array_lru( &$array, $maxCount, $chunkSize ) {
		if ( count( $array ) > $maxCount ) {
			$chunkLeft = $chunkSize ;
			// ( We can't use array_splice, as it clobbers the remaining keys. )
			foreach ( array_keys( $array ) as $k ) {
				unset( $array[$k] ) ;
				if ( --$chunkLeft <= 0 ) {
					break ;
				}
			}
		}
	}

	/**
	 * Initialize this class's static properties.
	 * @private
	 *
	 * PHP only allows variable declarations with simple constants, so we have this
	 * function for more complex initialization of statics. Although "public" in
	 * construction, it is usable in this source file, only, immediately after this
	 * class is declared. Any attempt to invoke this method a second time will throw
	 * an ErrorException.
	 */
	public static function _initStatic() {
		if ( !self::$_hexbinChunkSize ) {
			self::$_hexbinChunkSize = strlen(decbin(PHP_INT_MAX))>>2 ;
			self::$_binhexChunkSize = self::$_hexbinChunkSize << 2 ;
			self::$_hexChunkPaddingFormat =       // printf format for hex left zero padding
				'%0' . self::$_hexbinChunkSize . 's' ;
			self::$_binChunkPaddingFormat =      // printf format for binary left zero padding
				'%0' . self::$_binhexChunkSize . 's' ;
		} else {
			throw new ErrorException( sprintf( 'Invalid invocation of %s().', __METHOD__ ) ) ;
		}
	}

	/**
	 * An array of custom-number digits.
	 *
	 * The first element is zero, the second is unity, ...
	 */
	protected $_digitsArray ;

	/**
	 * An associative array that maps custom-number digits to their numerical values.
	 */
	protected $_digitValues ;

	/**
	 * The radix of the custom number.
	 */
	protected $_radix ;

	/**
	 * Custom number ranges by number of digits.
	 *
	 * This is an array that maps the number of digits in a custom number to the internal
	 * representation of the limit for that number as expressed by the number of digits
	 * of the custom number. (e.g. 4 digits in base 10 has a limit of 10000.)
	 */
	protected $_rangeNeededForDigits ;

	/**
	 * Array of number of bits by range of numbers.
	 *
	 * This is an array that maps a range of numbers to the number of bits required to
	 * represent any number in that range. (Actually stored is the limit of the range,
	 * log2(n), which starts at zero and ends at one less than the limit.)
	 */
	protected $_bitsNeededForRange ;

	/**
	 * The maximum number of hexadecimal digits that both decbin(hexdec($hex)) and
	 * dechex(bindec($binary)) can handle
	 */
	protected static $_hexbinChunkSize ;

	/**
	 * printf format to left-zero-fill a binary-digit string to the number of bits that corresponds
	 * to self::$_hexbinChunkSize
	 */
	protected static $_binChunkPaddingFormat ;

	/**
	 * The number of binary digits that corresponds to self::$_hexbinChunkSize
	 */
	protected static $_binhexChunkSize ;

	/**
	 * printf format to left-zero-fill a hex string to the number of hex digits specified by
	 * self::$_hexbinChunkSize
	 */
	protected static $_hexChunkPaddingFormat ;

	/**
	 * Function vector for mapable class functions
	 */
	private $_fn ;

	/**
	 * Boolean indicator if Unicode is supported by this instance (See initialization
	 * parameter $use_uc.)
	 */
	private $_is_using_uc ;

}
// Once-only invocation to initialize static properties
WeirdoCustomDigits::_initStatic() ;

/** @}*/
