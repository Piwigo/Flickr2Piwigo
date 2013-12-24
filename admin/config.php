<?php
defined('FLICKR_PATH') or die('Hacking attempt!');

if (isset($_POST['save_config']))
{
  $conf['flickr2piwigo'] = array(
    'api_key' => trim($_POST['api_key']),
    'secret_key' => trim($_POST['secret_key']),
    );
  unset($_SESSION['phpFlickr_auth_token']);
  conf_update_param('flickr2piwigo', serialize($conf['flickr2piwigo']));
  $page['infos'][] = l10n('Information data registered in database');
}


$template->assign(array(
  'flickr2piwigo' => $conf['flickr2piwigo'],
  'FLICKR_HELP_CONTENT' => load_language('help_api_key.html', FLICKR_PATH, array('return'=>true)),
  'FLICKR_CALLBACK' => get_absolute_root_url() . FLICKR_ADMIN . '-import',
  ));


$template->set_filename('flickr2piwigo', realpath(FLICKR_PATH . 'admin/template/config.tpl'));
