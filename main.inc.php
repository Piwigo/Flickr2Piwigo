<?php 
/*
Plugin Name: Flickr2Piwigo
Version: auto
Description: Import pictures from your Flickr account
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=612
Author: Mistic
Author URI: http://www.strangeplanet.fr
*/

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

global $conf;

define('FLICKR_PATH', PHPWG_PLUGINS_PATH . basename(dirname(__FILE__)) . '/');
define('FLICKR_ADMIN', get_root_url() . 'admin.php?page=plugin-' . basename(dirname(__FILE__)));
define('FLICKR_FS_CACHE', $conf['data_location'].'flickr_cache/');


if (defined('IN_ADMIN'))
{
  add_event_handler('get_admin_plugin_menu_links', 'flickr_admin_menu');

  function flickr_admin_menu($menu) 
  {
    array_push($menu, array(
      'NAME' => 'Flickr2Piwigo',
      'URL' => FLICKR_ADMIN,
    ));
    return $menu;
  }
}


include_once(FLICKR_PATH . 'include/ws_functions.inc.php');

add_event_handler('ws_add_methods', 'flickr_add_ws_method');

?>