<link rel="stylesheet" href="<?php echo WT_MODULES_DIR . $this->getName(); ?>/facebook.css?v=<?php echo  WT_FACEBOOK_VERSION; ?>" />
<h4><?php echo WT_I18N::translate('Facebook API'); ?></h4>
<form method="post" action="">
  <p><?php echo WT_I18N::translate('The App ID and secret can be setup at %s.', '<a href="https://developers.facebook.com/apps">https://developers.facebook.com/apps</a>'); ?></p>
  <label>
    <?php echo WT_I18N::translate('App ID:'); ?>
    <input type="text" name="app_id" value="<?php echo get_module_setting($mod_name, 'app_id', ''); ?>" />
  </label>
  <label>
    <?php echo WT_I18N::translate('App Secret:'); ?>
    <input type="text" name="app_secret" value="<?php echo get_module_setting($mod_name, 'app_secret', ''); ?>" size="40" />
  </label>
  <p>
    <label>
      <input type="checkbox" name="require_verified" value="1"<?php echo (get_module_setting($mod_name, 'require_verified', 1) ? 'checked="checked" ' : ''); ?> />
      <?php echo WT_I18N::translate('Require verified Facebook accounts'); ?>
      <em>(<?php echo WT_I18N::translate('Only disable for testing'); ?>)</em>
    </label>
  </p>
  <p><input type="submit" name="saveAPI" value="<?php echo WT_I18N::translate('Save'); ?>"></p>
</form>

<hr/>

<h4><?php echo WT_I18N::translate('Linked users');?></h4>
<form method="post" action="">
  <p><?php echo WT_I18N::translate("Associate a webtrees user with a Facebook account."); ?></p>
<table>
  <thead>
    <tr>
      <th><?php echo WT_I18N::translate('webtrees Username'); ?></th>
      <th><?php echo WT_I18N::translate('Real name'); ?></th>
      <th><?php echo WT_I18N::translate('Facebook Account'); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php
      if (!empty($linkedUsers)) {
        foreach ($linkedUsers as $user_id => $user) {
          echo '
    <tr>
      <td><a href="admin_users.php?filter='.$user->user_name.'">'.$user->user_name.'</a></td>
      <td><a href="admin_users.php?filter='.$user->user_name.'">'.$user->real_name.'</a></td>
      <td><a href="https://www.facebook.com/'.$user->facebook_username.'">'.$user->facebook_username.'</a></td>
      <td><button name="deleteLink" value="'.$user_id.'" class="icon-delete" style="border:none;"></button></td>
    </tr>';
        }
      }
    ?>
    <tr>
      <td colspan="2"><select name="user_id"><?php echo $unlinkedOptions; ?></select></td>
      <td><input type="text" name="facebook_username"/></td>
      <td><input type="submit" name="addLink" value="<?php echo WT_I18N::translate('Add'); ?>"></td>
    </tr>
  </tbody>
</table>
</form>

<hr/>

<h4><?php echo WT_I18N::translate('Pre-approve users'); ?></h4>
<form method="post" action="">
  <p><?php echo WT_I18N::translate("If you know a user's Facebook username but they don't have an account in webtrees, you can pre-approve one so they can login immediately the first time they visit."); ?></p>
<table>
  <thead>
    <tr>
      <th><?php echo WT_I18N::translate('Facebook Account'); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php
      if (!empty($preApproved)) {
        foreach ($preApproved as $fbUsername => $details) {
          echo '
<tr>
      <td><a href="https://www.facebook.com/'.$fbUsername.'">'.$fbUsername.'</a></td>
      <td><button name="deletePreapproved" value="'.$fbUsername.'" class="icon-delete"></button></td>
    </tr>';
        }
      }
    ?>
    <tr>
      <td><input type="text" name="facebook_username"/></td>
      <td><input type="submit" name="savePreapproved" value="<?php echo WT_I18N::translate('Add'); ?>"></td>
    </tr>
  </tbody>
</table>
</form>
