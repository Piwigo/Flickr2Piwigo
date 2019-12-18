$(function() {

    var queuedManager = $.manageAjax.create('queued', {
      queue: true,
      maxRequests: 1
    });

    var totalImported = 0;

    /** Whether to stop the process. Set by the 'stop' button. */
    var stop = false;

    /**
     * Import a single photo.
     */
  function flickr2piwigo_importPhoto(photoId, album, metadata) {
      queuedManager.add({
        type: 'GET',
        dataType: 'json',
        url: 'ws.php',
        data: {
          method: 'flickr2piwigo.importPhoto',
          id: photoId,
          category: album,
          fills: metadata,
          format: 'json'
        },
        success: function(data) {
          if (data['stat'] === 'ok') {
              $.jGrowl(data['result'], {
                theme: 'success',
                life: 4000,
                sticky: false,
                header: 'Success'
                });
          }
          else {
              $.jGrowl(data['result'], {
                theme: 'error',
                sticky: true,
                header: 'ERROR'
                });
          }

            totalImported++;
            $("#progress").html(totalImported+'/'+photosTotal);
          if (totalImported === photosTotal) {
              $("#loader_import").html(totalImported+" photos imported!");
          }
        },
        error: function(data) {
          if (data.statusText === 'abort') {
              $.jGrowl('The import has been stopped', {
                theme: 'success',
                sticky: true,
                header: 'Stopped'
                });
              $.manageAjax.destroy('queued');
              $('#beginImport').prop('disabled', false);
              queuedManager = $.manageAjax.create('queued', {
                queue: true,
                maxRequests: 1
              });
              totalImported = 0;
              stop = false;
              return;
          }
            var errorMsg = 'An error happened';
          if (data.responseText) {
              errorMsg += ': '+data.responseText;
          }
            $.jGrowl(errorMsg, {
              theme: 'error',
              sticky: true,
              header: 'ERROR'
              });
        }
        });
  }

    /**
     * Import a pageful of photos.
     * @param pageNum
     * @param album
     * @param metadata
     */
  function flickr2piwigo_importPhotos(pageNum, album, metadata) {
      queuedManager.add({
        type: 'GET',
        dataType: 'json',
        url: 'ws.php',
        data: {
          method: 'flickr2piwigo.allPhotos',
          user_id: flickrUserId,
          page: pageNum,
          format: 'json'
        },
        success: function (data) {
            // Give up if there's no result.
          if (!data.result || !data.result.photo) {
              return false;
          }
            // See if the stop button's been pressed.
          if (stop) {
              queuedManager.abort();
              return false;
          }
            $.each(data.result.photo, function (i, photoInfo) {
                flickr2piwigo_importPhoto(photoInfo.id, album, metadata);
                // Check again if we should stop.
              if (stop) {
                  queuedManager.abort();
                  return false;
              }
            });
          if (pageNum < data.result.pages) {
              pageNum++;
              flickr2piwigo_importPhotos(pageNum, album, metadata);
          }
        }
        });
  }

    /*
     * Run the import.
     */
    $('#beginImport').click(function() {
        // Set up the progress indicator.
        $(this).prop('disabled', true);
        $("#loader_import").fadeIn();
        $("#progress").html('0/' + photosTotal);

        // Determine the album ID (or null if we're to replicate the albums of Flickr).
        var album;
      if ($("input[name='album_mode']:checked").val() === 'one_album') {
          album = $('select[name=category] option:selected').val()
      }

        // Determine the metadata that will be copied to Piwigo.
        var metadata = '';
        $("input[name^='fill_']:checked").each(function() {
            metadata += $(this).attr("name")+',';
        });

        // Kick off the recursive page-by-page getting of photos.
        flickr2piwigo_importPhotos(1, album, metadata);

        return false;
    });
    $('button.stop').on('click', function() {
        stop = true;
        queuedManager.abort();
        $("#loader_import").fadeOut();
    });

    /*
     * Set up the Pwigo album selector.
     */
    var categoriesCache = new CategoriesCache({
      serverKey: cacheKeysCategories,
      serverId: cacheKeysHash,
      rootUrl: rootUrl
    });
    categoriesCache.selectize(jQuery('[data-selectize=categories]'), {
      filter: function(categories, options) {
        if (categories.length > 0) {
            jQuery("#albumSelection").show();
        }
          return categories;
      }
    });
    $('[data-add-album]').pwgAddAlbum({
      cache: categoriesCache,
      afterSelect: function() {
          jQuery("#albumSelection").show();
      }
    });

    /*
     * Hide or show the Piwigo album selector.
     */
    $("input[name='album_mode']").change(function() {
      if ($(this).val() === 'one_album') {
          $("#albumSelectWrapper").slideDown();
      } else {
          $("#albumSelectWrapper").slideUp();
      }
    });

});
