<?php
// ex: ts=2 sw=2 noet ai:
//
// WeirdoCustomDigits - Perform radix conversion
//
// © 2014 Daniel Norton d/b/a WeirdoSoft - www.weirdosoft.com
//
// License: CC BY-SA 3.0
//   This work is licensed under the Creative Commons
//   Attribution-ShareAlike 3.0 Unported License. To view a copy of
//   this license, visit http://creativecommons.org/licenses/by-sa/3.0/
//   or send a letter to Creative Commons, 444 Castro Street, Suite 900,
//   Mountain View, California, 94041, USA.
//
// REQUIREMENTS:
//  - PHP 5.3 or later
// 	- if using multi-byte digits or UTF-8 digits, PCRE UTF-8 support is required
//
//

require_once('WeirdoCustomDigits.php');

class WeirdoCustomDigitsBc extends WeirdoCustomDigits {

	public function __construct($digits = NULL, $radix = NULL, $use_uc = true) {
		// we require bc
		if (!function_exists('bcdiv')) {
			throw new ErrorException('Error detected by ' . __METHOD__ . '(): Required extension missing: bc');
		}
		parent::__construct($digits, $radix, $use_uc);
	}

	public function customFromDecimal($decimalNumber, $minCustomDigits=1 ) {
		// the internal format for bc accepts decimal strings directly
		return $this->customFromInternal($decimalNumber, $minCustomDigits);
	}

	public function customFromHex($hexNumber, $minCustomDigits=1 ) {
		// convert hex numbers to decimal for bc
		return $this->customFromInternal(hexdec($hexNumber), $minCustomDigits);
	}

	public function customFromInternal($internal, $minCustomDigits=1) {
		$digits = array();
		while ($internal) {
			$digits[] = $this->_digitsArray[intval(bcmod($internal, $this->_radix))];
			$internal = bcdiv($internal, $this->_radix, 0) ;
		}
		$customNumber = implode(array_reverse($digits));
		if (count($digits) < $minCustomDigits) {
			$customNumber = str_repeat($this->_digitsArray[0], $minCustomDigits - count($digits)) . $customNumber;
		}
		return $customNumber;
	}

	public function customRandomDigits($nDigits) {
		$range = $this->_getRangeNeededForCustomDigits((int)$nDigits);
		if ($range) {
			return $this->customRandomFromInternalRange($range);
		}
	}

	public function customRandomFromInternalRange($rangeInternal) {
		$numBits = $this->_getBitsNeededForRangeInternal($rangeInternal);
		if ($numBits) {
		  $limit = 100;
			do {
				if ($limit-- < 0) {
					throw new ErrorException(
						sprintf('Error detected by %s(): randomization failure (%u bits; rnd=0x%s; range=0x%s)', __METHOD__,
						  $numBits,
							dechex($random),
							dechex($rangeInternal)
					));
				}
				$random = static::hexdec($this->hexFromRandomBits($numBits));
			} while (bccomp($random,$rangeInternal) >= 0) ;
			$random = $this->customFromInternal($random);
		}
		else {
			$random = NULL;
		}
		return $random;
	}

	public static function hexdec($hexNumber) {
		// trim leading zeros
		if ($hexNumber[0] === '0') {
			$hexNumber = ltrim($hexNumber,'0');
			if ($hexNumber === '') {
				return '0';
			}
		}

		$sum = 0;
		$hexDigits = array_reverse(str_split($hexNumber, static::$_hexdecChunkSize));
		do {
			$chunk = array_pop($hexDigits);
			$sum = bcadd(bcmul($sum, 1 << (strlen($chunk)<<2),0), hexdec($chunk),0);
		} while (count($hexDigits));
		return $sum;
	}

	public function decimalFromCustom($customNumber) {
		// our internal format is decimal
		return $this->internalFromCustom($customNumber);
	}

	public function decimalFromInternal($internal) {
		// our internal format is decimal
		return $internal;
	}

	public static function dechex($decimalNumber) {
		$hexDigits = array();
		do {
			$hexDigits[] = dechex(bcmod($decimalNumber, static::$_dechexDivisor));
			$decimalNumber = bcdiv($decimalNumber, static::$_dechexDivisor, 0);
		} while ($decimalNumber != 0);
		return implode('',array_reverse($hexDigits));
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
			$sum = bcadd(bcmul($sum, $this->_radix, 0), $this->_digitValues[$key], 0);
		}
		return $sum;
	}

	public function internalFromDecimal($decimalNumber) {
		// our internal format is a decimal string
		return "$decimalNumber";
	}

	public function internalFromHex($hexNumber) {
		return hexdec($hexNumber);
	}

	protected function _getBitsNeededForRangeInternal($rangeInternal) {
		if (!array_key_exists($rangeInternal,$this->_bitsNeededForRange)) {
      $this->_bitsNeededForRange[$rangeInternal] = strlen(static::decbin(bcsub($rangeInternal,1,0)));
      static::_trim_array_lru($this->_bitsNeededForRange,100,10);
    }
		return $this->_bitsNeededForRange[$rangeInternal];
	}

  protected function _getRangeNeededForCustomDigits($nDigits) {
    if (!array_key_exists($nDigits,$this->_rangeNeededForDigits)) {
      $this->_rangeNeededForDigits[$nDigits] = bcpow($this->_radix,$nDigits) ;
      static::_trim_array_lru($this->_rangeNeededForDigits,100,10);
    }
    return $this->_rangeNeededForDigits[$nDigits];
  }

	// although public, this method may only be called immediately after declaring the class
	public static function _initStatic() {
		if (!self::$_dechexDivisor) {
			self::$_dechexDivisor = (int) hexdec('1' . str_repeat('0',self::$_hexdecChunkSize));
		}
		else {
			throw new ErrorException(sprintf('Invalid invocation of %s().', __METHOD__));
		}
	}

	private static $_dechexDivisor ;
}
WeirdoCustomDigitsBc::_initStatic();

