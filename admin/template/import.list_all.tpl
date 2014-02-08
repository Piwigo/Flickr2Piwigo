{footer_script require='jquery.ajaxmanager,jquery.jgrowl'}
(function($){
  /* global vars */
  var all_elements = {$all_elements};
  var import_done = 0;
  var import_selected = {$nb_elements};
  var queuedManager = $.manageAjax.create('queued', {
    queue: true,
    maxRequests: 1
  });

  /* import queue */
  function performImport(photo, album, fills) {
    queuedManager.add({
      type: 'GET',
      dataType: 'json',
      url: 'ws.php',
      data: {
        method: 'pwg.images.addFlickr',
        id: photo,
        category: album,
        fills: fills,
        format: 'json'
      },
      success: function(data) {
        if (data['stat'] == 'ok') {
          $.jGrowl(data['result'], {
            theme: 'success', life: 4000, sticky: false,
            header: '{'Success'|translate}',
          });
          $("#photo-"+photo.id).fadeOut(function(){ $(this).remove(); });
        }
        else {
          $.jGrowl(data['result'], {
            theme: 'error', sticky: true,
            header: '{'ERROR'|translate}'
          });
        }

        import_done++;
        $("#progress").html(import_done +"/"+ import_selected);

        if (import_done == import_selected) {
          $("#import_form").append('<input type="hidden" name="done" value="' + import_done + '">');
          $("#import_form").submit();
        }
      },
      error: function(data) {
        $.jGrowl('{'an error happened'|translate|escape:'javascript'}', {
          theme: 'error', sticky: true,
          header: '{'ERROR'|translate}'
        });
      }
    });
  }

  /* begin import */
  $('#beginImport').click(function() {
    $("#loader_import").fadeIn();

    if ($("input[name='album_mode']:checked").val() == 'identical') {
      var album = 0;
    }
    else {
      var album = $("#albumSelect option:selected").val();
    }

    var fills = '';
    $("input[name^='fill_']:checked").each(function() {
      fills+= $(this).attr("name") +',';
    });

    import_selected = all_elements.length;
    $("#progress").html("0/"+ import_selected);

    for (var i in all_elements) {
      if (album == 0) this_album = all_elements[i]['albums'];
      else            this_album = album;

      performImport(all_elements[i]['id'], this_album, fills);
    }

    return false;
  });

  /* album mode */
  $("input[name='album_mode']").change(function() {
    if ($(this).val() == 'one_album') {
      $("#albumSelectWrapper").slideDown();
    }
    else {
      $("#albumSelectWrapper").slideUp();
    }
  });
}(jQuery));
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
      <label><input type="radio" name="album_mode" value="one_album"> {'Import all photos in this album'|translate} :</label>
    </p>

    <p id="albumSelectWrapper" style="display:none;">
      <select style="width:400px" name="associate" id="albumSelect" size="1">
        {html_options options=$category_parent_options}
      </select>
      {'... or '|translate}<a href="#" class="addAlbumOpen" title="{'create a new album'|translate}">{'create a new album'|translate}</a>
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
    </p>

    <p>
      <input type="submit" name="import_set" id="beginImport" value="{'Begin transfer'|translate}" {if not $nb_elements}style="display:none;"{/if}>
      <span id="loader_import" style="display:none;"><img src="admin/themes/default/images/ajax-loader.gif"> <i>{'Processing...'|translate}</i> <span id="progress"></span></span>
    </p>
  </fieldset>
</form>