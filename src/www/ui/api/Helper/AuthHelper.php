<?php
/***************************************************************
 * Copyright (C) 2018 Siemens AG
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 ***************************************************************/

/**
 * @dir
 * @brief Helper functions for REST api use.
 * @file
 * @brief Provides authentication helper methods for REST api.
 * @namespace Fossology::UI::Api::Helper
 * @brief REST api helper classes
 */
namespace Fossology\UI\Api\Helper;

use Symfony\Component\HttpFoundation\Session\Session;
use Fossology\Lib\Dao\UserDao;
use Firebase\JWT\JWT;
use Fossology\UI\Api\Models\Info;
use Fossology\UI\Api\Models\InfoType;

/**
 * @class AuthHelper
 * @brief Provides helper methods for REST api
 */
class AuthHelper
{
  /**
   * @var Session $session
   * Current Symfony session
   */
  private $session;
  /**
   * @var UserDao $userDao
   * User DAO object
   */
  private $userDao;
  /**
   * @var DbHelper $dbHelper
   * DB helper for DB interaction.
   */
  private $dbHelper;

  /**
   * AuthHelper constructor.
   *
   * @param UserDao $userDao   User dao to use
   * @param Session $session   Session to use
   * @param DbHelper $dbhelper Db Helper to use
   */
  public function __construct(UserDao $userDao, Session $session,
    DbHelper $dbhelper)
  {
    $this->userDao = $userDao;
    $this->session = $session;
    $this->dbHelper = $dbhelper;
    if (!$this->session->isStarted())
    {
      $this->session->setName('Login');
      $this->session->start();
    }
  }

  /**
   * @brief Check the username and password against the database.
   *
   * If the user is not 'Default User' and is valid, this function also update
   * session using updateSession().
   * @param string $userName  Username
   * @param string $password  Password
   * @return boolean True if user is valid, false otherwise.
   * @sa updateSession()
   */
  public function checkUsernameAndPassword($userName, $password)
  {
    $authPlugin = $GLOBALS["container"]->get("helper.restHelper")->getPlugin('auth');
    return $authPlugin->checkUsernameAndPassword($userName, $password);
  }

  /**
   * Verify the JWT token sent by user.
   *
   * @param string $authHeader The "Authorization" header sent by user.
   * @param string $hostname   Host name to verify the audience of the token.
   * @param int    $userId     The user id as per the valid token.
   * @param string $tokenScope The scope of the token presented.
   * @return boolean|Fossology::UI::Api::Models::Info True if the token is valid,
   *         false otherwise, Info in case of error.
   */
  public function verifyAuthToken($authHeader, $hostname, &$userId, &$tokenScope)
  {
    $headerValid = preg_match(
      "/^bearer (([a-zA-Z0-9\-\_\+\/\=]+)\.([a-zA-Z0-9\-\_\+\/\=]+)\.([a-zA-Z0-9\-\_\+\/\=]+))$/i",
      $authHeader, $jwtTokenMatch);
    $returnValue = true;
    if (! $headerValid) {
      $returnValue = new Info(400, "Authorization header is malformed or empty.",
        InfoType::ERROR);
    } else {
      $jwtToken           = $jwtTokenMatch[1];
      $jwtTokenPayload    = $jwtTokenMatch[3];
      $jwtTokenPayloadDecoded = JWT::jsonDecode(
        JWT::urlsafeB64Decode($jwtTokenPayload));

      if (($jwtTokenPayloadDecoded->{'jti'} === null) ||
        ($jwtTokenPayloadDecoded->{'aud'} != $hostname)) {
        return new Info(403, "Invalid token sent.", InfoType::ERROR);
      }
      $jwtJti = $jwtTokenPayloadDecoded->{'jti'};
      $jwtJti = base64_decode($jwtJti, true);
      list ($tokenId, $userId) = explode(".", $jwtJti);

      $dbRows = $this->dbHelper->getTokenKey($tokenId);
      $isTokenActive = $this->isTokenActive($dbRows, $tokenId);
      if (empty($dbRows)) {
        $returnValue = new Info(403, "Invalid token sent.", InfoType::ERROR);
      } elseif ($isTokenActive !== true) {
        $returnValue = $isTokenActive;
      } else {
        try {
          $jwtTokenDecoded = JWT::decode($jwtToken, $dbRows["token_key"], ['HS256']);
          $tokenScope = $jwtTokenDecoded->{'scope'};
        } catch (\UnexpectedValueException $e) {
          $returnValue = new Info(403, $e->getMessage(), InfoType::ERROR);
        }
      }
    }
    return $returnValue;
  }

  /**
   * Check if the given date is expired (is past).
   *
   * @param string $date Date in `Y-m-d` format
   * @return boolean True if the date is of past.
   */
  private function isDateExpired($date)
  {
    return strtotime("today") > strtotime($date);
  }

  /**
   * Check if the token is still active and not expired.
   *
   * @param array $valuesFromDb Values from DB.
   * @param array $tokenId      Token id (pat_pk)
   * @return boolean|Fossology::UI::Api::Models::Info True if values are ok
   *         Info otherwise.
   */
  public function isTokenActive($valuesFromDb, $tokenId)
  {
    $isPayloadValid = true;
    if ($valuesFromDb['active'] == "f") {
      $isPayloadValid = new Info(403, "Token expired.", InfoType::ERROR);
    } elseif ($this->isDateExpired($valuesFromDb['expire_on']) &&
      $valuesFromDb['active'] == "t") {
      $this->dbHelper->invalidateToken($tokenId);
      $isPayloadValid = new Info(403, "Token expired.", InfoType::ERROR);
    }
    return $isPayloadValid;
  }

  /**
   * Get the current Symfony session
   * @return Session
   */
  public function getSession()
  {
    return $this->session;
  }

  /**
   * @brief Update the session using updateSession().
   *
   * @param int    $userId User id from the JWT.
   * @param string $scope  Scope of the current token.
   * @sa updateSession()
   */
  public function updateUserSession($userId, $scope)
  {
    $authPlugin = $GLOBALS["container"]->get("helper.restHelper")->getPlugin('auth');
    $user = $this->dbHelper->getUsers($userId)[0];
    $row = $this->userDao->getUserAndDefaultGroupByUserName($user["name"]);
    $authPlugin->updateSession($row);
    $this->getSession()->set('token_scope', $scope);
  }

  /**
   * Generates new JWT token.
   *
   * @param string $hostname Hostname of the issuer
   * @param string $expire   When the token will expire ('YYYY-MM-DD')
   * @param string $created  When the token was created ('YYYY-MM-DD')
   * @param string $jti      Token id (`pat_pk.user_pk`)
   * @param string $scope    User friendly token scope
   * @param string $key      Token secret key
   * @return string New JWT token
   */
  public function generateJwtToken($hostname, $expire, $created, $jti, $scope, $key)
  {
    $newJwtToken = [
      "iss" => $hostname,
      "aud" => $hostname,
      "exp" => strtotime($expire . " +1 day -1 second"),  // To allow day level granularity
      "nbf" => strtotime($created),
      "jti" => base64_encode($jti),
      "scope" => $scope
    ];
    return JWT::encode($newJwtToken, $key, 'HS256');
  }

  /**
   * Get the value for maximum API token validity from sysconfig table.
   *
   * @return integer The value stored in DB.
   * @see Fossology::UI::Api::Helper::getMaxTokenValidity()
   */
  public function getMaxTokenValidity()
  {
    return $this->dbHelper->getMaxTokenValidity();
  }
}
