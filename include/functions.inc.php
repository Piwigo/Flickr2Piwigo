<?php
defined('FLICKR_PATH') or die('Hacking attempt!');

include_once(FLICKR_PATH . 'vendor/autoload.php');

use OAuth\Common\Storage\Memory;
use OAuth\OAuth1\Token\StdOAuth1Token;
use Samwilson\PhpFlickr\PhpFlickr;
use Stash\Driver\FileSystem;
use Stash\Pool;

/**
 * Get a PhpFlickr object, already set up with the stored credentials.
 * @return PhpFlickr|bool The PhpFlickr object, or false if it could not be instantiated.
 */
function get_PhpFlickr() {
  global $conf;

  // Check for the API details.
  if (empty($conf['flickr2piwigo']['api_key']) or empty($conf['flickr2piwigo']['secret_key']))
  {
    return false;
  }

  $flickr = new PhpFlickr($conf['flickr2piwigo']['api_key'], $conf['flickr2piwigo']['secret_key']);

  // Enable the cache.
  $driver = new FileSystem([ 'path' => FLICKR_FS_CACHE ]);
  $pool = new Pool($driver);
  $flickr->setCache($pool);

  // Load access token if one's been saved.
  if (isset($conf['flickr2piwigo']['access_token']) and isset($conf['flickr2piwigo']['access_secret'])) {
    $token = new StdOAuth1Token();
    $token->setAccessToken( $conf['flickr2piwigo']['access_token'] );
    $token->setAccessTokenSecret( $conf['flickr2piwigo']['access_secret'] );
    $storage = new Memory();
    $storage->storeAccessToken('Flickr', $token);
    $flickr->setOauthStorage($storage);
  }

  return $flickr;
}

/**
 * test if a download method is available
 * @return: bool
 */
if (!function_exists('test_remote_download'))
{
  function test_remote_download()
  {
    return function_exists('curl_init') || ini_get('allow_url_fopen');
  }
}

/**
 * download a remote file
 *  - needs cURL or allow_url_fopen
 *  - take care of SSL urls
 *
 * @param: string source url
 * @param: mixed destination file (if true, file content is returned)
 */
if (!function_exists('download_remote_file'))
{
  function download_remote_file($src, $dest)
  {
    if (empty($src))
    {
      return false;
    }

    $return = ($dest === true) ? true : false;

    /* curl */
    if (function_exists('curl_init'))
    {
      if (!$return)
      {
        $newf = fopen($dest, "wb");
      }
      $ch = curl_init();

      curl_setopt($ch, CURLOPT_URL, $src);
      curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept-language: en"));
      curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)');
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      if (!ini_get('safe_mode'))
      {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 1);
      }
      if (strpos($src, 'https://') !== false)
      {
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
      }
      if (!$return)
      {
        curl_setopt($ch, CURLOPT_FILE, $newf);
      }
      else
      {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      }

      $out = curl_exec($ch);
      curl_close($ch);

      if ($out === false)
      {
        return 'file_error';
      }
      else if (!$return)
      {
        fclose($newf);
        return true;
      }
      else
      {
        return $out;
      }
    }
    /* file get content */
    else if (ini_get('allow_url_fopen'))
    {
      if (strpos($src, 'https://') !== false and !extension_loaded('openssl'))
      {
        return false;
      }

      $opts = array(
        'http' => array(
          'method' => "GET",
          'user_agent' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)',
          'header' => "Accept-language: en",
        )
      );

      $context = stream_context_create($opts);

      if (($file = file_get_contents($src, false, $context)) === false)
      {
        return 'file_error';
      }

      if (!$return)
      {
        file_put_contents($dest, $file);
        return true;
      }
      else
      {
        return $file;
      }
    }

    return false;
  }
}
