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
 * Implementation of WeirdoCustomDigits using the gmp extension for arithmetic
 *
 * @section Requirements
 *  - gmp extension ( See php.net/gmp )
 *
 * @section Limitations
 *  - None more than WeirdoCustomDigits.
 *
 * The "internally represented" format for values of this subclass is the internal value that
 * gmp uses, e.g. the value returned by gmp_init().
 */
class WeirdoCustomDigitsGmp extends WeirdoCustomDigits {

	/**
   * For parameters and semantics, see WeirdoCustomDigits::$maximumValue.
	 *
	 * For this class, the value is null, meaning that there is no limit.
	 */
	public static $maximumValue = null;

  /** For parameters and semantics, see WeirdoCustomDigits::__construct().  */
	public function __construct( $digits = null, $radix = null, $use_uc = true ) {
		// we require gmp
		if ( !function_exists( 'gmp_init' ) ) {
			throw new ErrorException(
				sprintf( 'Error detected by %s(): Required extension missing: gmp.', __METHOD__ )
			);
		}
		parent::__construct( $digits, $radix, $use_uc );
	}

  /** For parameters and semantics, see WeirdoCustomDigits::init().  */
	public function init( $digits = null, $radix = null, $use_uc = true ) {
		parent::init( $digits, $radix, $use_uc );
		// map PHP ints to gmp objects
		$this->_digitValues = array_map(
      function( $v ) {
        return gmp_init( $v );
      },
      $this->_digitValues
      );
		$this->_radix = gmp_init( $this->_radix );
	}

  /** For parameters and semantics, see WeirdoCustomDigits::binFromDecimal().  */
	public static function binFromDecimal( $decimalNumber ) {
    return gmp_strval( gmp_init( $decimalNumber, 10 ), 2 );
  }

  /** For parameters and semantics, see WeirdoCustomDigits::customFromDecimal().  */
	public function customFromDecimal( $decimalNumber, $minCustomDigits=1 ) {
		// gmp implicity converts decimal
		return $this->customFromInternal( $decimalNumber, $minCustomDigits );
	}

  /** For parameters and semantics, see WeirdoCustomDigits::customFromHex().  */
	public function customFromHex( $hexNumber, $minCustomDigits=1 ) {
		// gmp will convert from hex when prefixed with '0x'
		return $this->customFromInternal( "0x$hexNumber", $minCustomDigits );
	}

  /** For parameters and semantics, see WeirdoCustomDigits::customFromInternal().  */
	public function customFromInternal( $internal, $minCustomDigits=1 ) {
		$digits = array();
		while ( gmp_cmp( $internal, 0 ) ) {
			list( $internal, $remainder ) = gmp_div_qr( $internal, $this->_radix );
			$digits[] = $this->_digitsArray[gmp_intval( $remainder )];
		}
		$customNumber = implode( array_reverse( $digits ) );
		if ( count( $digits ) < $minCustomDigits ) {
			$customNumber =
        str_repeat($this->_digitsArray[0], $minCustomDigits - count( $digits ) ) .
        $customNumber
        ;
		}
		return $customNumber;
	}

  /** For parameters and semantics, see WeirdoCustomDigits::customRandomDigits().  */
	public function customRandomDigits( $nDigits ) {
		$range = $this->_getRangeNeededForCustomDigits( (int)$nDigits );
		if ( $range ) {
			return $this->customRandomFromInternalRange( $range );
		}
	}

  /** For parameters and semantics, see WeirdoCustomDigits::customRandomFromInternalRange().  */
	public function customRandomFromInternalRange( $rangeInternal ) {
		$numBits = $this->_getBitsNeededForRangeInternal( $rangeInternal );
		$limit = 100;
		if ( $numBits ) {
			do {
				if ( $limit-- < 0 ) {
					throw new ErrorException(
						sprintf( 'Error detected by %s(): randomization failure ( %u bits; rnd=0x%s; range=0x%s )', __METHOD__,
						  $numBits,
							gmp_strval( $random, 16 ),
							gmp_strval( $rangeInternal, 16 )
					) );
				}
				$random = gmp_init( $this->hexFromRandomBits( $numBits ), 16 );
			} while ( gmp_cmp( $random, $rangeInternal ) >= 0 ) ;
			$random = $this->customFromInternal( $random );
		}
		else {
			$random = null;
		}
		return $random;
	}

