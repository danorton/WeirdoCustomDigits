#!/usr/bin/php
<?php
// ex: ts=2 sw=2 noet ai:
//
// WeirdoCustomDigits - Arbitrary digit/radix conversion
//
// Unit tests
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

function getException($context,$fn) {
	$exception = false;
	try {
		$fn($context);
	}
	catch (Exception $e) {
		$exception = $e;
	}
	return $exception;
}

function get_rand() {
  if (PHP_INT_MAX > mt_getrandmax()) {
    return (mt_rand() << 31 | mt_rand());
  }
	return mt_rand();
}

function error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
	printf("FAIL: ERROR %u at %s:%u: %s\n", $errno, $errfile, $errline, $errstr);
	exit(1);
}
set_error_handler('error_handler');

// generate some random numbers
//*///
require_once(__DIR__ . '/../WeirdoCustomDigitsInt.php');
printf("WeirdoCustomDigitsInt::\$maximumValue=%s\n",WeirdoCustomDigitsInt::$maximumValue);
$randomInts = array(0);
for ( $i = 0; $i<10000; $i++) {
	$randomInts[] = get_rand() & WeirdoCustomDigitsInt::$maximumValue;
}
$randomInts[] = WeirdoCustomDigitsInt::$maximumValue;

$randomDecimalStrings = array('0');
for ( $i = 0; $i<10000; $i++) {
	$randomDecimalStrings[] = get_rand() . get_rand() . get_rand() . get_rand() . get_rand();
}
$randomDecimalStrings[] = PHP_INT_MAX . PHP_INT_MAX . PHP_INT_MAX;
//*///

$failures = array();
$customResults = array();

