<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACUI_Private_Files{
	const CLEANUP_HOOK = 'acui_private_files_cleanup';
	const SUFFIX_OPTION = 'acui_private_dir_suffix';

	function __construct(){
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'scheduled_cleanup' ) );

		if( !wp_next_scheduled( self::CLEANUP_HOOK ) )
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CLEANUP_HOOK );
	}

	static function get_dir(){
		$suffix = get_option( self::SUFFIX_OPTION );

		if( empty( $suffix ) || !preg_match( '/^[a-z0-9]{20,}$/', $suffix ) ){
			$suffix = strtolower( wp_generate_password( 24, false, false ) );
			update_option( self::SUFFIX_OPTION, $suffix, false );
		}

		$upload_dir = wp_upload_dir( null, false );
		$dir = trailingslashit( $upload_dir['basedir'] ) . 'acui-private-' . $suffix;

		self::protect_dir( $dir );

		return apply_filters( 'acui_private_dir', $dir );
	}

	static function protect_dir( $dir ){
		if( !is_dir( $dir ) )
			wp_mkdir_p( $dir );

		$files = array(
			'.htaccess' => "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n<system.webServer>\n<authorization>\n<deny users=\"*\" />\n</authorization>\n</system.webServer>\n</configuration>\n",
			'index.php' => "<?php\n// Silence is golden.\n",
			'index.html' => '',
		);

		foreach( $files as $name => $content ){
			$path = trailingslashit( $dir ) . $name;
			if( !file_exists( $path ) )
				@file_put_contents( $path, $content );
		}
	}

	static function get_path( $filename ){
		return trailingslashit( self::get_dir() ) . sanitize_file_name( $filename );
	}

	static function random_token(){
		return strtolower( wp_generate_password( 32, false, false ) );
	}

	static function protected_names(){
		return array( '.htaccess', 'web.config', 'index.php', 'index.html' );
	}

	static function scheduled_cleanup(){
		self::cleanup( (int) apply_filters( 'acui_private_files_max_age', 6 * HOUR_IN_SECONDS ) );
	}

	static function cleanup( $max_age ){
		$limit = time() - absint( $max_age );
		$upload_dir = wp_upload_dir( null, false );
		$basedir = trailingslashit( $upload_dir['basedir'] );

		$candidates = array();

		$private = glob( trailingslashit( self::get_dir() ) . '*' );
		if( is_array( $private ) )
			$candidates = array_merge( $candidates, $private );

		foreach( array( 'user-export-*.csv', 'user-export-*.csv.headers', 'acui-import-*' ) as $pattern ){
			$legacy = glob( $basedir . $pattern );
			if( is_array( $legacy ) )
				$candidates = array_merge( $candidates, $legacy );
		}

		foreach( $candidates as $file ){
			if( !is_file( $file ) || in_array( basename( $file ), self::protected_names(), true ) )
				continue;

			$mtime = @filemtime( $file );
			if( $mtime !== false && $mtime > $limit )
				continue;

			@unlink( $file );
		}
	}

	static function deactivate(){
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
		self::cleanup( 0 );
	}
}

new ACUI_Private_Files();
