<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACUI_Private_Files{
	const CLEANUP_HOOK = 'acui_private_files_cleanup';
	const SUFFIX_OPTION = 'acui_private_dir_suffix';
	const FALLBACK_OPTION = 'acui_private_files_fallback';

	function __construct(){
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'scheduled_cleanup' ) );
		add_filter( 'debug_information', array( __CLASS__, 'site_health_info' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'site_health_tests' ) );

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

		if( !self::is_usable( $dir ) ){
			$fallback = self::get_temp_fallback_dir();

			if( self::is_usable( $fallback ) )
				$dir = $fallback;
		}

		return apply_filters( 'acui_private_dir', $dir );
	}

	static function get_temp_fallback_dir(){
		$dir = trailingslashit( get_temp_dir() ) . 'acui-private-' . get_option( self::SUFFIX_OPTION );
		self::protect_dir( $dir );

		return $dir;
	}

	static function upload_error_message( $code ){
		switch( (int) $code ){
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return sprintf( __( 'The file is larger than the maximum upload size of this server (%s). Upload it to the uploads folder by FTP and use the "Use a URL or a file on this server" option, or ask your hosting provider to increase upload_max_filesize and post_max_size.', 'import-users-from-csv-with-meta' ), size_format( wp_max_upload_size() ) );
			case UPLOAD_ERR_PARTIAL:
				return __( 'The file was only partially uploaded. Please try again.', 'import-users-from-csv-with-meta' );
			case UPLOAD_ERR_NO_TMP_DIR:
				return __( 'The server has no temporary folder for uploads. Ask your hosting provider to check upload_tmp_dir.', 'import-users-from-csv-with-meta' );
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'The server could not write the uploaded file to disk. The disk or the quota of your hosting account may be full.', 'import-users-from-csv-with-meta' );
			case UPLOAD_ERR_EXTENSION:
				return __( 'A PHP extension of the server stopped the upload.', 'import-users-from-csv-with-meta' );
		}

		return '';
	}

	static function save_upload( $upload, $filename ){
		if( !is_array( $upload ) || empty( $upload['tmp_name'] ) )
			return new WP_Error( 'acui_no_upload', __( 'Please choose a file', 'import-users-from-csv-with-meta' ) );

		if( isset( $upload['error'] ) && (int) $upload['error'] !== UPLOAD_ERR_OK )
			return new WP_Error( 'acui_upload_error', self::upload_error_message( $upload['error'] ) );

		$tmp_name = $upload['tmp_name'];
		$dirs = array_unique( array( self::get_dir(), self::get_temp_fallback_dir() ) );
		$warnings = array();
		$tried = array();

		set_error_handler( function( $number, $message ) use ( &$warnings ){
			$warnings[] = wp_strip_all_tags( $message );
			return true;
		} );

		$failures = array();

		foreach( $dirs as $dir ){
			$path = trailingslashit( $dir ) . sanitize_file_name( $filename );
			$tried[] = $dir;

			foreach( array( 'move', 'copy' ) as $method ){
				$before = count( $warnings );
				$saved = ( $method === 'move' ) ? move_uploaded_file( $tmp_name, $path ) : ( is_uploaded_file( $tmp_name ) && copy( $tmp_name, $path ) );

				if( $saved ){
					restore_error_handler();

					if( empty( $failures ) )
						delete_option( self::FALLBACK_OPTION );
					else
						update_option( self::FALLBACK_OPTION, array( 'time' => time(), 'failures' => $failures, 'used' => $dir, 'method' => $method ), false );

					return $path;
				}

				$failures[] = array( 'dir' => $dir, 'method' => $method, 'warnings' => array_values( array_unique( array_slice( $warnings, $before ) ) ) );
			}
		}

		restore_error_handler();

		update_option( self::FALLBACK_OPTION, array( 'time' => time(), 'failures' => $failures, 'used' => '', 'method' => '' ), false );

		$details = array();

		if( !is_uploaded_file( $tmp_name ) )
			$details[] = __( 'the uploaded file is no longer in the temporary folder of PHP', 'import-users-from-csv-with-meta' );

		foreach( array_unique( $warnings ) as $warning )
			$details[] = $warning;

		return new WP_Error( 'acui_save_upload', sprintf( __( 'The uploaded file could not be saved in %1$s. Details: %2$s. The disk or the quota of your hosting account may be full, or WordPress may not be allowed to write in that folder: check it or ask your hosting provider.', 'import-users-from-csv-with-meta' ), implode( ' ' . __( 'or', 'import-users-from-csv-with-meta' ) . ' ', $tried ), implode( '; ', $details ) ) );
	}

	static function is_usable( $dir ){
		return is_dir( $dir ) && wp_is_writable( $dir );
	}

	static function save_error( $path ){
		$dir = dirname( $path );

		if( !is_dir( $dir ) )
			$reason = __( 'the folder does not exist and could not be created', 'import-users-from-csv-with-meta' );
		elseif( !wp_is_writable( $dir ) )
			$reason = __( 'WordPress cannot write in the folder', 'import-users-from-csv-with-meta' );
		else{
			$error = error_get_last();
			$reason = ( is_array( $error ) && !empty( $error['message'] ) ) ? wp_strip_all_tags( $error['message'] ) : __( 'unknown error', 'import-users-from-csv-with-meta' );
		}

		return sprintf( __( 'The file could not be saved in %1$s (%2$s). Check the permissions of the uploads folder or ask your hosting provider.', 'import-users-from-csv-with-meta' ), $dir, $reason );
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

		foreach( array_unique( array( self::get_dir(), trailingslashit( get_temp_dir() ) . 'acui-private-' . get_option( self::SUFFIX_OPTION ) ) ) as $private_dir ){
			$private = glob( trailingslashit( $private_dir ) . '*' );
			if( is_array( $private ) )
				$candidates = array_merge( $candidates, $private );
		}

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

	static function describe_fallback( $event ){
		$lines = array();

		foreach( $event['failures'] as $failure )
			$lines[] = sprintf( __( '%1$s with %2$s failed: %3$s', 'import-users-from-csv-with-meta' ), $failure['dir'], $failure['method'], empty( $failure['warnings'] ) ? __( 'no message from PHP', 'import-users-from-csv-with-meta' ) : implode( ' / ', $failure['warnings'] ) );

		$lines[] = empty( $event['used'] ) ? __( 'Nothing worked: the file could not be saved.', 'import-users-from-csv-with-meta' ) : sprintf( __( 'Saved in %1$s with %2$s.', 'import-users-from-csv-with-meta' ), $event['used'], $event['method'] );

		return $lines;
	}

	static function site_health_info( $info ){
		$upload_dir = wp_upload_dir( null, false );
		$main = trailingslashit( $upload_dir['basedir'] ) . 'acui-private-' . get_option( self::SUFFIX_OPTION );
		$temp = trailingslashit( get_temp_dir() ) . 'acui-private-' . get_option( self::SUFFIX_OPTION );
		$event = get_option( self::FALLBACK_OPTION );

		$fields = array(
			'private_dir' => array( 'label' => __( 'Private folder for temporary files', 'import-users-from-csv-with-meta' ), 'value' => $main . ' (' . ( self::is_usable( $main ) ? __( 'writable', 'import-users-from-csv-with-meta' ) : __( 'not writable', 'import-users-from-csv-with-meta' ) ) . ')', 'private' => true ),
			'temp_dir' => array( 'label' => __( 'Alternative folder', 'import-users-from-csv-with-meta' ), 'value' => $temp . ' (' . ( self::is_usable( $temp ) ? __( 'writable', 'import-users-from-csv-with-meta' ) : __( 'not writable', 'import-users-from-csv-with-meta' ) ) . ')', 'private' => true ),
			'dir_in_use' => array( 'label' => __( 'Folder in use', 'import-users-from-csv-with-meta' ), 'value' => self::get_dir(), 'private' => true ),
			'max_upload' => array( 'label' => __( 'Maximum upload size', 'import-users-from-csv-with-meta' ), 'value' => size_format( wp_max_upload_size() ) ),
			'last_fallback' => array(
				'label' => __( 'Last problem saving an uploaded file', 'import-users-from-csv-with-meta' ),
				'value' => is_array( $event ) ? wp_date( 'Y-m-d H:i', $event['time'] ) . ': ' . implode( ' ', self::describe_fallback( $event ) ) : __( 'None', 'import-users-from-csv-with-meta' ),
			),
		);

		$info['import-users-from-csv-with-meta'] = array(
			'label' => __( 'Import and export users and customers', 'import-users-from-csv-with-meta' ),
			'fields' => $fields,
		);

		return $info;
	}

	static function site_health_tests( $tests ){
		$tests['direct']['acui_private_files'] = array(
			'label' => __( 'Import and export users and customers can save uploaded files', 'import-users-from-csv-with-meta' ),
			'test' => array( __CLASS__, 'site_health_test' ),
		);

		return $tests;
	}

	static function site_health_test(){
		$event = get_option( self::FALLBACK_OPTION );
		$result = array(
			'label' => __( 'Import and export users and customers can save uploaded files', 'import-users-from-csv-with-meta' ),
			'status' => 'good',
			'badge' => array( 'label' => __( 'Import users', 'import-users-from-csv-with-meta' ), 'color' => 'blue' ),
			'description' => '<p>' . __( 'The files uploaded to import users are saved in a private folder without problems.', 'import-users-from-csv-with-meta' ) . '</p>',
			'test' => 'acui_private_files',
		);

		if( !is_array( $event ) )
			return $result;

		$result['status'] = 'recommended';
		$result['label'] = empty( $event['used'] ) ? __( 'Import and export users and customers could not save an uploaded file', 'import-users-from-csv-with-meta' ) : __( 'Import and export users and customers had to use an alternative way to save an uploaded file', 'import-users-from-csv-with-meta' );
		$result['description'] = '<p>' . sprintf( __( 'On %s the uploaded file could not be saved in the usual way. The import keeps working, but the server may have a problem with the permissions or the quota of the uploads folder. Send this information to your hosting provider or to the support forum of the plugin:', 'import-users-from-csv-with-meta' ), esc_html( wp_date( 'Y-m-d H:i', $event['time'] ) ) ) . '</p><ul><li>' . implode( '</li><li>', array_map( 'esc_html', self::describe_fallback( $event ) ) ) . '</li></ul>';

		return $result;
	}

	static function deactivate(){
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
		self::cleanup( 0 );
	}
}

new ACUI_Private_Files();
