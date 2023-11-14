<?php 
$currentUser = wp_get_current_user();
$currentUserId = $currentUser->ID;
$userAttMode = get_user_meta( $currentUser->ID, 'purchase_attribution_mode', true);
?>
<button class="wiz-button gray module-settings" "><i class=" fa-solid fa-cog"></i></button>
<div id="module-settings-dropdown" class="dropdown-menu">
    <form id="module-settings-form">
        <div class="form-group" id="purchase-attribution">
            <h5>Purchase Attribution</h5>
            <div class="input-group">
                <label>
                    <div class="radio-item">
                        <input <?php if (!$userAttMode || $userAttMode == 'campaign-id') {echo 'checked ';} ?>
                        type="radio" class="purchase-attribution" name="purchase-attribution" value="campaign-id"> Campaign ID
                        Match (default)
                    </div>

                    <div class="input-group-description">
                        Purchases are attributed when the campaign ID(s) match, regardless of
                        channel.
                    </div>
                </label>
            </div>
            <div class="input-group">
                <label>
                    <div class="radio-item">
                        <input <?php if ($userAttMode == 'broad-channel-match') {echo 'checked ';} ?>
                        type="radio" class="purchase-attribution" name="purchase-attribution" value="broad-channel-match"> Broad
                        Channel Match
                    </div>

                    <div class="input-group-description">
                        Purchases are attributed when the campaign ID(s) match, and the channel must
                        equal "email" or [blank].
                    </div>
                </label>
            </div>
            <div class="input-group">
                <label>
                    <div class="radio-item">
                        <input <?php if ($userAttMode == 'email-channel-match') {echo 'checked ';} ?>
                        type="radio" class="purchase-attribution" name="purchase-attribution" value="email-channel-match"> Email
                        Channel Match
                    </div>

                    <div class="input-group-description">
                        Purchases are attributed when the campaign ID(s) match, and the channel must
                        equal "email".
                    </div>
            </div>
            </label>
            <div class="input-group-description">
                <em>Note: GA Revenue data is always attributed via "Email Channel Match"</em>
            </div>
        </div>
    </form>
</div>