<?php
$currentUser = wp_get_current_user();
$currentUserId = $currentUser->ID;
$userAttMode = get_user_meta( $currentUser->ID, 'purchase_attribution_mode', true );
$userAttLength = get_user_meta( $currentUser->ID, 'purchase_attribution_length', true );
?>
<button class="wiz-button gray module-settings" "><i class=" fa-solid fa-cog"></i></button>
<div id="module-settings-dropdown" class="dropdown-menu">
	<form id="attribution-settings-form">
		<div class="form-group" id="purchase-attribution">
			<div class="input-groups">
				<h5>Attribution Length</h5>
				<div class="input-group">
					<select name="purchase_attribution_length" id="selectAttrLength">
						<option value="allTime" <?php if ( ! $userAttLength || $userAttLength == 'allTime' ) {
							echo ' selected';
						} ?>>All Time (default)</option>
						<option value="72Hours" <?php if ( $userAttLength == '72Hours' ) {
							echo ' selected';
						} ?>>72 Hours
						</option>
						<option value="30Days" <?php if ( $userAttLength == '30Days' ) {
							echo ' selected';
						} ?>>30 Days
						</option>
						<option value="60Days" <?php if ( $userAttLength == '60Days' ) {
							echo ' selected';
						} ?>>60 Days
						</option>
						<option value="90Days" <?php if ( $userAttLength == '90Days' ) {
							echo ' selected';
						} ?>>90 Days
						</option>
					</select>
				</div>
			</div>


			<div class="input-groups">
				<h5>Attribution Mode</h5>
				<div class="input-group">
					<label>
						<div class="radio-item">
							<input <?php if ( ! $userAttMode || $userAttMode == 'campaign-id' ) {
								echo 'checked ';
							} ?>
								type="radio" class="purchase-attribution" name="purchase_attribution_mode"
								value="campaign-id"> Campaign ID
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
							<input <?php if ( $userAttMode == 'broad-channel-match' ) {
								echo 'checked ';
							} ?> type="radio"
								class="purchase-attribution" name="purchase_attribution_mode"
								value="broad-channel-match">
							Broad
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
							<input <?php if ( $userAttMode == 'email-channel-match' ) {
								echo 'checked ';
							} ?> type="radio"
								class="purchase-attribution" name="purchase_attribution_mode"
								value="email-channel-match">
							Email
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
		</div>
	</form>
</div>