<?php

namespace DownloadMonitor\MailChimpLock;

class DownloadLocker {

	/**
	 * Constructor
	 */
	public function __construct() {
		// make sure download is only accessible for subscribers
		add_filter( 'dlm_can_download', array( $this, 'can_download' ), 10, 2 );

		// filter mailchimp locked downloads from `downloads` shortcode.
		add_filter( 'dlm_shortcode_downloads_args', array( $this, 'filter_locked_downloads' ) );
	}

	/**
	 * Gets the email of the current visitor in the following priority
	 *
	 * 1. From GET or POST data, set when using redirect option in MailChimp for WP
	 * 2. From `mc4wp_email` cookie, set by MailChimp for WP forms
	 * 3. From currently logged in user
	 *
	 * @return string
	 */
	private function get_email() {

		// Check if email is given in the request data, either from form or in URL.
		if( isset( $_REQUEST['email'] ) && is_email( (string) $_REQUEST['email'] ) ) {
			$email = sanitize_text_field( $_REQUEST['email'] );
		} elseif( isset( $_COOKIE['mc4wp_email'] ) ) {
			$email = sanitize_text_field( $_COOKIE['mc4wp_email'] );
		} elseif( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$email = $user->user_email;
		} else {
			$email = '';
		}

		return apply_filters( 'dlm_mailchimp_lock_email', $email );
	}

	/**
	 * Makes sure the download can only be accessed when an email is supplied and that email is
	 * on the selected MailChimp list.
	 *
	 * @param boolean $can_download
	 * @param \DLM_Download $download
	 *
	 * @return boolean
	 */
	public function can_download( $can_download, \DLM_Download $download ) {

		// Do nothing if download is not locked
		if ( 'yes' !== (string) $download->mailchimp_locked ) {
			return $can_download;
		}

		// Get email for current visitor
		$email = $this->get_email();
		if( '' === $email ) {
			return false;
		}

		// Get selected list id & MailChimp API
		$selected_list_id = (string) $download->mailchimp_list_id;
		$api = mc4wp_get_api();

		// Check if email is on selected list
		$list_has_subscriber = $api->list_has_subscriber( $selected_list_id, $email );

		if( $list_has_subscriber ) {
			return true;
		}

		return false;
	}

	/**
	 * Modifies the arguments when fetching all downloads
	 * - filters out MailChimp locked downloads
	 *
	 * @param $args
	 * @return array
	 */
	public function filter_locked_downloads( $args ) {
		$args = array_merge_recursive( $args, array(
			'meta_query' => array(
				array(
					'key'     => '_mailchimp_locked',
					'compare' => 'NOT EXISTS'
				)
			)
		) );

		return $args;
	}
}