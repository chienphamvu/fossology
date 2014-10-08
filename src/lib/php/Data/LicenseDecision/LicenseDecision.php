<?php
/*
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/
namespace Fossology\Lib\Dao\Data\LicenseDecision;

use Fossology\Lib\Data\License\LicenseAlike;

interface LicenseDecision extends LicenseAlike
{
  /**
   * @return string
   */
  public function getComment();

  /**
   * @return float
   */
  public function getEpoch();

  /**
   * @return int
   */
  public function getEventId();

  /**
   * @return string
   */
  public function getEventType();

  /**
   * @return int
   */
  public function getLicenseId();

  /**
   * @return string
   */
  public function getLicenseShortName();

  /**
   * @return string
   */
  public function getReportinfo();

  /**
   * @return boolean
   */
  public function isGlobal();

  /**
   * @return boolean
   */
  public function isRemoved();
}