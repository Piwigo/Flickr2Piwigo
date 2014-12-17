{footer_script require='jquery.ajaxmanager,jquery.jgrowl'}
{* <!-- CATEGORIES --> *}
var categoriesCache = new CategoriesCache({
  serverKey: '{$CACHE_KEYS.categories}',
  serverId: '{$CACHE_KEYS._hash}',
  rootUrl: '{$ROOT_URL}'
});

categoriesCache.selectize(jQuery('[data-selectize=categories]'), {
  filter: function(categories, options) {
    if (categories.length > 0) {
      jQuery("#albumSelection").show();
    }
    
    return categories;
  }
});

jQuery('[data-add-album]').pwgAddAlbum({
  afterSelect: function() {
    jQuery("#albumSelection").show();
  }
});

(function($){
  /* global vars */
  var nb_thumbs_set = {$nb_thumbs_set};
  var all_elements = [{if !empty($all_elements)}{','|@implode:$all_elements}{/if}];
  var import_done = 0;
  var import_selected = 0;
  var queuedManager = $.manageAjax.create('queued', {ldelim}
    queue: true,
    maxRequests: 1
  });

  /* Shift-click: select all photos between the click and the shift+click */
  var last_clicked=0;
  var last_clickedstatus=true;
  $.fn.enableShiftClick = function() {
    var inputs = [];
    var count=0;
    var This=$(this);
    this.find('input[type=checkbox]').each(function() {
      var pos=count;
      inputs[count++]=this;
      $(this).bind("shclick", function (dummy,event) {
        if (event.shiftKey) {
          var first = last_clicked;
          var last = pos;
          if (first > last) {
            first=pos;
            last=last_clicked;
          }

          for (var i=first; i<=last;i++) {
            input = $(inputs[i]);
            $(input).attr('checked', last_clickedstatus);
            if (last_clickedstatus) {
              $(input).siblings("span.wrap2").addClass("thumbSelected");
            }
            else {
              $(input).siblings("span.wrap2").removeClass("thumbSelected");
            }
          }
        }
        else {
          last_clicked = pos;
          last_clickedstatus = this.checked;
        }
        return true;
      });
      $(this).click(function(event) { $(this).triggerHandler("shclick",event); });
    });
  }

  /* update displaying */
  function checkPermitAction() {
    var nbSelected = 0;
    if ($("input[name=setSelected]").is(':checked')) {
      nbSelected = nb_thumbs_set;
    }
    else {
      $(".thumbnails input[type=checkbox]").each(function() {
        if ($(this).is(':checked')) nbSelected++;
      });
    }

    if (nbSelected == 0) {
      $("#beginImport").hide();
    }
    else {
      $("#beginImport").show();
    }

    $("#applyOnDetails").text(
      sprintf(
        '{'on the %d selected photos'|translate|escape:javascript}',
        nbSelected
      )
    );

    // display the number of currently selected photos in the "Selection" fieldset
    if (nbSelected == 0) {
      $("#selectedMessage").text(
        sprintf(
          '{'No photo selected, %d photos in current set'|translate|escape:javascript}',
          nb_thumbs_set
        )
      );
    }
    else if (nbSelected == nb_thumbs_set) {
      $("#selectedMessage").text(
        sprintf(
          '{'All %d photos are selected'|translate|escape:javascript}',
          nb_thumbs_set
        )
      );
    }
    else {
      $("#selectedMessage").text(
        sprintf(
          '{'%d of %d photos selected'|translate|escape:javascript}',
          nbSelected,
          nb_thumbs_set
        )
      );
    }
  }

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
          jQuery.jGrowl(data['result'], {
            theme: 'success', life: 4000, sticky: false,
            header: '{'Success'|translate}'
          });
          jQuery("#photo-"+photo).fadeOut(function(){ $(this).remove(); });
        }
        else {
          jQuery.jGrowl(data['result'], {
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
        jQuery.jGrowl('{'an error happened'|translate|@escape:'javascript'}', {
          theme: 'error', sticky: true,
          header: '{'ERROR'|translate}'
        });
      }
    });
  }


  checkPermitAction();
  $("a.preview-box").colorbox();
  $('ul.thumbnails').enableShiftClick();

  /* tiptip */
  $('img.thumbnail').tipTip({
    'delay' : 0,
    'fadeIn' : 200,
    'fadeOut' : 200
  });

  /* thumbnail click */
  $(".wrap1 label").click(function(event) {
    $("input[name=setSelected]").prop('checked', false);

    var wrap2 = $(this).children(".wrap2");
    var checkbox = $(this).children("input[type=checkbox]");

    checkbox.triggerHandler("shclick",event);

    if ($(checkbox).is(':checked')) {
      $(wrap2).addClass("thumbSelected");
    }
    else {
      $(wrap2).removeClass('thumbSelected');
    }

    checkPermitAction();
  });

  /* select all */
  $("#selectAll").click(function() {
    $("input[name=setSelected]").prop('checked', false);

    $(".thumbnails label").each(function() {
      var wrap2 = $(this).children(".wrap2");
      var checkbox = $(this).children("input[type=checkbox]");

      $(checkbox).prop('checked', true);
      $(wrap2).addClass("thumbSelected");
    });

    checkPermitAction();
    return false;
  });

  /* select none */
  $("#selectNone").click(function() {
    $("input[name=setSelected]").prop('checked', false);

    $(".thumbnails label").each(function() {
      var wrap2 = $(this).children(".wrap2");
      var checkbox = $(this).children("input[type=checkbox]");

      $(checkbox).prop('checked', false);
      $(wrap2).removeClass("thumbSelected");
    });

    checkPermitAction();
    return false;
  });

  /* select invert */
  $("#selectInvert").click(function() {
    $("input[name=setSelected]").prop('checked', false);

    $(".thumbnails label").each(function() {
      var wrap2 = $(this).children(".wrap2");
      var checkbox = $(this).children("input[type=checkbox]");

      $(checkbox).prop('checked', !$(checkbox).is(':checked'));

      if ($(checkbox).is(':checked')) {
        $(wrap2).addClass("thumbSelected");
      }
      else {
        $(wrap2).removeClass('thumbSelected');
      }
    });

    checkPermitAction();
    return false;
  });

  /* select set */
  $("#selectSet").click(function() {
    $("input[name=setSelected]").prop('checked', true);

    $(".thumbnails label").each(function() {
      var wrap2 = $(this).children(".wrap2");
      var checkbox = $(this).children("input[type=checkbox]");

      $(checkbox).prop('checked', true);
      $(wrap2).addClass("thumbSelected");
    });

    checkPermitAction();
    return false;
  });

  /* begin import */
  jQuery('#beginImport').click(function() {
    $("#loader_import").fadeIn();
    var album = $('select[name=category] option:selected').val();

    var fills = '';
    $("input[name^='fill_']:checked").each(function() {
      fills+= $(this).attr("name") +',';
    });

    if (jQuery('input[name="setSelected"]').attr('checked')) {
      import_selected = all_elements.length;
      $("#progress").html("0/"+ import_selected);

      for (var i in all_elements) {
        performImport(all_elements[i], album, fills);
      }
		}
    else {
      import_selected = $("input[name='selection[]']:checked").length;
      $("#progress").html("0/"+ import_selected);

			jQuery("input[name='selection[]']:checked").each(function() {
        performImport(jQuery(this).attr('value'), album, fills);
      });
    }

    return false;
  });

  /* pagination loader */
  jQuery('#navigation a').click(function() {
    $("#loader_display").fadeIn();
  });
}(jQuery));
{/footer_script}


