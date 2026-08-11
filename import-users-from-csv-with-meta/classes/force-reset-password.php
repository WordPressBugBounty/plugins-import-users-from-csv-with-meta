<?php

if ( ! defined( 'ABSPATH' ) ) exit; 

class ACUI_Force_Reset_Password{
    function __construct(){
    }

    function hooks(){
        add_action( 'acui_post_import_single_user', array( $this, 'new_user' ), 10, 9 );
		add_action( 'personal_options_update', array( $this, 'updated' ) );
		add_action( 'template_redirect', array( $this, 'redirect' ) );
		add_action( 'current_screen', array( $this, 'redirect' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'wp_ajax_acui_force_reset_password_delete_metas', array( $this, 'ajax_force_reset_password_delete_metas' ) );
    }

	function new_user( $headers, $data, $user_id, $role, $positions, $form_data, $is_frontend, $is_cron, $password_changed ){
        if( isset( $form_data["force_user_reset_password"] ) && in_array( $form_data["force_user_reset_password"], array( 'yes', 1 ) ) && $password_changed )
		    update_user_meta( $user_id, 'acui_force_reset_password', 1 );
	}

	function updated( $user_id ){
		$pass1 = $pass2 = '';

		if ( isset( $_POST['pass1'] ) )
			$pass1 = $_POST['pass1'];

		if ( isset( $_POST['pass2'] ) )
			$pass2 = $_POST['pass2'];

		if ( $pass1 != $pass2 || empty( $pass1 ) || empty( $pass2 ) || false !== strpos( stripslashes( $pass1 ), "\\" ) )
			return;

		delete_user_meta( $user_id, 'acui_force_reset_password' );
	}

	function redirect() {
        if( is_admin() ) {
			$screen = get_current_screen();

			if ( in_array( $screen->base, array( 'profile', 'plugins' ) ) )
				return;
		}

		if( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) )
			return;

		if( !isset( $_SERVER['REQUEST_METHOD'] ) || strtoupper( $_SERVER['REQUEST_METHOD'] ) != 'GET' )
			return;

		if( !is_user_logged_in() )
			return;

        if( apply_filters( 'acui_force_reset_password_redirect_condition', false ) )
            return;

		if( !get_user_meta( get_current_user_id(), 'acui_force_reset_password', true ) )
			return;

		$url = apply_filters( 'acui_force_reset_password_edit_profile_url', admin_url( 'profile.php' ) );

		if( empty( $url ) )
			return;

		if( $this->is_current_url( $url ) ) {
			$this->reset_redirect_count();
			return;
		}

		if( $this->redirect_loop_detected() )
			return;

		wp_redirect( $url );
		die();
	}

	function is_current_url( $url ) {
		$target = wp_parse_url( $url );

		if( $target === false )
			return false;

		$current_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		$target_path = isset( $target['path'] ) ? $target['path'] : '';

		if( untrailingslashit( urldecode( $current_path ) ) != untrailingslashit( urldecode( $target_path ) ) )
			return false;

		if( empty( $target['query'] ) )
			return true;

		parse_str( $target['query'], $target_args );

		foreach ( $target_args as $key => $value ) {
			if( !isset( $_GET[ $key ] ) || $_GET[ $key ] != $value )
				return false;
		}

		return true;
	}

	function redirect_loop_detected() {
		$limit = absint( apply_filters( 'acui_force_reset_password_max_redirects', 3 ) );

		if( $limit === 0 )
			return false;

		$count = isset( $_COOKIE['acui_force_reset_password_redirects'] ) ? absint( $_COOKIE['acui_force_reset_password_redirects'] ) : 0;

		if( $count >= $limit )
			return true;

		$this->set_redirect_count( $count + 1, time() + 300 );

		return false;
	}

	function reset_redirect_count() {
		if( isset( $_COOKIE['acui_force_reset_password_redirects'] ) )
			$this->set_redirect_count( 0, time() - 3600 );
	}

	function set_redirect_count( $count, $expiration ) {
		if( headers_sent() )
			return;

		setcookie( 'acui_force_reset_password_redirects', $count, $expiration, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		$_COOKIE['acui_force_reset_password_redirects'] = $count;
	}

	function notice(){
		if ( get_user_meta( get_current_user_id(), 'acui_force_reset_password', true ) ) {
			printf( '<div class="error"><p>%s</p></div>', apply_filters( 'acui_force_reset_password_message', __( 'Please change your password', 'import-users-from-csv-with-meta' ) ) );
		}
	}

	function ajax_force_reset_password_delete_metas(){
		check_ajax_referer( 'codection-security', 'security' );

		if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
			wp_die( __( 'Only users who are able to create users can remove the force reset password flag.', 'import-users-from-csv-with-meta' ) );

		global $wpdb;
		$rows = $wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'acui_force_reset_password' ) );
		$result = ( $rows === false ) ? 'ERROR' : $rows;
		
		echo $result;
		wp_die();
	}
}

$acui_force_reset_password = new ACUI_Force_Reset_Password();
$acui_force_reset_password->hooks();