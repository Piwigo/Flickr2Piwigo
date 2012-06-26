<?php
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

define(
  'flickr2piwigo_default_config', 
  serialize(array(
    'api_key' => null,
    'secret_key' => null,
    'username' => null,
    ))
  );
  

function plugin_install() 
{
  global $conf;
  conf_update_param('flickr2piwigo', flickr2piwigo_default_config);
  mkdir($conf['data_location'].'flickr_cache/', 0755);
}

function plugin_activate()
{
  global $conf;

  if (empty($conf['flickr2piwigo']))
  {
    conf_update_param('flickr2piwigo', flickr2piwigo_default_config);
  }
  
  if (!file_exists($conf['data_location'].'flickr_cache/'))
  {
    mdir($conf['data_location'].'flickr_cache/', 0755);
  }
}

function plugin_uninstall() 
{
  pwg_query('DELETE FROM `'. CONFIG_TABLE .'` WHERE param = "flickr2piwigo" LIMIT 1;');
}

?>