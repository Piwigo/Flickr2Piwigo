<?php 
/*
Plugin Name: Flickr2Piwigo
Version: auto
Description: Import pictures from your Flickr account
Plugin URI: auto
Author: Mistic
Author URI: http://www.strangeplanet.fr
*/

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

global $conf;

define('FLICKR_PATH',     PHPWG_PLUGINS_PATH . basename(dirname(__FILE__)) . '/');
define('FLICKR_ADMIN',    get_root_url() . 'admin.php?page=plugin-' . basename(dirname(__FILE__)));
define('FLICKR_FS_CACHE', PHPWG_ROOT_PATH . $conf['data_location'] . 'flickr_cache/');


if (defined('IN_ADMIN'))
{
  add_event_handler('get_admin_plugin_menu_links', 'flickr_admin_menu');
  add_event_handler('get_batch_manager_prefilters', 'flickr_add_batch_manager_prefilters');
  add_event_handler('perform_batch_manager_prefilters', 'flickr_perform_batch_manager_prefilters', EVENT_HANDLER_PRIORITY_NEUTRAL, 2);
  add_event_handler('loc_begin_admin_page', 'flickr_prefilter_from_url');

  function flickr_admin_menu($menu) 
  {
    array_push($menu, array(
      'NAME' => 'Flickr2Piwigo',
      'URL' => FLICKR_ADMIN,
    ));
    return $menu;
  }
  
  function flickr_add_batch_manager_prefilters($prefilters)
  {
    array_push($prefilters, array(
      'ID' => 'flickr',
      'NAME' => l10n('Imported from Flickr'),
    ));
    return $prefilters;
  }

  function flickr_perform_batch_manager_prefilters($filter_sets, $prefilter)
  {
    if ($prefilter == 'flickr')
    {
      $query = '
  SELECT id
    FROM '.IMAGES_TABLE.'
    WHERE file LIKE "flickr-%"
  ;';
      $filter_sets[] = array_from_query($query, 'id');
    }
    
    return $filter_sets;
  }
  
  function flickr_prefilter_from_url()
  {
    global $page;
    if ($page['page'] == 'batch_manager' && @$_GET['prefilter'] == 'flickr')
    {
      $_SESSION['bulk_manager_filter'] = array('prefilter' => 'flickr');
      unset($_GET['prefilter']);
    }
  }
}


include_once(FLICKR_PATH . 'include/ws_functions.inc.php');

add_event_handler('ws_add_methods', 'flickr_add_ws_method');

?>