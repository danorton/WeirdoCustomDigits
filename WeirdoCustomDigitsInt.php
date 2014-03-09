<?php
// ex: ts=2 sw=2 noet ai:
//
// WeirdoCustomDigits - Perform radix conversion
//
// © 2014 Daniel Norton d/b/a WeirdoSoft - www.weirdosoft.com
//
// License: CC BY-SA 3.0
//	 This work is licensed under the Creative Commons
//	 Attribution-ShareAlike 3.0 Unported License. To view a copy of
//	 this license, visit http://creativecommons.org/licenses/by-sa/3.0/
//	 or send a letter to Creative Commons, 444 Castro Street, Suite 900,
//	 Mountain View, California, 94041, USA.
//
// REQUIREMENTS:
//	- PHP 5.3 or later
//	- if using multi-byte digits or UTF-8 digits, PCRE UTF-8 support is required
//
// LIMITATIONS:
//	- range is 0...min(0x07ffffffffffffff,PHP_INT_MAX)
//

require_once(__DIR__ . '/WeirdoCustomDigits.php');

class WeirdoCustomDigitsInt extends WeirdoCustomDigits {

	public function __construct($digits = NULL, $radix = NULL, $use_uc = true) {
		parent::__construct($digits, $radix, $use_uc);
	}

	public function customFromDecimal($decimalNumber, $minCustomDigits=1 ) {
		return $this->customFromInternal($decimalNumber, $minCustomDigits);
	}

	public function customFromHex($hexNumber, $minCustomDigits=1 ) {
		return $this->customFromInternal(hexdec($hexNumber), $minCustomDigits);
	}

	public function customFromInternal($internal, $minCustomDigits=1) {
		$digits = array();
		while ($internal) {
			$digits[] = $this->_digitsArray[$internal % $this->_radix];
			$internal = intval($internal / $this->_radix) ;
		}
		$customNumber = implode(array_reverse($digits));
		if (count($digits) < $minCustomDigits) {
			$customNumber = str_repeat($this->_digitsArray[0], $minCustomDigits - count($digits)) . $customNumber;
		}
		return $customNumber;
	}

	public static function hexdec($hexNumber) {
		return hexdec($hexNumber);
	}

	public function decimalFromCustom($customNumber) {
		return $this->internalFromCustom($customNumber);
	}

	public function decimalFromInternal($internal) {
		return $internal;
	}

	public static function dechex($decimalNumber) {
		return dechex($decimalNumber);
	}

	public function hexFromCustom($customNumber) {
		return dechex($this->internalFromCustom($customNumber));
	}

	public function hexFromInternal($internal) {
		return dechex($internal);
	}

	public function internalFromCustom($customNumber) {
		$sum = 0;
		$digits = array_reverse($this->str_split($customNumber));
		while (count($digits)) {
			$key = "#" . array_pop($digits);
			if (!isset($this->_digitValues[$key])) {
				throw new ErrorException('Error detected by ' . __METHOD__ . '(): unrecognized digit (' . substr($key,1) . ').');
			}
			$sum = intval(intval($sum * $this->_radix) + $this->_digitValues[$key]);
		}
		return $sum;
	}

	public function internalFromDecimal($decimalNumber) {
		return intval($decimalNumber);
	}

	public function internalFromHex($hexNumber) {
		return hexdec($hexNumber);
	}

	public function customRandomDigits($nDigits) {
		$range = $this->_getRangeNeededForCustomDigits((int)$nDigits);
		if ($range) {
			return $this->customRandomFromInternalRange($range);
		}
	}

	public function customRandomFromInternalRange($rangeInternal) {
		$rangeInternal = (int) $rangeInternal;
		$numBits = $this->_getBitsNeededForRangeInternal($rangeInternal);
		if ($numBits) {
			do {
				$random = $this->hexFromRandomBits($numBits);
			} while (hexdec($random) >= $rangeInternal) ;
			$random = $this->customFromHex($random);
		}
		else {
			$random = NULL;
		}
		return $random;
	}

	protected function _getBitsNeededForRangeInternal($rangeInternal) {
		if (!array_key_exists($rangeInternal,$this->_bitsNeededForRange)) {
			$this->_bitsNeededForRange[$rangeInternal] =
				(($rangeInternal > 0) && ($rangeInternal <= static::$maximumValue))
					? strlen(decbin($rangeInternal-1))
					: NULL;
			self::_trim_array_lru($this->_bitsNeededForRange,100,10);
		}
		return $this->_bitsNeededForRange[$rangeInternal];
	}

	protected function _getRangeNeededForCustomDigits($nDigitsDecimal) {
		if (!array_key_exists($nDigitsDecimal,$this->_rangeNeededForDigits)) {
			$range = pow($this->_radix,$nDigitsDecimal);
			if ($range > static::$maximumValue) $range = NULL;
			$this->_rangeNeededForDigits[$nDigitsDecimal] = $range;
			self::_trim_array_lru($this->_rangeNeededForDigits,100,10);
		}
		return $this->_rangeNeededForDigits[$nDigitsDecimal];
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
		if ( !static::$maximumValue ) {
		  static::$maximumValue = min(0x07ffffffffffffff,PHP_INT_MAX);
		} else {
			throw new ErrorException( sprintf( 'Invalid invocation of %s().', __METHOD__ ) );
		}
	}

}
WeirdoCustomDigitsInt::_initStatic();
