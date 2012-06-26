{combine_css path=$FLICKR_PATH|@cat:"admin/template/style.css"}

<div class="titrePage">
	<h2>Flickr2Piwigo</h2>
</div>

{if $ACTION == 'init_login'}
<p><input type="submit" onClick="javascript:window.location.href ='{$flickr_login}';" value="{'Login'|@translate}"></p>

{elseif $ACTION == 'main'}
<p>
  <b>{'Logued as'|@translate}</b> : <a href="{$profile_url}" target="_blank">{$username}</a><br><br>
  <input type="submit" onClick="javascript:window.location.href ='{$logout_url}';" value="{'Logout'|@translate}">
</p>
<br>
<p>
  <input type="submit" onClick="javascript:window.location.href ='{$list_albums_url}';" value="{'List my albums'|@translate}">
  <input type="submit" onClick="javascript:window.location.href ='{$import_all_url}';" value="{'Import all my pictures'|@translate}">
</p>

{elseif $ACTION == 'list_albums'}
<h3>{'%d albums'|@translate|@sprintf:$total_albums}</h3>
<ul id="albumsList">
{foreach from=$albums item=album}
  <li {if $album.id == "not_in_set"}class="not_in_set"{/if}>
    <b><a href="{$album.U_LIST}">{$album.title}</a></b> <i>{'(%d photos)'|@translate|@sprintf:$album.photos}</i> {if $album.description}- {$album.description|@truncate:100}{/if}
    {*- <a href="{$album.U_IMPORT_ALL}">{'Import all pictures of this album'|@translate}</a>*}
  </li>
{/foreach}
</ul>

{elseif $ACTION == 'list_photos'}
{include file=$FLICKR_ABS_PATH|@cat:'admin/template/import.list_photos.tpl'}

{elseif $ACTION == 'list_all'}
{include file=$FLICKR_ABS_PATH|@cat:'admin/template/import.list_all.tpl'}

{/if}