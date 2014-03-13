WeirdoCustomDigits
==================
© 2014 Daniel Norton d/b/a WeirdoSoft - www.weirdosoft.com

This module provides the WeirdoCustomDigits PHP classes, which convert custom numbers with arbitrary radixes and digit characters.

# Documentation
The latest API documentation is at http://danorton.github.io/WeirdoCustomDigits/dox/

# License
CC BY-SA 3.0 - 
This work is licensed under the Creative Commons
Attribution-ShareAlike 3.0 Unported License. To view a copy of
this license, visit http://creativecommons.org/licenses/by-sa/3.0/
or send a letter to Creative Commons, 444 Castro Street, Suite 900,
Mountain View, California, 94041, USA.

# Releases

 - **PENDING** 1.0.1 - enhancements, bug fixes & miscellaneous maintenance
   - Enhancements:
     - #7 - Remove limit of $nDigits in WeirdoCustomDigitsInt::customRandomDigits()
             - When the parameter $allowOverflow is true, this method will allow the
               number of digits requested to be unlimited. The default and prior behavior
               was to throw an exception if the number of requested digits could produce
               a value that was outside the supported numerical range. This is only relevant
               to the WeirdoCustomDigitsInt implementation subclass, as the other
               subclasses have no limit on the numerical range.
     - #8, #9 - Add base64 compatibility and raw bytes
          - These two enhancements together provide low-lever base64 encode and decode.
   - Bugs fixed:
     - #1 - customRandomDigits() method returns fewer than $nDigits characters
     - #5 - customRandomDigits returns null when $nDigits is beyond range

 - 1.0.0 - initial release
   - Seems to work
   - API documented in Doxygen
   - Presumes IEEE 754 floating point, but that's configurable
   - Unit test is crude
     - Tested with gmp, bc, 32-bit PHP and 64-bit PHP

