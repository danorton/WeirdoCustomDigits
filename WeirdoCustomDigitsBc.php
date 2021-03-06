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
 * **GPL v3**\n
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * \n\n
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * \n\n
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * @}
 *
 */

require_once( 'WeirdoCustomDigits.php' ) ;

/**
 * Implementation of WeirdoCustomDigits using the bc extension for arithmetic
 *
 * @section Requirements
 *  - bc extension ( See http://php.net/bc )
 *
 * @section Limitations
 *  - None more than WeirdoCustomDigits.
 *
 * The "internally represented" format for values of this subclass is a string of decimal digits.
 */
class WeirdoCustomDigitsBc extends WeirdoCustomDigits {

	/**
	 * For semantics, see WeirdoCustomDigits::$maximumValue.
	 *
	 * For this class, the value is null, indicating that there is no limit.
	 */
	public static $maximumValue = null ;

	/**
	 * For parameters and semantics, see WeirdoCustomDigits::__construct().
	 *
	 * This subclass method throws an error if the bc extension is not available.
	 */
	public function __construct( $digits = null, $radix = null, $use_uc = true ) {
		// we require bc
		if ( !function_exists( 'bcdiv' ) ) {
			throw new ErrorException(
				sprintf( 'Error detected by %s(): Required extension missing: bc.', __METHOD__ )
			) ;
		}
		parent::__construct( $digits, $radix, $use_uc ) ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::binFromDecimal(). */
	public static function binFromDecimal( $decimalNumber ) {

		$binChunks = array() ;
		do {
			$chunk = decbin( bcmod( $decimalNumber, self::$_dechexDivisor ) ) ;
			$decimalNumber = bcdiv( $decimalNumber, self::$_dechexDivisor, 0 ) ;
			if ( $decimalNumber !== '0' ) {
				$chunk = sprintf( self::$_binChunkPaddingFormat, $chunk ) ;
			}
			$binChunks[] = $chunk ;
		} while ( $decimalNumber !== '0' ) ;

		return implode( '', array_reverse( $binChunks ) ) ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::customFromDecimal(). */
	public function customFromDecimal( $decimalNumber, $minCustomDigits=1 ) {
		// the internal format for bc accepts decimal strings directly
		return $this->customFromInternal( $decimalNumber, $minCustomDigits ) ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::customFromHex(). */
	public function customFromHex( $hexNumber, $minCustomDigits=1 ) {
		// convert hex numbers to decimal for bc
		return $this->customFromInternal( $this->decimalFromHex( $hexNumber ), $minCustomDigits ) ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::customFromInternal(). */
	public function customFromInternal( $internal, $minCustomDigits=1 ) {
		$digits = array() ;
		while ( $internal ) {
			$digits[] = $this->_digitsArray[intval( bcmod( $internal, $this->_radix ) )] ;
			$internal = bcdiv( $internal, $this->_radix, 0 ) ;
		}
		$customNumber = implode( array_reverse( $digits ) ) ;
		if ( count( $digits ) < $minCustomDigits ) {
			$customNumber = str_repeat( $this->_digitsArray[0], $minCustomDigits - count( $digits ) ) . $customNumber ;
		}
		return $customNumber ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::customRandomDigits(). */
	public function customRandomDigits( $nDigits, $allowOverflow = false ) {
		$result = $this->customRandomFromInternalRange(
			$this->_getRangeNeededForCustomDigits( (int)$nDigits )
		);
		$needDigits = $nDigits - strlen( $result ) ;
		if ( $needDigits > 0 ) {
			$result = str_repeat( $this->_digitsArray[0], $needDigits ) . $result ;
		}
		return $result ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::customRandomFromInternalRange(). */
	public function customRandomFromInternalRange( $rangeInternal ) {
		$numBits = $this->_getBitsNeededForRangeInternal( $rangeInternal ) ;
		if ( $numBits ) {
			$limit = 100 ;
			do {
				if ( $limit-- < 0 ) {
					throw new ErrorException(
						sprintf( 'Error detected by %s(): randomization failure', __METHOD__ )
					) ;
				}
				$random = self::decimalFromHex( $this->hexFromRandomBits( $numBits ) ) ;
			} while ( bccomp( $random, $rangeInternal ) >= 0 ) ;
			$random = $this->customFromInternal( $random ) ;
		} else {
			$random = null ;
		}
		return $random ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::decimalFromBin(). */
	public static function decimalFromBin( $binNumber ) {

		// trim leading zeros
		if ( $binNumber[0] === '0' ) {
			$binNumber = ltrim( $binNumber, '0' ) ;
			if ( $binNumber === '' ) {
				return '0'; // quick exit for the degenerate case
			}
		}

		// split the number into chunks that we can pass to bindec()
		$binChunks = array_reverse ( str_split( $binNumber, self::$_bindecChunkSize ) ) ;

		// convert one chunk at a time
		$sum = 0 ;
		do {
			$chunk = array_pop( $binChunks ) ;
			$decimalChunk = bindec( $chunk ) ;
			// if the result is a floating point number, we use sprintf to extract the integer significand as a string
			if (is_float( $decimalChunk ) ) {
				$decimalChunk = sprintf('%.0f', $decimalChunk) ;
			}
			$sum = bcadd( bcmul( $sum, 1 << strlen( $chunk ), 0 ), $decimalChunk, 0 ) ;
		} while ( count( $binChunks ) ) ;

		return $sum ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::decimalFromHex(). */
	public static function decimalFromHex( $hexNumber ) {

		// trim leading zeros
		if ( $hexNumber[0] === '0' ) {
			$hexNumber = ltrim( $hexNumber, '0' ) ;
			if ( $hexNumber === '' ) {
				return '0'; // quick exit for the degenerate case
			}
		}

		// split the number into chunks that we can pass to hexdec()
		$hexChunks = array_reverse ( str_split( $hexNumber, self::$_hexdecChunkSize ) ) ;

		// convert one chunk at a time
		$sum = 0 ;
		do {
			$chunk = array_pop( $hexChunks ) ;
			$decimalChunk = hexdec( $chunk ) ;
			// if the result is a floating point number, we use sprintf to extract the integer significand as a string
			if (is_float( $decimalChunk ) ) {
				$decimalChunk = sprintf('%.0f', $decimalChunk) ;
			}
			$sum = bcadd( bcmul( $sum, 1 << ( strlen( $chunk ) << 2 ), 0 ), $decimalChunk, 0 ) ;
		} while ( count( $hexChunks ) ) ;

		return $sum ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::decimalFromCustom(). */
	public function decimalFromCustom( $customNumber ) {
		// our internal format is decimal
		return $this->internalFromCustom( $customNumber ) ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::decimalFromInternal(). */
	public function decimalFromInternal( $internal ) {
		// our internal format is decimal
		return $internal ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::hexFromDecimal(). */
	public static function hexFromDecimal( $decimalNumber ) {

		$hexChunks = array() ;
		do {
			$chunk = dechex( bcmod( $decimalNumber, self::$_dechexDivisor ) ) ;
			$decimalNumber = bcdiv( $decimalNumber, self::$_dechexDivisor, 0 ) ;
			if ( $decimalNumber !== '0' ) {
				$chunk = sprintf( self::$_hexChunkPaddingFormat, $chunk ) ;
			}
			$hexChunks[] = $chunk ;
		} while ( $decimalNumber !== '0' ) ;

		return implode( '', array_reverse( $hexChunks ) ) ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::hexFromCustom(). */
	public function hexFromCustom( $customNumber ) {
		return self::hexFromDecimal( $this->internalFromCustom( $customNumber ) ) ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::hexFromInternal(). */
	public function hexFromInternal( $internal ) {
		return self::hexFromDecimal( $internal ) ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::internalFromCustom(). */
	public function internalFromCustom( $customNumber ) {
		$sum = 0 ;
		$digits = array_reverse( $this->str_split( $customNumber ) ) ;
		while ( count( $digits ) ) {
			$key = "#" . array_pop( $digits ) ;
			if ( !isset( $this->_digitValues[$key] ) ) {
				throw new ErrorException(
					sprintf('Error detected by %s(): unrecognized digit ( %s ).',
						__METHOD__,
						substr( $key, 1 )
					)
				) ;
			}
			$sum = bcadd( bcmul( $sum, $this->_radix, 0 ), $this->_digitValues[$key], 0 ) ;
		}
		return $sum ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::internalFromDecimal(). */
	public function internalFromDecimal( $decimalNumber ) {
		// our internal format is a decimal string
		return "$decimalNumber" ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::internalFromHex(). */
	public function internalFromHex( $hexNumber ) {
		return self::decimalFromHex( $hexNumber ) ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::_getBitsNeededForRangeInternal().
	 * @private
	 */
	protected function _getBitsNeededForRangeInternal( $rangeInternal ) {
		if ( !array_key_exists( $rangeInternal, $this->_bitsNeededForRange ) ) {
			$this->_bitsNeededForRange[$rangeInternal] = strlen( self::binFromDecimal( bcsub( $rangeInternal, 1, 0 ) ) ) ;
			self::_trim_array_lru( $this->_bitsNeededForRange, 100, 10 ) ;
		}
		return $this->_bitsNeededForRange[$rangeInternal] ;
	}

	/** For parameters and semantics, see WeirdoCustomDigits::_getRangeNeededForCustomDigits().
	 * @private
	 */
	protected function _getRangeNeededForCustomDigits( $nDigits ) {
		if ( !array_key_exists( $nDigits, $this->_rangeNeededForDigits ) ) {
			$this->_rangeNeededForDigits[$nDigits] = bcpow( $this->_radix, $nDigits ) ;
			self::_trim_array_lru( $this->_rangeNeededForDigits, 100, 10 ) ;
		}
		return $this->_rangeNeededForDigits[$nDigits] ;
	}

	/** For semantics, see WeirdoCustomDigits::_initStatic().
	 * @private
	 */
	public static function _initStatic() {
		if ( !self::$_hexdecChunkSize) {
			self::$_hexdecChunkSize = min( ( PHP_INT_SIZE << 3 ) - 1, self::$phpIntegerMaxBits ) >> 2 ;
			self::$_dechexDivisor = sprintf( '%.0f', hexdec( '1' . str_repeat( '0', self::$_hexdecChunkSize ) ) ) ;
			self::$_hexChunkPaddingFormat = '%0' . self::$_hexdecChunkSize . 's' ;
			self::$_bindecChunkSize = self::$_hexdecChunkSize << 2 ;
			self::$_binChunkPaddingFormat = '%0' . self::$_bindecChunkSize . 's' ;
		} else {
			throw new ErrorException( sprintf( 'Invalid invocation of %s().', __METHOD__ ) ) ;
		}
	}

	/** Divisor for extracting digits for decimal-to-hex conversion. */
	private static $_dechexDivisor ;

	/** This int represents the number of whole hexadecimal digits that hexdec() can handle. */
	private static $_hexdecChunkSize ;

	/** This int represents the number of bits that corresponds to _hexdecChunkSize. */
	private static $_bindecChunkSize ;

	/**
	 * printf format to left-zero-fill a hex string to the number of hex digits we extract
	 * with self::$_dechexDivisor
	 */
	protected static $_hexChunkPaddingFormat ;

}
// Once-only invocation to initialize static properties
WeirdoCustomDigitsBc::_initStatic() ;

/** @}*/
