<?php
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

define(
  'flickr2piwigo_default_config', 
  serialize(array(
    'api_key' => null,
    'secret_key' => null,
    ))
  );


function plugin_install() 
{
  global $conf;
  
  conf_update_param('flickr2piwigo', flickr2piwigo_default_config);
  
  mkgetdir(PHPWG_ROOT_PATH . $conf['data_location'] . 'flickr_cache/', MKGETDIR_DEFAULT&~MKGETDIR_DIE_ON_ERROR);
}

function plugin_activate()
{
  global $conf;

  if (empty($conf['flickr2piwigo']))
  {
    conf_update_param('flickr2piwigo', flickr2piwigo_default_config);
  }
  
  if (!file_exists(PHPWG_ROOT_PATH . $conf['data_location'] . 'flickr_cache/'))
  {
    mkgetdir(PHPWG_ROOT_PATH . $conf['data_location'] . 'flickr_cache/', MKGETDIR_DEFAULT&~MKGETDIR_DIE_ON_ERROR);
  }
}

function plugin_uninstall() 
{
  global $conf;
  
  pwg_query('DELETE FROM `'. CONFIG_TABLE .'` WHERE param = "flickr2piwigo";');
  unset($conf['flickr2piwigo']);
  
  rrmdir(PHPWG_ROOT_PATH . $conf['data_location'] . 'flickr_cache/');
}

function rrmdir($dir)
{
  if (!is_dir($dir))
  {
    return false;
  }
  $dir = rtrim($dir, '/');
  $objects = scandir($dir);
  $return = true;
  
  foreach ($objects as $object)
  {
    if ($object !== '.' && $object !== '..')
    {
      $path = $dir.'/'.$object;
      if (filetype($path) == 'dir') 
      {
        $return = $return && rrmdir($path); 
      }
      else 
      {
        $return = $return && @unlink($path);
      }
    }
  }
  
  return $return && @rmdir($dir);
} 

?>