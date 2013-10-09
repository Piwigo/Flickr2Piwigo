<?php
if (!defined('FLICKR_PATH')) die('Hacking attempt!');

function flickr_add_ws_method($arr)
{
  $service = &$arr[0];
  
  $service->addMethod(
    'pwg.images.addFlickr',
    'ws_images_addFlickr',
    array(
      'id' => array(),
      'category' => array(),
      'fills' => array('default' =>null),
      ),
    'Used by Flickr2Piwigo'
    );
}

function ws_images_addFlickr($photo, &$service)
{
  if (!is_admin())
  {
    return new PwgError(403, 'Forbidden');
  }
  
  global $conf;
  $conf['flickr2piwigo'] = unserialize($conf['flickr2piwigo']);
  
  if ( empty($conf['flickr2piwigo']['api_key']) or empty($conf['flickr2piwigo']['secret_key']) )
  {
    return new PwgError(null, l10n('Please fill your API keys on the configuration tab'));
  }
  
  include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
  include_once(PHPWG_ROOT_PATH . 'admin/include/functions_upload.inc.php');
  include_once(FLICKR_PATH . 'include/functions.inc.php');
  
  if (test_remote_download() === false)
  {
    return new PwgError(null, l10n('No download method available'));
  }
  
  // init flickr API
  include_once(FLICKR_PATH . 'include/phpFlickr/phpFlickr.php');
  $flickr = new phpFlickr($conf['flickr2piwigo']['api_key'], $conf['flickr2piwigo']['secret_key']);
  $flickr->enableCache('fs', FLICKR_FS_CACHE);
  
  // user
  $u = $flickr->test_login();
  if ( $u === false or empty($_SESSION['phpFlickr_auth_token']) )
  {
    return new PwgError(403, l10n('API not authenticated'));
  }
  
  // photos infos
  $photo_f = $flickr->photos_getInfo($photo['id']);
  $photo = array_merge($photo, $photo_f['photo']);
  $photo['url'] = $flickr->get_biggest_size($photo['id'], 'original');
  $photo['path'] = FLICKR_FS_CACHE . 'flickr-'.$u['username'].'-'.$photo['id'].'.'.get_extension($photo['url']);
  
  // copy file
  if (download_remote_file($photo['url'], $photo['path']) == false)
  {
    return new PwgError(null, l10n('Can\'t download file'));
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
        
        $cat = create_virtual_category($category_name);
        array_push($photo['category'], $cat['id']);
      }
    }
  }
  else
  {
    $photo['category'] = array($photo['category']);
  }
  
  // add photo
  $photo['image_id'] = add_uploaded_file($photo['path'], basename($photo['path']), $photo['category']);
  
  // do some updates
  if (!empty($photo['fills']))
  {
    $photo['fills'] = rtrim($photo['fills'], ',');
    $photo['fills'] = explode(',', $photo['fills']);
  
    $updates = array();
    if (in_array('fill_name', $photo['fills']))   $updates['name'] = pwg_db_real_escape_string($photo['title']); 
    if (in_array('fill_posted', $photo['fills'])) $updates['date_available'] = date('Y-m-d H:i:s', $photo['dates']['posted']);
    if (in_array('fill_taken', $photo['fills']))  $updates['date_creation'] = $photo['dates']['taken'];
    if (in_array('fill_author', $photo['fills'])) $updates['author'] = pwg_db_real_escape_string($photo['owner']['username']);
    
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
  
  return sprintf(l10n('Photo "%s" imported'), $photo['title']);
}

?>