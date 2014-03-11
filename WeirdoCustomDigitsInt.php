<?php
/**
 * @addtogroup WeirdoCustomDigits
 * @{
 *
 * @file
 * @{
 * @copyright © 2014 Daniel Norton d/b/a WeirdoSoft - www.weirdosoft.com
 *
 * @section License
 *    - <b>CC BY-SA 3.0</b> -
 *   This work is licensed under the Creative Commons
 *   Attribution-ShareAlike 3.0 Unported License. To view a copy of
 *   this license, visit http://creativecommons.org/licenses/by-sa/3.0/
 *   or send a letter to Creative Commons, 444 Castro Street, Suite 900,
 *   Mountain View, California, 94041, USA.
 * @}
 *
 */

require_once( __DIR__ . '/WeirdoCustomDigits.php' );

/**
 * Implementation of WeirdoCustomDigits using built-in integer arithmetic
 *
 * @section Requirements
 *  - None more than WeirdoCustomDigits.
 *
 * @section Limitations
 *  - range limited by the smaller of PHP_INT_MAX and the maximum integer supported by
 *    floating point arithmetic.
 *
 * The "internally represented" format for values of this subclass is a PHP int.
 */
class WeirdoCustomDigitsInt extends WeirdoCustomDigits {

	/**
	 * The maximum value of a number
	 *
	 * For this class, the value is the lesser of PHP_INT_MAX and pow( 2, self::$phpIntegerMaxBits )-1
	 */
	public static $maximumValue ;

	/** For parameters and semantics, see WeirdoCustomDigits::binFromDecimal(). */
	public static function binFromDecimal( $decimalNumber ) {
		return decbin( $decimalNumber );
	}

	/** For parameters and semantics, see WeirdoCustomDigits::customFromDecimal(). */
	public function customFromDecimal( $decimalNumber, $minCustomDigits=1 ) {
		return $this->customFromInternal( $decimalNumber, $minCustomDigits );
	}

	/** For parameters and semantics, see WeirdoCustomDigits::customFromHex(). */
	public function customFromHex( $hexNumber, $minCustomDigits=1 ) {
		return $this->customFromInternal( hexdec( $hexNumber ), $minCustomDigits );
	}

