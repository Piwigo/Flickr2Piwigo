<?php
defined('FLICKR_PATH') or die('Hacking attempt!');

set_time_limit(600);

include_once(FLICKR_PATH . 'include/functions.inc.php');
include_once(FLICKR_PATH . 'vendor/autoload.php');

use OAuth\Common\Storage\Session;

// check API parameters and connect to flickr
if (empty($conf['flickr2piwigo']['api_key']) or empty($conf['flickr2piwigo']['secret_key']))
{
  $_SESSION['page_warnings'][] = l10n('Please enter your Flickr API keys');
  redirect(FLICKR_ADMIN . '-config');
}
else if (!test_remote_download())
{
  $page['errors'][] = l10n('No download method available');
  $_GET['action'] = 'error';
}
else
{
  $flickr = get_PhpFlickr();

  if ( isset($_GET['action']) and in_array($_GET['action'], ['login', 'logged'])) {
    // Set up session storage for use while logging in.
    $storage = new Session();
    $flickr->setOauthStorage($storage);
  } else {
    // If we're not logging in, test authentication.
    $u = $flickr->test_login();
    if (!$u && !isset($_GET['action'])) {
      $_GET['action'] = 'init_login';
    }
  }

}


if (!isset($_GET['action']))
{
  $_GET['action'] = 'main';
}


