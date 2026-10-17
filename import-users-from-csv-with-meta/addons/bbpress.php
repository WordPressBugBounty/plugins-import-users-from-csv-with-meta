<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if( !is_plugin_active( 'bbpress/bbpress.php' ) ){
	return;
}

class ACUI_bbPress{
	function __construct(){
		add_filter( 'acui_force_reset_password_edit_profile_url', array( $this, 'force_reset_password_edit_profile_url' ) );
		add_action( 'bbp_template_notices', array( $this, 'force_reset_password_notice' ) );
	}

	function force_reset_password_edit_profile_url( $url ){
		if( $url != admin_url( 'profile.php' ) || !function_exists( 'bbp_get_user_profile_edit_url' ) )
			return $url;

		return bbp_get_user_profile_edit_url( get_current_user_id() );
	}

	function force_reset_password_notice(){
		if( function_exists( 'bbp_is_single_user_edit' ) && bbp_is_single_user_edit() && get_user_meta( get_current_user_id(), 'acui_force_reset_password', true ) )
			printf( '<div class="bbp-template-notice error"><ul><li>%s</li></ul></div>', apply_filters( 'acui_force_reset_password_message', __( 'Please change your password', 'import-users-from-csv-with-meta' ) ) );
	}
}
new ACUI_bbPress();
