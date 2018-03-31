<?php
defined('FLICKR_PATH') or die('Hacking attempt!');

function flickr_add_ws_method($arr)
{
  /** @var PwgServer $service */
  $service = &$arr[0];

  $service->addMethod(
    'flickr2piwigo.allPhotos', 
    'ws_flickr2piwigo_allPhotos',
    [
      'user_id' => [],
      'page' => [ 'type' => WS_TYPE_INT, 'default' => 1 ],
    ],
    'Get all photos by a given user.',
    null,
    ['hidden' => true]
  );

  $service->addMethod(
    'flickr2piwigo.importPhoto',
    'ws_flickr2piwigo_importPhoto',
    [
      'id' => [],
      'category' => ['default' => ''],
      'fills' => ['default' => ''],
    ],
    'Import a single Flickr photo along with its metadata.',
    null,
    ['hidden' => true]
  );
}

function flickr2piwigo_ws_init() {
  if (!is_admin())
  {
    return new PwgError(403, 'Forbidden');
  }

  global $conf;

  if (empty($conf['flickr2piwigo']['api_key']) or empty($conf['flickr2piwigo']['secret_key']))
  {
    return new PwgError(null, l10n('Please enter your Flickr API keys on the configuration tab'));
  }

  include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
  include_once(PHPWG_ROOT_PATH . 'admin/include/functions_upload.inc.php');
  include_once(FLICKR_PATH . 'include/functions.inc.php');

  if (test_remote_download() === false)
  {
    return new PwgError(null, l10n('No download method available'));
  }

  return get_PhpFlickr();
}

/**
 * Get a list of all photos (page-by-page).
 * @param $params
 * @return bool|string[]
 */
function ws_flickr2piwigo_allPhotos($params) {
  $flickr = flickr2piwigo_ws_init();
  $page = $params['page'];
  $userId = $params['user_id'];
  $photos = $flickr->people()->getPhotos(
    $userId, null, null, null,
    null, null, null, null, null, 500, $page
  );
  return $photos;
}

/**
 * @param $photo
 * @return PwgError|string
 */