switch ($_GET['action'])
{
  // button to login page
  case 'init_login':
  {
    $template->assign('flickr_login', FLICKR_ADMIN . '-import&amp;action=login');
    break;
  }

  // call flickr login procedure
  case 'login':
  {
    $callbackUrl = get_absolute_root_url() . FLICKR_ADMIN . '-import&action=logged';
    $flickrUrl = $flickr->getAuthUrl('read', $callbackUrl);
    redirect($flickrUrl->getAbsoluteUri());
    break;
  }

  // message after login
  case 'logged':
  {
    // Get the access token.
    $oauthVerifier = $_GET[ 'oauth_verifier' ];
    $oauthToken = $_GET[ 'oauth_token' ];
    try
    {
      $accessToken = $flickr->retrieveAccessToken($oauthVerifier, $oauthToken);
    } catch (Exception $e) {
      $_SESSION['page_warnings'][] = l10n('Unable to retrieve Flickr access token. The error was:').$e->getMessage();
      redirect(FLICKR_ADMIN . '-import');
    }

    // Save the access token.
    $conf['flickr2piwigo']['access_token'] = $accessToken->getAccessToken();
    $conf['flickr2piwigo']['access_secret'] = $accessToken->getAccessTokenSecret();
    conf_update_param('flickr2piwigo', $conf['flickr2piwigo']);

    $_SESSION['page_infos'][] = l10n('Successfully logged in to you Flickr account');
    redirect(FLICKR_ADMIN . '-import');
    break;
  }

  // logout
  case 'logout':
  {
    unset($_SESSION['phpFlickr_auth_token']);
    unset($conf['flickr2piwigo']['access_token']);
    unset($conf['flickr2piwigo']['access_secret']);
    conf_update_param('flickr2piwigo', $conf['flickr2piwigo']);
    $_SESSION['page_infos'][] = l10n('Logged out');
    redirect(FLICKR_ADMIN . '-import');
    break;
  }

  // main menu
  case 'main':
  {
    $u = $flickr->people_getInfo($u['id']);
    $template->assign(array(
      'username' => $u['username'],
      'profile_url' => $u['profileurl'],
      'logout_url' => FLICKR_ADMIN . '-import&amp;action=logout',
      'list_albums_url' => FLICKR_ADMIN . '-import&amp;action=list_albums',
      'import_all_url' => FLICKR_ADMIN . '-import&amp;action=list_all',
      ));
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
      $album['U_LIST'] = FLICKR_ADMIN . '-import&amp;action=list_photos&amp;album='.$album['id'];
    }
    unset($album);

    // not classed
    $wo_albums = $flickr->photos_getNotInSet(NULL, NULL, NULL, NULL, 'photos', NULL, NULL, 1);
    if ($wo_albums['photos']['total'] > 0)
    {
      $albums[] = array(
        'id' => 'not_in_set',
        'title' => l10n('Pictures without album'),
        'description' => null,
        'photos' => $wo_albums['photos']['total'],
        'U_LIST' => FLICKR_ADMIN . '-import&amp;action=list_photos&amp;album=not_in_set',
        );
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
    $self_url = FLICKR_ADMIN . '-import&amp;action=list_photos&amp;album='.$_GET['album'];
    $flickr_prefix = 'flickr-'.$u['username'].'-';
    $flickr_root_url = $flickr->urls_getUserPhotos($u['id']);

    // pagination
    if (isset($_GET['start']))   $page['start'] = intval($_GET['start']);
    else                         $page['start'] = 0;
    if (isset($_GET['display'])) $page['display'] = $_GET['display']=='all' ? 500 : intval($_GET['display']);
    else                         $page['display'] = 20;

    // get photos
    if ($_GET['album'] == 'not_in_set')
    {
      $all_photos = $flickr->photos_getNotInSet(NULL, NULL, NULL, NULL, 'photos', NULL, NULL, 500);
      $all_photos = $all_photos['photos']['photo'];
    }
    else
    {
      $all_photos = $flickr->photosets_getPhotos($_GET['album'], 'url_m, url_t', NULL, 500, NULL, 'photos');
      $all_photos = $all_photos['photoset']['photo'];
    }

    // get existing photos
    $query = '
SELECT id, file
  FROM '.IMAGES_TABLE.'
  WHERE file LIKE "'.$flickr_prefix.'%"
;';
    $existing_photos = simple_hash_from_query($query, 'id', 'file');
    $existing_photos = array_map(create_function('$p', 'return preg_replace("#^'.$flickr_prefix.'([0-9]+)\.([a-z]{3,4})$#i", "$1", $p);'), $existing_photos);

    // remove existing photos
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
      $page['infos'][] = '<a href="admin.php?page=batch_manager&amp;filter=prefilter-flickr">'
          .l10n_dec(
            'One picture is not displayed because already existing in the database.',
            '%d pictures are not displayed because already existing in the database.',
            $duplicates)
        .'</a>';
    }

    // displayed photos
    $page_photos = array_slice($all_photos, $page['start'], $page['display']);
    $all_elements = array_map(create_function('$p', 'return  \'"\'.$p["id"].\'"\';'), $all_photos);

    foreach ($page_photos as &$photo)
    {
      $photo['thumb'] = $photo['url_t'];
      $photo['src'] = $photo['url_m'];
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

  // list all photos of the user
  case 'list_all':
  {
    $flickr_prefix = 'flickr-'.$u['username'].'-';

    // get all photos in all albums
    $all_albums = $flickr->photosets_getList($u['id']);
    $all_albums = $all_albums['photoset'];

    $all_photos = array();
    foreach ($all_albums as $album)
    {
      $album_photos = $flickr->photosets_getPhotos($album['id'], NULL, NULL, 500, NULL, 'photos');
      $album_photos = $album_photos['photoset']['photo'];

      foreach ($album_photos as $photo)
      {
        $all_photos[ $photo['id'] ][] = $album['title'];
      }
    }

    // get existing photos
    $query = '
SELECT id, file
  FROM '.IMAGES_TABLE.'
  WHERE file LIKE "'.$flickr_prefix.'%"
;';
    $existing_photos = simple_hash_from_query($query, 'id', 'file');
    $existing_photos = array_map(create_function('$p', 'return preg_replace("#^'.$flickr_prefix.'([0-9]+)\.([a-z]{3,4})$#i", "$1", $p);'), $existing_photos);

    // remove existing photos
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
    $all_photos = array_values($all_photos);

    if ($duplicates>0)
    {
      $page['infos'][] = '<a href="admin.php?page=batch_manager&amp;filter=prefilter-flickr">'
          .l10n_dec(
            'One picture is not displayed because already existing in the database.',
            '%d pictures are not displayed because already existing in the database.',
            $duplicates)
        .'</a>';
    }

    $template->assign(array(
      'nb_elements' => count($all_photos),
      'all_elements' => json_encode($all_photos),
      'F_ACTION' => FLICKR_ADMIN . '-import&amp;action=import_set',
      ));

    // get piwigo categories
    $query = '
SELECT id, name, uppercats, global_rank
  FROM '.CATEGORIES_TABLE.'
;';
    display_select_cat_wrapper($query, array(), 'category_parent_options');
    break;
  }

  // success message after import
  case 'import_set':
  {
    if (isset($_POST['done']))
    {
      $_SESSION['page_infos'][] = l10n('%d pictures imported', $_POST['done']);
    }
    redirect(FLICKR_ADMIN . '-import');
  }
}


$template->assign('ACTION', $_GET['action']);

$template->set_filename('flickr2piwigo', realpath(FLICKR_PATH . 'admin/template/import.tpl'));
