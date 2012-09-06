
<?php
/***********************************************************
 Copyright (C) 2012 Hewlett-Packard Development Company, L.P.

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
 ***********************************************************/

/** @file UI Regression test.
 *  Each file in regression/good/ is named with a URL.
 *  Capture that URL output (to regression/output_{pid}/) and compare to good.
 *  POST data is in regression/post/
 */

// $DATAROOTDIR and $PROJECT come from Makefile
//require_once "$DATAROOTDIR/$PROJECT/lib/php/bootstrap.php";
require_once "/usr/local/share/fossology/lib/php/bootstrap.php";

$SysConf = array();  // fo system configuration variables
$PG_CONN = 0;   // Database connection

/* Set SYSCONFDIR and set global (for backward compatibility) */
$SysConf = bootstrap();

/* Initialize global system configuration variables $SysConfig[] */
ConfigInit($SYSCONFDIR, $SysConf);

/* Directories */
$GoodDir = "regression/good/";
$PostDir = "regression/post/";
$OutputDirTemplate = "regression/output_";  /* output dir is suffixed with pid */
$OutputDir = $OutputDirTemplate . posix_getpid();

/*  -h webhost
 */
$Options = getopt("hw:");
if ( array_key_exists('h', $Options))
{
  Usage($argc, $argv);
  exit(0);
}

if ( array_key_exists('w', $Options))
{
  $WebHost = $Options['w'];
}
else
{
  $WebHost = "localhost";
}

/* Create directory to put results */
if (mkdir($OutputDir, 0777, true) === false)
{
  echo "Fatal: Could not create $OutputDir\n";
  exit(-1);
}

/* Open $GoodDir */
if (($DirH = opendir($GoodDir)) === false)
{
  echo "Fatal: Could not create $OutputDir\n";
  exit(-1);
}

/* Loop through $GoodDir files */
while (($FileName = readdir($DirH)) !== false)
{
  if ($FileName[0] == '.') continue;

  /* $FileName is a URL, hit it and save the results. */
  $URL = $WebHost . "/$FileName";
echo "URL is $URL\n";
  $ch = curl_init($URL);
  SetCurlArgs($ch);
  $contents = curl_exec( $ch );
  curl_close( $ch );

  /* Save the output in $OutputDir */
  $OutFileName = $OutputDir . "/$FileName";
echo "OutFileName is $OutFileName\n";
echo "data length is " . strlen($contents) . "\n";
  if (file_put_contents($OutFileName, $contents) === false)
  {
    echo "Failed to write contents to $OutFileName.\n";
  }
}

return (0);

/**
 * @brief Set basic curl args
 * @param $ch  curl handle
 **/
function SetCurlArgs($ch)
{
  global $SysConf;
//  curl_setopt($ch,CURLOPT_USERAGENT,'Curl-php');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
  curl_setopt($ch,
              CURLOPT_HTTPHEADER, array("Content-Type:
               text/html; charset=utf-8"));

  /* parse http_proxy server and port */
  $http_proxy = $SysConf['FOSSOLOGY']['http_proxy'];
  $ProxyServer = substr($http_proxy, 0, strrpos($http_proxy, ":"));
  $ProxyPort = substr(strrchr($http_proxy, ":"), 1);
  if (!empty($ProxyServer))
  {
    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, TRUE);
    curl_setopt($ch, CURLOPT_PROXY, $ProxyServer);
    if (!empty($ProxyPort)) curl_setopt($ch, CURLOPT_PROXYPORT, $ProxyPort);
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
  }
}

/**
 * @brief Usage
 * @param $argc
 * @param $argv
 **/
function Usage($argc, $argv)
{
  echo "$argv[0] -h -w {web host}\n";
  echo "         -h help\n";
  echo "         -w Web Host. Optional. E.G. bobg.fc.hp.com/trunk\n";
}

?>