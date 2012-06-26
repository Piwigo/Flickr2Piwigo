<?php
if (!defined('FLICKR_PATH')) die('Hacking attempt!');

global $template, $page, $conf;

$conf['flickr2piwigo'] = unserialize($conf['flickr2piwigo']);
load_language('plugin.lang', FLICKR_PATH);

if (!file_exists(FLICKR_FS_CACHE))
{
  mdir(FLICKR_FS_CACHE, 0755);
}

// tabsheet
include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');
$page['tab'] = (isset($_GET['tab'])) ? $_GET['tab'] : $page['tab'] = 'import';
  
$tabsheet = new tabsheet();
$tabsheet->add('import', l10n('Import'), FLICKR_ADMIN . '-import');
$tabsheet->add('config', l10n('Configuration'), FLICKR_ADMIN . '-config');
$tabsheet->select($page['tab']);
$tabsheet->assign();

// include page
include(FLICKR_PATH . 'admin/' . $page['tab'] . '.php');

// template
$template->assign(array(
  'FLICKR_PATH'=> FLICKR_PATH,
  'FLICKR_ABS_PATH'=> dirname(__FILE__).'/',
  'FLICKR_ADMIN' => FLICKR_ADMIN,
  ));
$template->assign_var_from_handle('ADMIN_CONTENT', 'flickr2piwigo');

?>