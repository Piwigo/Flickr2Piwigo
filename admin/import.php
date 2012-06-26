<?php
if (!defined('FLICKR_PATH')) die('Hacking attempt!');

// check API parameters and connect to flickr
if (empty($conf['flickr2piwigo']['api_key']) or empty($conf['flickr2piwigo']['secret_key']) or empty($conf['flickr2piwigo']['username']))
{
  array_push($page['warnings'], l10n('Please fill your API keys on the configuration tab'));
  $_GET['action'] = 'error';
}
else
{
  // init flickr API
  include_once(FLICKR_PATH . 'include/phpFlickr/phpFlickr.php');
  $flickr = new phpFlickr($conf['flickr2piwigo']['api_key'], $conf['flickr2piwigo']['secret_key']);
  $flickr->enableCache('fs', FLICKR_FS_CACHE);
  
  // must authenticate
  if (empty($_SESSION['phpFlickr_auth_token']) and @$_GET['action']!='login')
  {
    $_GET['action'] = 'init_login';
  }
  else
  {
    // get user id
    $u = $flickr->people_findByUsername($conf['flickr2piwigo']['username']);
    if ($u === false)
    {
      array_push($page['errors'], l10n('Unknown username, please verify your configuration'));
      $_GET['action'] = 'error';
    }
  }
  
  
  // generate token after authentication
  if (!empty($_GET['frob']))
  {
    $flickr->auth_getToken($_GET['frob']);
    $_GET['action'] = 'logued';
  }
}

if (!isset($_GET['action'])) $_GET['action'] = 'choice';


