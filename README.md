# WeirdoCustomDigits
====================
© 2014 Daniel Norton d/b/a WeirdoSoft - www.weirdosoft.com

This module provides the WeirdoCustomDigits PHP classes, which convert custom numbers with arbitrary radixes and digit characters.

## Documentation
The latest API documentation is at http://danorton.github.io/WeirdoCustomDigits/dox/

## License
**GPL v3**

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

## Releases

 - 1.0.2-alpha - License under GPL v3
 - 1.0.1-alpha - enhancements, bug fixes & miscellaneous maintenance
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

 - 1.0.0-alpha - initial release
   - Seems to work
   - API documented in Doxygen
   - Presumes IEEE 754 floating point, but that's configurable
   - Unit test is crude
     - Tested with gmp, bc, 32-bit PHP and 64-bit PHP