function ws_flickr2piwigo_importPhoto($params) {
  global $logger;
  $flickr = flickr2piwigo_ws_init();

  // Get the photo's info.
  $logger->debug('Attempting to import Flickr photo '.$params['id'], FLICKR2PIWIGO);
  $photo = $flickr->photos()->getInfo($params['id']);
  if (!$photo['id']) {
    $logger->error( 'Unable to get info from Flickr about photo: '.$params['id'], FLICKR2PIWIGO );
    return l10n('Unable to get info from Flickr about photo: %s', $params['id']);
  }
  $filename_prefix = 'flickr-'.$photo['id'];

  // See if the photo already exists.
  $exists_query = 'SELECT id, file FROM '.IMAGES_TABLE.' WHERE file LIKE "'.pwg_db_real_escape_string($filename_prefix).'%";';
  $exists = pwg_query($exists_query);
  if (pwg_db_num_rows($exists))
  {
    $piwigo_file = pwg_db_fetch_array($exists);
    $logger->debug( 'Photo already imported (determined by filename) with Piwigo ID: '.$piwigo_file['id'], FLICKR2PIWIGO );
    return l10n('Already imported: %s (Piwigo ID: %s)', $photo['title'], $piwigo_file['id']);
  }
  $logger->debug( 'No duplicate filename found for: '.$filename_prefix, FLICKR2PIWIGO );

  // Download largest available size. If not original, this needs an extra API request.
  if (isset($photo['originalsecret']) && isset($photo['originalformat'])) {
    $photo['url'] = $flickr->buildPhotoURL($photo, 'original');
  } else {
    $photo['url'] = $flickr->photos()->getLargestSize($photo['id'])['source'];
  }
  $photo['path'] = FLICKR_FS_CACHE.$filename_prefix.'.'.get_extension($photo['url']);
  if (download_remote_file($photo['url'], $photo['path']) == false)
  {
    $logger->error( 'Unable to download file: '.$photo['url'].' to '.$photo['path'], FLICKR2PIWIGO );
    return l10n("Can't download file: %s", $photo['url']);
  }

  // See if it already exists (by checksum).
  $md5sum = md5_file($photo['path']);
  $exists_checksum_query = 'SELECT id, file FROM '.IMAGES_TABLE.' WHERE md5sum = "'.pwg_db_real_escape_string($md5sum).'";';
  $exists_checksum = pwg_query($exists_checksum_query);
  if (pwg_db_num_rows($exists_checksum))
  {
    // Delete the downloaded Flickr file and report success.
    unlink($photo['path']);
    $checksum_info = pwg_db_fetch_array($exists_checksum);
    $logger->debug( 'Photo already imported (determined by checksum) with Piwigo ID: '.$checksum_info['id'], FLICKR2PIWIGO);
    return l10n('Already imported: %s (Piwigo ID: %s)', $photo['title'], $checksum_info['id']);
  }

  if (is_numeric($params['category']))
  {
    // Either the provided category is a Piwigo album ID...
    $logger->info('Using provided album ID: '.$params['category'], FLICKR2PIWIGO);
    $photo['category'] = [$params['category']];
  }
  else
  {
    // ...or we ignore it an query for all of the photo's albums on Flickr.
    $photosets = $flickr->photos()->getSets([$photo['id']]);
    $logger->info(count($photosets).' albums found on Flickr', FLICKR2PIWIGO);

    $photo['category'] = [];
    foreach ($photosets as $photoset_info)
    {
      $photoset_search_title = substr(strtolower( $photoset_info['title'] ), 0, 255 );
      $query = 'SELECT id FROM '.CATEGORIES_TABLE.' WHERE LOWER(name) = "'.pwg_db_real_escape_string($photoset_search_title).'";';
      $result = pwg_query($query);

      if (pwg_db_num_rows($result))
      {
        list($cat_id) = pwg_db_fetch_row($result);
        $photo['category'][] = $cat_id;
      }
      else
      {
        $logger->info('Creating category: '.$photoset_info['title'], FLICKR2PIWIGO);
        $cat = create_virtual_category(pwg_db_real_escape_string($photoset_info['title']));
        if ( !isset( $cat['id'] ) ) {
          $cat_error_msg = l10n('Unable to create category: %s', $photoset_info['title']);
          $logger->error( $cat_error_msg, FLICKR2PIWIGO );
          return $cat_error_msg;
        }
        $logger->debug( 'Category created', FLICKR2PIWIGO);
        $photo['category'][] = $cat['id'];
      }
    }
  }

  // add photo
  $photo['image_id'] = add_uploaded_file($photo['path'], basename($photo['path']), $photo['category']);

  // Update metadata if required.
  if (!empty($params['fills']))
  {
    $photo['fills'] = rtrim($params['fills'], ',');
    $photo['fills'] = explode(',', $photo['fills']);

    $updates = array();
    if (in_array('fill_name', $photo['fills']))   $updates['name'] = pwg_db_real_escape_string($photo['title']);
    if (in_array('fill_posted', $photo['fills'])) $updates['date_available'] = date('Y-m-d H:i:s', $photo['dates']['posted']);
    if (in_array('fill_taken', $photo['fills']))  $updates['date_creation'] = $photo['dates']['taken'];
    if (in_array('fill_author', $photo['fills'])) $updates['author'] = pwg_db_real_escape_string($photo['owner']['username']);
    if (in_array('fill_description', $photo['fills']) && isset($photo['description']))
    {
      $updates['comment'] = pwg_db_real_escape_string($photo['description']);
    }
    if (in_array('fill_geotag', $photo['fills']) and !empty($photo['location']) )
    {
      $updates['latitude'] = pwg_db_real_escape_string($photo['location']['latitude']);
      $updates['longitude'] = pwg_db_real_escape_string($photo['location']['longitude']);
    }
    if (in_array('level', $photo['fills']) && !$photo['visibility']['ispublic'])
    {
      $updates['level'] = 8;
      if ($photo['visibility']['isfamily']) $updates['level'] = 4;
      if ($photo['visibility']['isfriend']) $updates['level'] = 2;
    }
    if (count($updates))
    {
      $logger->debug('Updating metadata of Piwigo ID: '.$photo['image_id'], FLICKR2PIWIGO);
      single_update(IMAGES_TABLE, $updates, ['id' => $photo['image_id']]);
    }

    if (!empty($photo['tags']['tag']) and in_array('fill_tags', $photo['fills']))
    {
      $tag_ids = [];
      foreach ($photo['tags']['tag'] as $tag) {
        $tag_ids[] = tag_id_from_tag_name(pwg_db_real_escape_string($tag['raw']));
      }
      $logger->debug('Updating tags of Piwigo ID: '.$photo['image_id'], FLICKR2PIWIGO);
      set_tags($tag_ids, $photo['image_id']);
    }
  }

  $logger->info('Import complete', FLICKR2PIWIGO);
  return l10n('Photo "%s" imported', $photo['title']);
}