foreach (array('Int', 'Gmp', 'Bc') as $mathType) {
//foreach (array('Int', 'Bc') as $mathType) {
//foreach (array('Bc', 'Int') as $mathType) {
	echo "===== mathType=$mathType ======\n";
	$className = "WeirdoCustomDigits$mathType";
	echo "===== class=$className ======\n";
	require_once(__DIR__ . "/../$className.php");

	if ($className::$maximumValue !== null) {
		$maximumInteger = $className::$maximumValue;
		printf("%s::\$maximumValue=0x%016X\n", $className, $maximumInteger);
	} else {
		$maximumInteger = PHP_INT_MAX;
	}
	
	try {
		$wrdx10 = new $className();
		$wrdx16 = new $className();
		$wrdxai = new $className();
		$wrdx51 = new $className();
		$wrdxRoman = new $className();
	}	catch (ErrorException $e) {
		$msg = $e->getMessage();	
	  $failures[] = $msg;
		printf("ERROR: %s\n", $msg);
		continue;
	}
	
	$wrdx10->init('0123456789');
	if (!getException($wrdx10, function ($o) { $o->validateCustomNumber('0x99'); })) {
		throw new ErrorException('Unit test: internalFromDecimal() accepted invalid digit');
	}
	$wrdx16->init('0123456789abcdef');
	if (!getException($wrdx16, function ($o) { $o->validateCustomNumber('10AB'); })) {
		throw new ErrorException('Unit test: internalFromDecimal() accepted invalid digit');
	}
	$wrdxai->init(WeirdoCustomDigits::DIGITS_10_ARABIC_EAST);
	$wrdx51->init(WeirdoCustomDigits::DIGITS_51_READABLE);
	$wrdxRoman->init(array('0','I','II','III','IV','V','VI','VII','VIII','IX','X'));

	if (isset($argv[1]) && $argv[1]) {
		var_dump($argv[1]);
		$wrdx51->validateCustomNumber($argv[1]);
		$number = $wrdx51->customFromDecimal($argv[1]);
		var_dump($number);
		var_dump($wrdx51->decimalFromCustom($number));
		echo "-----\n";
	}

	assert($wrdx10->decimalFromCustom(64) == 64);
	assert($wrdx51->decimalFromCustom(64) == 5*51 + 4);
	assert($wrdxai->decimalFromCustom('١٠١') == 101);
	assert($wrdx10->customFromDecimal(0x4597f740) == 0x4597f740);
	assert($wrdx16->customFromDecimal(0x4597f740) == '4597f740');
	assert($wrdx51->customFromDecimal(0x4597f740) == '3GmYeH');
	assert($wrdx51->customFromDecimal('1167587136') == '3GmYeH');
	assert($wrdx51->customFromHex('4597f740') == '3GmYeH');

	$number = $wrdx51->customFromDecimal(0x4597f740);
	if ($number !== '3GmYeH') {
		throw new ErrorException('Unit test: customFromDecimal() failed');
	}
	$decimal = $wrdx51->decimalFromCustom($number);
	if ($decimal != 0x4597f740) {
		throw new ErrorException('Unit test: decimalFromCustom() failed');
	}

	assert($wrdxai->customFromDecimal(0x4597f740) == '١١٦٧٥٨٧١٣٦');

	assert($wrdx16->customFromDecimal($wrdx51->decimalFromCustom($wrdx51->customFromDecimal(0x4597f740))) == '4597f740');

	$result = "";
	for ( $i = 1; $i <= 10; $i++) {
		$result .= $wrdxRoman->customFromDecimal($i);
		if ($i != 10) $result .= ", ";
	}
	assert($result === 'I, II, III, IV, V, VI, VII, VIII, IX, X');

	// try forward and inverse mapping for random numbers to and from base 51
	$randomNumberSets = array($randomInts);
	if ($className::$maximumValue === null) {
	  $randomNumberSets[] = $randomDecimalStrings;
	}
	foreach ($randomNumberSets as $randomNumbers) {
		$startTime = microtime(TRUE);
		for ( $i = 0; $i<count($randomNumbers); $i++) {
			$decimal = (string)$randomNumbers[$i];
			$number = $wrdx51->customFromDecimal($decimal);
			if ((count($randomNumbers) - $i) < 3) printf("%s", $number);
			$decimalInverse = $wrdx51->decimalFromCustom($number);
			if ($decimal != $decimalInverse) {
				throw new ErrorException(sprintf('Unit test failure: inverse map failed for random number (%s=>%s) (ix=%u)',
					$decimal, $decimalInverse,$i ));
			}
			if (isset($customResults[$decimal])) {
				assert($customResults[$decimal] === $number);
			  if ((count($randomNumbers) - $i) < 3) printf("/OK ");
			} else {
			  if ((count($randomNumbers) - $i) < 3) printf("/?? ");
				$customResults[$decimal] = $number;
			}

			//printf("\$i=%u; \$decimal='%s'\n", $i, $decimal);
			// go to hex and back
			$hex = $className::hexFromDecimal($decimal);
			//printf("\$hex='%s'\n", $hex);
			//printf("hinverse='%s'\n", "".$className::decimalFromHex($hex));
			assert("$decimal" === ("".$className::decimalFromHex($hex)));

			// go to binary and back
			$bin = $className::binFromDecimal($decimal);
			//printf("\$bin='%s'\n", $bin);
			//printf("binverse='%s'\n", "".$className::decimalFromBin($bin));
			assert("$decimal" === ("".$className::decimalFromBin($bin)));

		}
		printf("\n%s ms\n", intval((microtime(TRUE) - $startTime)*1000000)/1000);
	}


	for ( $i=0; $i<10; $i++) {
		$random = $wrdx51->customRandomDigits($i+1);
		if ($random === NULL) break;
		printf("%s:",$i+1);
		printf("%s ", $random);
		if ($className::$maximumValue) {
			$random = $wrdx51->customRandomFromInternalRange($wrdx51->internalFromDecimal($maximumInteger));
		}
		else {
			$random = $wrdx51->customRandomDigits(20);
		}
		printf("%s ", $random);
	}
	printf("\n");

}
if (count($failures)) {
  echo "===========\n";
	echo join("\n",$failures) . "\n";
  printf("FAIL\n");
	exit(1);
}