	/** For parameters and semantics, see WeirdoCustomDigits::customFromInternal(). */
	public function customFromInternal( $internal, $minCustomDigits=1 ) {
		$digits = array();
		while ( $internal ) {
			$digits[] = $this->_digitsArray[$internal % $this->_radix];
			$internal = intval( $internal / $this->_radix ) ;
		}
		$customNumber = implode( array_reverse( $digits ) );
		if ( count( $digits ) < $minCustomDigits ) {
			$customNumber = str_repeat( $this->_digitsArray[0], $minCustomDigits - count( $digits ) ) . $customNumber;
		}
		return $customNumber;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::decimalFromHex(). */
	public static function decimalFromHex( $hexNumber ) {
		return hexdec( $hexNumber );
	}

	/** For parameters and semantics, see WeirdoCustomDigits::decimalFromBin(). */
	public static function decimalFromBin( $binNumber ) {
		return bindec( $binNumber );
	}

	/** For parameters and semantics, see WeirdoCustomDigits::decimalFromCustom(). */
	public function decimalFromCustom( $customNumber ) {
		return $this->internalFromCustom( $customNumber );
	}

	/** For parameters and semantics, see WeirdoCustomDigits::decimalFromInternal(). */
	public function decimalFromInternal( $internal ) {
		return $internal;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::hexFromDecimal(). */
	public static function hexFromDecimal( $decimalNumber ) {
		return dechex( $decimalNumber );
	}

	/** For parameters and semantics, see WeirdoCustomDigits::hexFromCustom(). */
	public function hexFromCustom( $customNumber ) {
		return dechex( $this->internalFromCustom( $customNumber ) );
	}

	/** For parameters and semantics, see WeirdoCustomDigits::hexFromInternal(). */
	public function hexFromInternal( $internal ) {
		return dechex( $internal );
	}

	/** For parameters and semantics, see WeirdoCustomDigits::internalFromCustom(). */
	public function internalFromCustom( $customNumber ) {
		$sum = 0;
		$digits = array_reverse( $this->str_split( $customNumber ) );
		while ( count( $digits ) ) {
			$key = "#" . array_pop( $digits );
			if ( !isset( $this->_digitValues[$key] ) ) {
				throw new ErrorException( 'Error detected by ' . __METHOD__ . '(): unrecognized digit ( ' . substr( $key, 1 ) . ' ).' );
			}
			$sum = intval( intval( $sum * $this->_radix ) + $this->_digitValues[$key] );
		}
		return $sum;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::internalFromDecimal(). */
	public function internalFromDecimal( $decimalNumber ) {
		return intval( $decimalNumber );
	}

	/** For parameters and semantics, see WeirdoCustomDigits::internalFromHex(). */
	public function internalFromHex( $hexNumber ) {
		return hexdec( $hexNumber );
	}

	/** For parameters and semantics, see WeirdoCustomDigits::customRandomDigits(). */
	public function customRandomDigits( $nDigits ) {
		$range = $this->_getRangeNeededForCustomDigits( (int)$nDigits );
		if ( $range ) {
			return $this->customRandomFromInternalRange( $range );
		}
	}

	/** For parameters and semantics, see WeirdoCustomDigits::customRandomFromInternalRange(). */
	public function customRandomFromInternalRange( $rangeInternal ) {
		$rangeInternal = (int)$rangeInternal;
		$numBits = $this->_getBitsNeededForRangeInternal( $rangeInternal );
		if ( $numBits ) {
			do {
				$random = $this->hexFromRandomBits( $numBits );
			} while ( hexdec( $random ) >= $rangeInternal ) ;
			$random = $this->customFromHex( $random );
		} else {
			$random = null;
		}
		return $random;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::_getBitsNeededForRangeInternal().
	 * @private
	 */
	protected function _getBitsNeededForRangeInternal( $rangeInternal ) {
		if ( !array_key_exists( $rangeInternal, $this->_bitsNeededForRange ) ) {
			$this->_bitsNeededForRange[$rangeInternal] =
				( ( $rangeInternal > 0 ) && ( $rangeInternal <= static::$maximumValue ) )
					? strlen( decbin( $rangeInternal - 1 ) )
					: null;
			self::_trim_array_lru( $this->_bitsNeededForRange, 100, 10 );
		}
		return $this->_bitsNeededForRange[$rangeInternal];
	}

	/** For parameters and semantics, see WeirdoCustomDigits::_getRangeNeededForCustomDigits().
	 * @private
	 */
	protected function _getRangeNeededForCustomDigits( $nDigitsDecimal ) {
		if ( !array_key_exists( $nDigitsDecimal, $this->_rangeNeededForDigits ) ) {
			$range = pow( $this->_radix, $nDigitsDecimal );
			if ( $range > static::$maximumValue ) {
				$range = null;
			}
			$this->_rangeNeededForDigits[$nDigitsDecimal] = $range;
			self::_trim_array_lru( $this->_rangeNeededForDigits, 100, 10 );
		}
		return $this->_rangeNeededForDigits[$nDigitsDecimal];
	}

	/** For semantics, see WeirdoCustomDigits::_initStatic().
	 * @private
	 */
	public static function _initStatic() {
		if ( !self::$maximumValue ) {
			self::$maximumValue = (int) min ( PHP_INT_MAX, pow( 2, self::$phpIntegerMaxBits ) - 1 ) ;
		} else {
			throw new ErrorException( sprintf( 'Invalid invocation of %s().', __METHOD__ ) );
		}
	}

}
// Once-only invocation to initialize static properties
WeirdoCustomDigitsInt::_initStatic();

/** @}*/
