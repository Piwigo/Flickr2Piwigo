{combine_css path=$FLICKR_PATH|cat:'admin/template/style.css'}

{include file='include/colorbox.inc.tpl'}
{include file='include/add_album.inc.tpl'}
{combine_script id='common' load='footer' path='admin/themes/default/js/common.js'}
{combine_script id='jquery.ajaxmanager' load='footer' path='themes/default/js/plugins/jquery.ajaxmanager.js'}
{combine_script id='jquery.jgrowl' load='footer' require='jquery' path='themes/default/js/plugins/jquery.jgrowl_minimized.js'}
{combine_css path="themes/default/js/plugins/jquery.jgrowl.css"}
{combine_script id='LocalStorageCache' load='footer' path='admin/themes/default/js/LocalStorageCache.js'}
{combine_script id='jquery.selectize' load='footer' path='themes/default/js/plugins/selectize.min.js'}
{combine_css id='jquery.selectize' path="themes/default/js/plugins/selectize.{$themeconf.colorscheme}.css"}

<div class="titrePage">
	<h2>Flickr2Piwigo</h2>
</div>

<fieldset>
  <legend>{'Help'|translate}</legend>
  {$help}
</fieldset>

{* <!-- LOGIN --> *}
{if $ACTION == 'init_login'}
<p><input type="submit" onclick="javascript:window.location.href ='{$flickr_login}';" value="{'Login'|translate}"></p>

{* <!-- MAIN MENU --> *}
{else if $ACTION == 'main'}

{footer_script}
jQuery('input[type="submit"]').click(function() {
  window.location.href = $(this).attr("data");
});
jQuery('.load').click(function() {
  $("#loader_import").fadeIn();
});
{/footer_script}

<p>
  <b>{'Logged in as'|translate}</b> : <a href="{$profile_url}" target="_blank">{$username}</a><br><br>
  <input type="submit" data="{$logout_url}" value="{'Logout'|translate}">
</p>
<br>
<p>
  <input type="submit" data="{$list_albums_url}" class="load" value="{'List my albums'|translate}">
  <input type="submit" data="{$import_all_url}" class="load" value="{'Import all my pictures'|translate}">
</p>
<p id="loader_import" style="display:none;"><img src="admin/themes/default/images/ajax-loader.gif"> <i>{'Processing...'|translate}</i></p>

{* <!-- ALBUMS LIST --> *}
{else if $ACTION == 'list_albums'}

{footer_script}
jQuery('.load').click(function() {
  $("#loader_import").fadeIn();
});
{/footer_script}

<h3>{'%d albums'|translate:$total_albums}</h3>
<ul id="albumsList">
{foreach from=$albums item=album}
  <li {if $album.id == "not_in_set"}class="not_in_set"{/if}>
    <b><a href="{$album.U_LIST}" class="load">{$album.title}</a></b> <i>{'(%d photos)'|translate:$album.photos}</i>
    {if $album.description}- {$album.description|truncate:100}{/if}
  </li>
{/foreach}
</ul>
<p id="loader_import" style="display:none;"><img src="admin/themes/default/images/ajax-loader.gif"> <i>{'Processing...'|translate}</i></p>

{* <!-- PHOTOS LIST --> *}
{else if $ACTION == 'list_photos'}
{include file=$FLICKR_ABS_PATH|cat:'admin/template/import.list_photos.tpl'}

{* <!-- IMPORT ALL --> *}
{else if $ACTION == 'list_all'}
{include file=$FLICKR_ABS_PATH|cat:'admin/template/import.list_all.tpl'}

{/if}