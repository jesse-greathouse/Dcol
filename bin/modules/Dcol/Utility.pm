#!/usr/bin/perl

package Dcol::Utility;
use strict;
use Exporter 'import';

our @EXPORT_OK = qw(
    splash
);

1;

# ====================================
#    Subroutines below this point
# ====================================

# Prints a spash screen message.
sub splash() {
  print (''."\n");
  print ('+--------------------------------------------------------------------------------------+'."\n");
  print ('| Thank you for choosing dcol                                                          |'."\n");
  print ('+--------------------------------------------------------------------------------------+'."\n");
  print ('| Copyright (c) 2023 Jesse Greathouse (https://github.com/jesse-greathouse/dcol)       |'."\n");
  print ('+--------------------------------------------------------------------------------------+'."\n");
  print ('| dcol is free software: you can redistribute it and/or modify it under the            |'."\n");
  print ('| terms of thethe Free Software Foundation, either version 3 of the License, or GNU    |'."\n");
  print ('| General Public License as published by (at your option) any later version.           |'."\n");
  print ('|                                                                                      |'."\n");
  print ('| dcol is distributed in the hope that it will be useful, but WITHOUT ANY              |'."\n");
  print ('| WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A      |'."\n");
  print ('| PARTICULAR PURPOSE.  See the GNU General Public License for more details.            |'."\n");
  print ('|                                                                                      |'."\n");
  print ('| You should have received a copy of the GNU General Public License along with         |'."\n");
  print ('| dcol. If not, see <http://www.gnu.org/licenses/>.                                    |'."\n");
  print ('+--------------------------------------------------------------------------------------+'."\n");
  print ('| Author: Jesse Greathouse <jesse.greathouse@gmail.com>                                |'."\n");
  print ('+--------------------------------------------------------------------------------------+'."\n");
  print (''."\n");
}
