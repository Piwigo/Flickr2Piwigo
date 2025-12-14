# Flickr2Piwigo

* Internal name: `flickr2piwigo` (directory name in `plugins/`)
* Plugin page: https://piwigo.org/ext/extension_view.php?eid=612
* Translation: https://piwigo.org/translate/project.php?project=flickr

## Development status

In February 2018 [Sam Wilson](https://samwilson.id.au) became a co-maintainer of this plugin.

## Release procedure

1. Update version number in `main.inc.php`.
2. Run `composer update --no-dev -o` to get the `vendor/` directory prepared.
3. Zip up the entire `flickr2piwigo` directory into a file named `flickr2piwigo_x-y-z.zip`,
   with `flickr2piwigo` as the top-level directory within the zip.
4. Upload the zip file to the plugin's page on piwigo.org.
