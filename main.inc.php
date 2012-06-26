<?php 
/*
Plugin Name: Flickr2Piwigo
Version: auto
Description: Extension for importing pictures from your Flickr account
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=
Author: Mistic
Author URI: http://www.strangeplanet.fr
*/

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

global $conf;

define('FLICKR_PATH', PHPWG_PLUGINS_PATH . basename(dirname(__FILE__)) . '/');
define('FLICKR_ADMIN', get_root_url() . 'admin.php?page=plugin-' . basename(dirname(__FILE__)));
define('FLICKR_FS_CACHE', $conf['data_location'].'flickr_cache/');

add_event_handler('get_admin_plugin_menu_links', 'flickr2_admin_menu');

function flickr2_admin_menu($menu) 
{
  array_push($menu, array(
    'NAME' => 'Flickr2Piwigo',
    'URL' => FLICKR_ADMIN,
  ));
  return $menu;
}

add_event_handler('ws_add_methods', 'flickr2_add_ws_method');

function flickr2_add_ws_method($arr)
{
  $service = &$arr[0];
  
  $service->addMethod(
    'pwg.images.addFlickr',
    'ws_images_addFlickr',
    array(
      'category' => array('default' => null),   
      'id' => array('default' => null),
      'fills' => array('default' =>null),
      ),
    'Used by Flickr2Piwigo, fills € (fill_name,fill_posted,fill_taken,fill_author,fill_tags)'
    );
}

function ws_images_addFlickr($photo, &$service)
{
  global $conf;
  
  if (!is_admin())
  {
    return new PwgError(403, 'Forbidden');
  }
  
  $conf['flickr2piwigo'] = unserialize($conf['flickr2piwigo']);
  
  if (empty($conf['flickr2piwigo']['api_key']) or empty($conf['flickr2piwigo']['secret_key']) or empty($conf['flickr2piwigo']['username']))
  {
    return new PwgError(500, l10n('Please fill your API keys on the configuration tab'));
  }
  
  if (empty($_SESSION['phpFlickr_auth_token']))
  {
    return new PwgError(403, l10n('API not authenticated'));
  }
  
  // category
  if (!preg_match('#^[0-9]+$#', $photo['category']))
  {
    $categories_names = explode(',', $photo['category']);
    
    $photo['category'] = array();
    foreach ($categories_names as $category_name)
    {
      $query = '
SELECT id FROM '.CATEGORIES_TABLE.'
  WHERE LOWER(name) = "'.strtolower($category_name).'"
;';
      $result = pwg_query($query);
      
      if (pwg_db_num_rows($result))
      {
        list($cat_id) = pwg_db_fetch_row($result);
        array_push($photo['category'], $cat_id);
      }
      else
      {
        include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
        $cat = create_virtual_category($category_name);
        array_push($photo['category'], $cat_id['id']);
      }
    }
  }
  else
  {
    $photo['category'] = array($photo['category']);
  }
  
  // init flickr API
  include_once(FLICKR_PATH . 'include/phpFlickr/phpFlickr.php');
  $flickr = new phpFlickr($conf['flickr2piwigo']['api_key'], $conf['flickr2piwigo']['secret_key']);
  $flickr->enableCache('fs', FLICKR_FS_CACHE);
  
  // photos infos
  $photo_f = $flickr->photos_getInfo($photo['id']);
  $photo = array_merge($photo, $photo_f['photo']);
  $photo['url'] = $flickr->get_biggest_size($photo['id'], 'original');
  $photo['path'] = FLICKR_FS_CACHE . 'flickr-'.$conf['flickr2piwigo']['username'].'-'.$photo['id'].'.'.get_extension($photo['url']);
  
  // copy file
  $file = fopen($photo['url'], "rb");
  $newf = fopen($photo['path'], "wb");
  while (!feof($file))
  {
    fwrite($newf, fread($file, 1024 * 8 ), 1024 * 8 );
  }
  fclose($file);
  fclose($newf);
  
  // add to database
  include_once(PHPWG_ROOT_PATH . 'admin/include/functions_upload.inc.php');
  $photo['image_id'] = add_uploaded_file($photo['path'], basename($photo['path']), $photo['category']);
  
  // do some updates
  if (!empty($photo['fills']))
  {
    $photo['fills'] = rtrim($photo['fills'], ',');
    $photo['fills'] = explode(',', $photo['fills']);
  
    $updates = array();
    if (in_array('fill_name', $photo['fills']))   $updates['name'] = $photo['title']; 
    if (in_array('fill_posted', $photo['fills'])) $updates['date_available'] = date('Y-d-m H:i:s', $photo['dates']['posted']);
    if (in_array('fill_taken', $photo['fills']))  $updates['date_creation'] = $photo['dates']['taken'];
    if (in_array('fill_author', $photo['fills'])) $updates['author'] = $conf['flickr2piwigo']['username'];
    
    if (count($updates))
    {
      single_update(
        IMAGES_TABLE,
        $updates,
        array('id' => $photo['image_id'])
        );
    }
    
    if ( !empty($photo['tags']['tag']) and in_array('fill_tags', $photo['fills']) )
    {
      $raw_tags = array_map(create_function('$t', 'return $t["_content"];'), $photo['tags']['tag']);
      $raw_tags = implode(',', $raw_tags);
      set_tags(get_tag_ids($raw_tags), $photo['image_id']);
    }
  }
  
  return sprintf(l10n('%s imported'), $photo['title']);
}

?>