<div id="batchManagerGlobal">
<form action="{$F_ACTION}" method="post" id="import_form">

  <fieldset>
    <legend>{'Selection'|translate}</legend>

  {if !empty($thumbnails)}
    <p id="checkActions">
      {'Select:'|translate}
    {if $nb_thumbs_set > $nb_thumbs_page}
      <a href="#" id="selectAll">{'The whole page'|translate}</a>,
      <a href="#" id="selectSet">{'The whole set'|translate}</a>,
    {else}
      <a href="#" id="selectAll">{'All'|translate}</a>,
    {/if}
      <a href="#" id="selectNone">{'None'|translate}</a>,
      <a href="#" id="selectInvert">{'Invert'|translate}</a>

      <span id="selectedMessage"></span>
      <input type="checkbox" name="setSelected" style="display:none">
      <span id="loader_display" style="display:none;"><img src="admin/themes/default/images/ajax-loader.gif"> <i>{'Processing...'|translate}</i></span>
    </p>

    <ul class="thumbnails">
      {foreach from=$thumbnails item=thumbnail}
			<li id="photo-{$thumbnail.id}">
				<span class="wrap1">
					<label>
						<span class="wrap2">
						<div class="actions"><a href="{$thumbnail.src}" class="preview-box">{'Zoom'|translate}</a> &middot; <a href="{$thumbnail.url}" target="_blank" title="{'Open Flickr page in a new tab'|translate}">Flickr</a></div>
							<span>
								<img src="{$thumbnail.thumb}" alt="{$thumbnail.title}" title="{$thumbnail.title|escape:'html'}" class="thumbnail">
							</span>
						</span>
						<input type="checkbox" name="selection[]" value="{$thumbnail.id}">
					</label>
				</span>
			</li>
      {/foreach}
    </ul>


    <div style="clear:both;" id="navigation">
    {if !empty($navbar) }
      <div style="float:left">
      {include file='navigation_bar.tpl'|get_extent:'navbar'}
      </div>
    {/if}

      <div style="float:right;margin-top:10px;">{'display'|translate}
        <a href="{$U_DISPLAY}&amp;display=20">20</a>
        &middot; <a href="{$U_DISPLAY}&amp;display=50">50</a>
        &middot; <a href="{$U_DISPLAY}&amp;display=100">100</a>
        &middot; <a href="{$U_DISPLAY}&amp;display=all">{'all'|translate}</a>
        {'photos per page'|translate}
      </div>
    </div>

  {else}
    <div>{'No photo in the current set.'|translate}</div>
  {/if}
  </fieldset>

  <fieldset>
    <legend>{'Import options'|translate}</legend>

    <p>
      <label><b>{'Album'|translate}:</b></label>
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
    </p>

    <p>
      <input type="hidden" name="album" value="{$album}">
      <input type="submit" name="import_set" id="beginImport" value="{'Begin transfer'|translate}" style="display:none;">
      <span id="loader_import" style="display:none;"><img src="admin/themes/default/images/ajax-loader.gif"> <i>{'Processing...'|translate}</i> <span id="progress"></span></span>
    </p>
  </fieldset>
</form>
</div>