  /** For parameters and semantics, see WeirdoCustomDigits::decimalFromBin(). */
	public static function decimalFromBin( $binNumber ) {
    return gmp_strval( gmp_init( $binNumber, 2 ), 10 );
  }

  /** For parameters and semantics, see WeirdoCustomDigits::decimalFromHex(). */
	public static function decimalFromHex( $hexNumber ) {
    return gmp_strval( gmp_init( $hexNumber, 16 ), 10 );
  }

  /** For parameters and semantics, see WeirdoCustomDigits::decimalFromCustom(). */
	public function decimalFromCustom( $customNumber ) {
		return gmp_strval( $this->internalFromCustom( $customNumber ), 10 );
	}

  /** For parameters and semantics, see WeirdoCustomDigits::decimalFromInternal(). */
	public function decimalFromInternal( $internal ) {
		return gmp_strval( $internal, 10 );
	}

  /** For parameters and semantics, see WeirdoCustomDigits::hexFromDecimal(). */
	public static function hexFromDecimal( $decimalNumber ) {
    return gmp_strval( gmp_init( $decimalNumber, 10 ), 16 );
	}

  /** For parameters and semantics, see WeirdoCustomDigits::hexFromCustom(). */
	public function hexFromCustom( $customNumber ) {
		return gmp_strval( $this->internalFromCustom( $customNumber ), 16 );
	}

  /** For parameters and semantics, see WeirdoCustomDigits::hexFromInternal(). */
	public function hexFromInternal( $internal ) {
		return gmp_strval( $internal, 16 );
	}

  /** For parameters and semantics, see WeirdoCustomDigits::internalFromCustom(). */
	public function internalFromCustom( $customNumber, $minCustomDigits=1 ) {
		$sum = gmp_init( 0 );
		$digits = array_reverse( $this->str_split( $customNumber ) );
		while ( count( $digits ) ) {
			$key = "#" . array_pop( $digits );
			if ( !isset( $this->_digitValues[$key] ) ) {
			  throw new ErrorException( 'Error detected by ' . __METHOD__ . '(): unrecognized digit ( ' . substr( $key, 1 ) . ' ).' );
			}
			$sum = gmp_add( gmp_mul( $sum, $this->_radix ), $this->_digitValues[$key] );
		}
		return $sum;
	}

  /** For parameters and semantics, see WeirdoCustomDigits::internalFromDecimal(). */
	public function internalFromDecimal( $decimalNumber ) {
		return gmp_init( $decimalNumber, 10 );
	}

  /** For parameters and semantics, see WeirdoCustomDigits::internalFromHex(). */
	public function internalFromHex( $hexNumber ) {
		return gmp_init( $hexNumber, 16 );
	}

  /** For parameters and semantics, see WeirdoCustomDigits::_getRangeNeededForCustomDigits(). */
  protected function _getRangeNeededForCustomDigits( $nDigits ) {
    if ( !array_key_exists( $nDigits, $this->_rangeNeededForDigits ) ) {
      $this->_rangeNeededForDigits[$nDigits] = gmp_pow( $this->_radix, $nDigits ) ;
      self::_trim_array_lru( $this->_rangeNeededForDigits, 100, 10 );
    }
    return $this->_rangeNeededForDigits[$nDigits];
  }

  /** For parameters and semantics, see WeirdoCustomDigits::_getBitsNeededForRangeInternal(). */
	protected function _getBitsNeededForRangeInternal( $rangeInternal ) {
		$key = "#" . gmp_strval( $rangeInternal, 62 );
		if ( !array_key_exists( $key, $this->_bitsNeededForRange ) ) {
      $this->_bitsNeededForRange[$key] = strlen( gmp_strval( gmp_sub( $rangeInternal, 1 ), 2 ) );
      self::_trim_array_lru( $this->_bitsNeededForRange, 100, 10 );
    }
		return $this->_bitsNeededForRange[$key];
	}

}

/** @}*/
