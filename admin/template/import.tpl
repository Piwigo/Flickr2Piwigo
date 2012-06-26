{combine_css path=$FLICKR_PATH|@cat:"admin/template/style.css"}

<div class="titrePage">
	<h2>Flickr2Piwigo</h2>
</div>

{if $ACTION == 'init_login'}
<p><a href="{$flickr_login}">{'Login'|@translate}</a></p>

{elseif $ACTION == 'choice'}
<p>
  <a href="{$list_albums_url}">{'List my albums'|@translate}</a> -
  <a href="{$import_all_url}">{'Import all my pictures'|@translate}</a>
</p>

{elseif $ACTION == 'list_albums'}
<h3>{'%d albums'|@translate}|@sprintf:$total_albums}</h3>
<ul>
{foreach from=$albums item=album}
  <li>
    <b><a href="{$album.U_LIST}">{$album.title}</a></b> <i>{'(%d photos)'|@translate|@sprintf:$album.photos}</i> 
    {*- <a href="{$album.U_IMPORT_ALL}">{'Import all pictures of this album'|@translate}</a>*}
    {*<p>{$album.description}</p>*}
  </li>
{/foreach}
</ul>

{elseif $ACTION == 'list_photos'}
{include file=$FLICKR_ABS_PATH|@cat:'admin/template/import.list_photos.tpl'}

{elseif $ACTION == 'list_all'}
{include file=$FLICKR_ABS_PATH|@cat:'admin/template/import.list_all.tpl'}

{/if}