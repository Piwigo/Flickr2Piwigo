{combine_css path=$FLICKR_PATH|cat:'admin/template/style.css'}

<div class="titrePage">
	<h2>Flickr2Piwigo</h2>
</div>

<form method="post" action="" class="properties">
<fieldset>
  <legend>{'Flickr logins'|translate}</legend>

  <ul>
    <li>
      <label>
        <span class="property">{'API key'|translate}</span>
        <input type="text" name="api_key" value="{$flickr2piwigo.api_key}" size="40">
      </label>
    </li>

    <li>
      <label>
        <span class="property">{'API secret'|translate}</span>
        <input type="text" name="secret_key" value="{$flickr2piwigo.secret_key}" size="20">
      </label>
    </li>
  </ul>
</fieldset>

<p><input type="submit" name="save_config" value="{'Save Settings'|translate}"></p>

<fieldset>
  <legend>{'How do I get my Flickr API key?'|translate}</legend>

  {$FLICKR_HELP_CONTENT}
</fieldset>

</form>
