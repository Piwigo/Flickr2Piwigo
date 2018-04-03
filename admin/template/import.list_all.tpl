{footer_script}

var cacheKeysCategories = '{$CACHE_KEYS.categories}';
var cacheKeysHash = '{$CACHE_KEYS._hash}';
var rootUrl = '{$ROOT_URL}';
var photosTotal = {$nb_elements};
var flickrUserId = '{$flickrUserId}';

{/footer_script}

<form action="{$F_ACTION}" method="post" id="import_form">

  <fieldset>
    <legend>{'Selection'|translate}</legend>

  {if $nb_elements}
    {'%d elements ready for importation'|translate:$nb_elements}
  {else}
    <div>{'No photo in the current set.'|translate}</div>
  {/if}
  </fieldset>

  <fieldset>
    <legend>{'Import options'|translate}</legend>

    <p>
      <label><input type="radio" name="album_mode" value="identical" checked="checked"> {'Reproduce flickr albums'|translate}</label><br>
      <label><input type="radio" name="album_mode" value="one_album"> {'Import all photos in this album'|translate}:</label>
    </p>

    <p id="albumSelectWrapper" style="display:none;">
      <span id="albumSelection" style="display:none">
      <select data-selectize="categories" data-default="first" name="category" style="width:600px"></select>
      <br>{'... or '|@translate}</span>
      <a href="#" data-add-album="category" title="{'create a new album'|@translate}">{'create a new album'|@translate}</a>
    </p>

    <p>
      <b>{'Fill these fields from Flickr datas'|translate}:</b>
      <label><input type="checkbox" name="fill_name" checked="checked"> {'Photo name'|translate}</label>
      <label><input type="checkbox" name="fill_author" checked="checked"> {'Author'|translate}</label>
      <label><input type="checkbox" name="fill_tags" checked="checked"> {'Tags'|translate}</label>
      <label><input type="checkbox" name="fill_taken" checked="checked"> {'Creation date'|translate}</label>
      <label><input type="checkbox" name="fill_posted"> {'Post date'|translate}</label>
      <label><input type="checkbox" name="fill_description" checked="checked"> {'Description'|@translate}</label>
      <label><input type="checkbox" name="fill_geotag" checked="checked"> {'Geolocalization'|@translate}</label>
      <label><input type="checkbox" name="fill_level" checked="checked"> {'Privacy level'|@translate}</label>
      <label><input type="checkbox" name="fill_safety" checked="checked"> {'Safety level (as keyword)'|@translate}</label>
    </p>

    <p>
      <button type="button" name="import_set" id="beginImport" {if not $nb_elements}style="display:none;"{/if}>{'Begin transfer'|translate}</button>
    </p>
    <p id="loader_import" style="display:none;">
      <img src="admin/themes/default/images/ajax-loader.gif"> <i>{'Processing...'|translate}</i> <span id="progress"></span>
      <button type="button" class="stop">{'Stop'|translate}</button>
    </p>
  </fieldset>
</form>