switch ($_GET['action'])
{
  // button to login page
  case 'init_login':
  {
    $template->assign('flickr_login', FLICKR_ADMIN.'-import&amp;action=login');
    break;
  }
  
  // call flickr login procedure
  case 'login':
  {
    $flickr->auth('read', false);
    break;
  }
  
  // message after login
  case 'logued':
  {
    $_SESSION['page_infos'][] = l10n('Successfully logued to you Flickr account');
    redirect(FLICKR_ADMIN.'-import');
    break;
  }
  
  // main menu
  case 'choice':
  {
    $template->assign('list_albums_url', FLICKR_ADMIN.'-import&amp;action=list_albums');
    $template->assign('import_all_url', FLICKR_ADMIN.'-import&amp;action=list_all');
    break;
  }
  
  // list user albums
  case 'list_albums':
  {
    // all albums
    $albums = $flickr->photosets_getList($u['id']);
    $total_albums = $albums['total'];
    $albums = $albums['photoset'];
    
    foreach ($albums as &$album)
    {
      //$album['U_IMPORT_ALL'] = FLICKR_ADMIN.'-import&amp;action=import_album&amp;album='.$album['id'];
      $album['U_LIST'] = FLICKR_ADMIN.'-import&amp;action=list_photos&amp;album='.$album['id'];
    }
    unset($album);
    
    // not classed
    $wo_albums = $flickr->photos_getNotInSet(NULL, NULL, NULL, NULL, 'photos', NULL, NULL, 1);
    if ($wo_albums['photos']['total'] > 0)
    {
      array_push($albums, array(
        'id' => 'not_in_set',
        'title' => l10n('Pictures without album'),
        'description' => null,
        'photos' => $wo_albums['photos']['total'],
        //'U_IMPORT_ALL' => FLICKR_ADMIN.'-import&amp;action=import_album&amp;album='.$album['id'],
        'U_LIST' => FLICKR_ADMIN.'-import&amp;action=list_photos&amp;album=not_in_set',
        ));
    }
    
    $template->assign(array(
      'total_albums' => $total_albums,
      'albums' => $albums,
      ));
    break;
  }
  
  // list photos of an album
  case 'list_photos':
  {
    if (isset($_GET['start']))   $page['start'] = intval($_GET['start']);
    else                         $page['start'] = 0;
    if (isset($_GET['display'])) $page['display'] = $_GET['display']=='all' ? 500 : intval($_GET['display']);
    else                         $page['display'] = 20;
    
    $self_url = FLICKR_ADMIN.'-import&amp;action=list_photos&amp;album='.$_GET['album'];
    $flickr_prefix = 'flickr-'.$conf['flickr2piwigo']['username'].'-';
    $flickr_root_url = $flickr->urls_getUserPhotos($u['id']);
    
    // get existing photos
    $query = '
SELECT id, file
  FROM '.IMAGES_TABLE.'
  WHERE file LIKE "'.$flickr_prefix.'%"
;';
    $existing_photos = simple_hash_from_query($query, 'id', 'file');
    $existing_photos = array_map(create_function('$p', '$p=preg_replace("#^'.$flickr_prefix.'([0-9]+)\.([a-z]{3,4})$#i", "$1", $p); return $p;'), $existing_photos);
    
    // get photos
    if ($_GET['album'] == 'not_in_set')
    {
      $all_photos = $flickr->photos_getNotInSet(NULL, NULL, NULL, NULL, 'photos', NULL, NULL, 500);
      $all_photos = $all_photos['photos']['photo'];
    }
    else
    {
      $all_photos = $flickr->photosets_getPhotos($_GET['album'], NULL, NULL, 500, NULL, 'photos');
      $all_photos = $all_photos['photoset']['photo'];
    }
    
    // remove existing
    $duplicates = 0;
    foreach ($all_photos as $i => $photo)
    {
      if (in_array($photo['id'], $existing_photos))
      {
        unset($all_photos[$i]);
        $duplicates++;
      }
    }
    if ($duplicates>0)
    {
      array_push($page['infos'], l10n_dec('One picture is not displayed because already existing in the database.', '%d pictures are not displayed because already existing in the database.', $duplicates));
    }
    
    // displayed photos
    $page_photos = array_slice($all_photos, $page['start'], $page['display']);
    $all_elements = array_map(create_function('$p', 'return  \'"\'.$p["id"].\'"\';'), $all_photos);
    
    foreach ($page_photos as &$photo)
    {
      $photo['thumb'] = $flickr->buildPhotoURL($photo, "thumbnail");
      $photo['src'] = $flickr->get_biggest_size($photo['id'], "medium_800");
      $photo['url'] = $flickr_root_url.$photo['id'];
    }
    unset($photo);
    
    $template->assign(array(
      'nb_thumbs_set' => count($all_photos),
      'nb_thumbs_page' => count($page_photos),
      'thumbnails' => $page_photos,
      'all_elements' => $all_elements,
      'album' => $_GET['album'],
      'F_ACTION' => FLICKR_ADMIN.'-import&amp;action=import_set',
      'U_DISPLAY' => $self_url,
      ));
      
    // get piwigo categories
    $query = '
SELECT id, name, uppercats, global_rank
  FROM '.CATEGORIES_TABLE.'
;';
    display_select_cat_wrapper($query, array(), 'associate_options', true);
    display_select_cat_wrapper($query, array(), 'category_parent_options');
    
    // get navbar
    $nav_bar = create_navigation_bar(
      $self_url,
      count($all_elements),
      $page['start'],
      $page['display']
      );
    $template->assign('navbar', $nav_bar);
    break;
  }
    
  case 'list_all':
  {
    $flickr_prefix = 'flickr-'.$conf['flickr2piwigo']['username'].'-';
    
    // get all photos in all albums
    $all_albums = $flickr->photosets_getList($u['id']);
    $all_albums = $all_albums['photoset'];
    
    $all_photos = array();
    foreach ($all_albums as &$album)
    {
      $album_photos = $flickr->photosets_getPhotos($album['id'], NULL, NULL, 500, NULL, 'photos');
      $album_photos = $album_photos['photoset']['photo'];
      
      foreach ($album_photos as &$photo)
      {
        $all_photos[ $photo['id'] ][] = $album['title'];
      }
      unset($photo);
    }
    unset($album);
    
    // get existing photos
    $query = '
SELECT id, file
  FROM '.IMAGES_TABLE.'
  WHERE file LIKE "'.$flickr_prefix.'%"
;';
    $existing_photos = simple_hash_from_query($query, 'id', 'file');
    $existing_photos = array_map(create_function('$p', '$p=preg_replace("#^'.$flickr_prefix.'([0-9]+)\.([a-z]{3,4})$#i", "$1", $p); return $p;'), $existing_photos);
    
    // remove duplicates
    $duplicates = 0;
    foreach ($all_photos as $id => &$photo)
    {
      if (in_array($id, $existing_photos))
      {
        unset($all_photos[$id]);
        $duplicates++;
      }
      else
      {
        $photo = array(
          'id' => $id,
          'albums' => implode(',', $photo),
          );
      }
    }
    unset($photo);
    if ($duplicates>0)
    {
      array_push($page['infos'], l10n_dec('%d picture is not displayed because already existing in the database.', '%d pictures are not displayed because already existing in the database.', $duplicates));
    }
    $all_photos = array_values($all_photos);
    
    $template->assign(array(
      'nb_elements' => count($all_photos),
      'all_elements' => json_encode($all_photos),
      'F_ACTION' => FLICKR_ADMIN.'-import&amp;action=import_set',
      ));
      
    // get piwigo categories
    $query = '
SELECT id, name, uppercats, global_rank
  FROM '.CATEGORIES_TABLE.'
;';
    display_select_cat_wrapper($query, array(), 'associate_options', true);
    display_select_cat_wrapper($query, array(), 'category_parent_options');
    break;
  }
  
  // success message after import
  case 'import_set':
  {
    if (isset($_POST['done']))
    {
      $_SESSION['page_infos'][] = sprintf(l10n('%d pictures imported'), $_POST['done']);
    }
    redirect(FLICKR_ADMIN.'-import');
  }
}

$template->assign('ACTION', $_GET['action']);

$template->set_filename('flickr2piwigo', dirname(__FILE__).'/template/import.tpl');

?>