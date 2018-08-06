<?php 
?>
<div class="wrap">
  <div id="icon-options-general" class="icon32">
    <br/>
  </div>
  <h2><?php echo $menu_title; ?></h2>
  <form method="post" action="options-general.php?page=phlegiscope">

    <input type="hidden" name="action" value="update"/>
    <h3>Global Plugin Settings</h3>
    <p>Set up how your server responds to LegiscopePH submissions.<br/>
    </p>
    <table class="form-table">
      <tbody>
        <!-- --!>
        <tr valign="top">
          <th scope="row">Database Name</th>
          <td>
            <fieldset>
            <legend class="screen-reader-text"><span>Allow an unregistered PHLegiscope extension user (i.e. one that does not have a <?php echo $menu_title; ?> account) to submit tracking reports.</span></legend>
              <label for="phlegiscope_allow_anonymous_submissions"><input name="phlegiscope_allow_anonymous_submissions" id="phlegiscope_allow_anonymous_submissions" <?php echo $phlegiscope_allow_anonymous_submissions; ?> type="checkbox">OPTION DESC</label>
              <p>Submissions by anonymous users and registered users differ in several ways.<br/>
 They are only notified of tracking activity on the item they've chosen that other also anonymous users submit.
</p>
            </fieldset>
          </td>
        </tr>
        <!-- --!>
        <tr valign="top">
          <th scope="row">Database Host</th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><span>OPTION DESC</span></legend>
              <label for="phlegiscope_option_2"><input name="phlegiscope_option_2" id="phlegiscope_option_2" <?php echo $phlegiscope_option_2; ?> type="checkbox">OPTION DESC</label>
              <p>OPTION NOTE</p>
            </fieldset>
          </td>
        </tr>
        <!-- --!>
        <tr valign="top">
          <th scope="row">OPTION SHORTDESC</th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><span>OPTION DESC</span></legend>
              <label for="phlegiscope_option_3"><input name="phlegiscope_option_3" id="phlegiscope_option_3" <?php echo $phlegiscope_option_3; ?> type="checkbox">OPTION DESC</label>
              <p>OPTION NOTE</p>
            </fieldset>
          </td>
        </tr>

        <!-- --!>
        <tr valign="top">
          <th scope="row">OPTION SHORTDESC</th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><span>OPTION DESC</span></legend>
              <label for="phlegiscope_option_4"><input name="phlegiscope_option_4" id="phlegiscope_option_4" <?php echo $phlegiscope_option_4; ?> type="checkbox">OPTION DESC</label>
              <p>OPTION NOTE</p>
            </fieldset>
          </td>
        </tr>

        <!-- --!>
        <tr valign="top">
          <th scope="row">OPTION SHORTDESC</th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><span>OPTION DESC</span></legend>
              <label for="phlegiscope_option_5"><input name="phlegiscope_option_5" id="phlegiscope_option_5" <?php echo $phlegiscope_option_5; ?> type="checkbox">OPTION DESC</label>
              <p>OPTION NOTE</p>
            </fieldset>
          </td>
        </tr>

        <!-- --!>
        <tr valign="top">
          <th scope="row">OPTION SHORTDESC</th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><span>OPTION DESC</span></legend>
              <label for="phlegiscope_option_6"><input name="phlegiscope_option_6" id="phlegiscope_option_6" <?php echo $phlegiscope_option_6; ?> type="checkbox">OPTION DESC</label>
              <p>OPTION NOTE</p>
            </fieldset>
          </td>
        </tr>


        <!-- --!>
        <tr valign="top">
          <th scope="row">Enable 'Client-Only' visibility</th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><span> OPTION DESC</span></legend>
              <label for="phlegiscope_client_visibility"><input name="phlegiscope_client_visibility" id="phlegiscope_client_visibility" <?php echo $phlegiscope_client_visibility; ?> type="checkbox">OPTION DESC</label>
              <p>This also enables the 'Clients and public subscribers' and 'Public subscribers only' options. <br/>The latter visibility option may be used to publish marketing posts, for example, that paid-up customers need not see.</p>
            </fieldset>
          </td>
        </tr>
        <tr valign="top">
          <th scope="row">Enable Client Categories</th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><span>Display categories of articles that are only visible to clients (i.e. who are recorded in the Client table).</span></legend>
              <label for="phlegiscope_client_categories"><input name="phlegiscope_client_categories" id="phlegiscope_client_categories" <?php echo $phlegiscope_client_categories; ?> type="checkbox">Display categories of articles that are only visible to clients (i.e. who are recorded in the Client table).</label>
              <p>Disabling client categories does not affect the display of posts that are already published; editors will simply not be able to see those special categories. Note that:</p>
<ul>
<li>* Postings belonging to client-only categories are only visible to clients. They are also marked as 'Client-Only'.</li>
<li>* Postings belonging to both client-only and regular WordPress categories visible to registered users as well as clients. They are also marked as 'Clients and public subscribers'.</li>
<li>* Postings that have visibility set to 'Clients and public subscribers' must belong to at least one Client-Only and one normal WordPress category.</li>
<li>* Editing the visibility of an article so that it is inconsistent with chosen article categories will generate a warning.</li>
</ul>
            </fieldset>
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">Enable custom client fields</th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><span>Allow creation of additional information fields that can be associated with each client login (i.e. client-specific codes used elsewhere in your enterprise)</span></legend>
              <label for="phlegiscope_custom_datafields"><input name="phlegiscope_custom_datafields" id="phlegiscope_custom_datafields" <?php echo $phlegiscope_custom_datafields; ?> type="checkbox">Allow creation of additional information fields that can be associated with each client login (i.e. client-specific codes used elsewhere in your enterprise)</label>
            </fieldset>
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">Client comment responders</th>
          <td>
            <fieldset>
              <legend class="screen-reader-text"><span>Enable multiple recipients for client comments.</span></legend>
              <label for="phlegiscope_client_responders"><input type="checkbox" name="phlegiscope_client_responders" id="phlegiscope_client_responders" <?php echo $phlegiscope_client_responders; ?>>Enable multiple recipients for client comments.</label><br/>
              <label for="phlegiscope_roundrobin_resp"><input type="checkbox" name="phlegiscope_roundrobin_resp" id="phlegiscope_roundrobin_resp" <?php echo $phlegiscope_roundrobin_resp; ?>>Distribute comments among all responders in round-robin fashion only (ignore client account managers list).</label>
            </fieldset>
          </td>
        </tr>
           
      </tbody>
    </table>

    <h3>Plugin Admin-Mode Behavior</h3>
    <table class="form-table">
      <tbody>
        <tr valign="top">
          <th scope="row">Menu item name</th>
          <td><input value="<?php echo $menu_title; ?>" name="phlegiscope_menutitle" id="phlegiscope_menutitle" class="regular-text code" type="text">
          <span class="description">Used in administrative menus.</span>
          </td>
        </tr>
      </tbody>
    </table>

    <p class="submit"><input name="submit" id="submit" class="button-primary" value="Save Changes" type="submit"></p>

  </form>
</div>
