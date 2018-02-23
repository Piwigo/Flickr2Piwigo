<?php
defined('FLICKR_PATH') or die('Hacking attempt!');

if (isset($_POST['save_config']))
{
  $conf['flickr2piwigo'] = array(
    'api_key' => trim($_POST['api_key']),
    'secret_key' => trim($_POST['secret_key']),
    );
  unset($_SESSION['phpFlickr_auth_token']);

  // Save the new API values and redirect back to the config page.
  conf_update_param('flickr2piwigo', $conf['flickr2piwigo']);
  $_SESSION['page_infos'][] = l10n('Information data registered in database');
  redirect(FLICKR_ADMIN . '-config');
}

$template->assign([
  'flickr2piwigo' => $conf['flickr2piwigo'],
  'FLICKR_HELP_CONTENT' => load_language('help_api_key.html', FLICKR_PATH, ['return'=>true]),
]);

// Add a warning if the key and secret are not filled yet.
if (empty($conf['flickr2piwigo']['api_key']) or empty($conf['flickr2piwigo']['secret_key']))
{
  $template->assign('warnings', l10n('Please enter your Flickr API keys'));
}

$template->set_filename('flickr2piwigo', realpath(FLICKR_PATH . 'admin/template/config.tpl'));
