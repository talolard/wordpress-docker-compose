<?php

namespace DownloadMonitor\MailChimpLock\Admin;

use DownloadMonitor\MailChimpLock\Plugin;

class Manager {

	const SETTINGS_CAP = 'manage_downloads';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'dlm_extensions', array( $this, 'register_extension' ) );
		add_action( 'dlm_options_end', array( $this, 'show_settings' ) );
		add_action( 'dlm_save_meta_boxes', array( $this, 'save_settings' ) );
	}

	/**
	 * Register this extension
	 *
	 * @param array $extensions
	 * @return array $extensions
	 */
	public function register_extension( $extensions ) {
		$extensions[] = 'dlm-mailchimp-lock';
		return $extensions;
	}

	/**
	 * Add settings to the Download settings.
	 *
	 * @param $post_id
	 */
	public function show_settings( $post_id ) {
		$locked = get_post_meta( $post_id, '_mailchimp_locked', true );
		$selected_list_id = get_post_meta( $post_id, '_mailchimp_list_id', true );
		$mailchimp = new \MC4WP_MailChimp();
		?>
		<div class="form-field">
			<p class="form-field-checkbox">
				<input type="checkbox" name="_mailchimp_locked" id="_mailchimp_locked" value="yes" <?php checked( $locked, 'yes' ); ?> />
				<label for="_mailchimp_locked"><?php _e( 'Subscribers only', 'dlm-mailchimp-lock' ); ?></label>
				<span class="description"><?php _e( 'Only MailChimp subscribers will be able to access the file via a download link if this is enabled.', 'dlm-mailchimp-lock' ); ?></span>
				<select name="_mailchimp_list_id" id="_mailchimp_list_id" style="margin-top: 10px; display: <?php echo ( $locked === 'yes' ) ? 'block' : 'none'; ?>;">
					<?php foreach( $mailchimp->get_lists() as $list ) { ?>
						<option value="<?php echo esc_attr( $list->id ); ?>" <?php selected( $list->id, $selected_list_id ); ?>>
							<?php echo esc_html( $list->name ); ?>
						</option>
					<?php } ?>
				</select>

			</p>
		</div>
		<?php

		// print js snippet in footer
		add_action( 'admin_footer', array( $this, 'javascript' ) );
	}

	/**
	 * @param $post_id
	 */
	public function save_settings( $post_id ) {
		$locked = ( isset( $_POST['_mailchimp_locked'] ) && $_POST['_mailchimp_locked'] == 'yes' ) ? 'yes' : 'no';
		$list_id = ( isset( $_POST['_mailchimp_list_id'] ) ) ? sanitize_text_field( $_POST['_mailchimp_list_id'] ) : '';

		if( 'yes' === $locked ) {
			update_post_meta( $post_id, '_mailchimp_locked', $locked );
			update_post_meta( $post_id, '_mailchimp_list_id', $list_id );
		} else {
			delete_post_meta( $post_id, '_mailchimp_locked' );
			delete_post_meta( $post_id, '_mailchimp_list_id' );
		}
	}

	public function javascript() {
		?>
		<script type="text/javascript">
			(function() {
				var triggerEl = document.getElementById('_mailchimp_locked');
				var targetEl = document.getElementById('_mailchimp_list_id');

				// for IE8 compatibility, use a simple `onchange`
				triggerEl.onchange = function() {
					targetEl.style.display = ( this.checked ) ? 'block' : 'none';
				};

			})();
		</script>
		<?php
	}

	/**
	 * @param $url
	 *
	 * @return string
	 */
	protected function asset_url( $url ) {
		return plugins_url( '/assets' . $url, Plugin::FILE );
	}

}