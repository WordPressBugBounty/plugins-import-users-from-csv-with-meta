<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if( !defined( 'WP_CLI' ) || !WP_CLI ){
	return;
}

class ACUI_CLI{
	private $temp_files = array();

	static function register(){
		$cli = new self();

		WP_CLI::add_command( 'acui import', array( $cli, 'import' ), array(
			'shortdesc' => __( 'Import users and customers from a CSV file (or any format your addons support).', 'import-users-from-csv-with-meta' ),
			'synopsis' => array(
				array( 'type' => 'positional', 'name' => 'file', 'description' => __( 'Path or URL of the file.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'role', 'optional' => true, 'description' => __( 'Role for the users when the file has no role column. Several roles separated by commas.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'update-existing', 'optional' => true, 'default' => 'yes', 'options' => array( 'yes', 'no' ), 'description' => __( 'Update users who already exist.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'update-emails', 'optional' => true, 'default' => 'no', 'options' => array( 'no', 'yes', 'create' ), 'description' => __( 'What to do when an existing username comes with another email: keep it, update it or create a new user.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'update-roles', 'optional' => true, 'default' => 'no', 'options' => array( 'no', 'yes', 'yes_no_override' ), 'description' => __( 'Update the roles of existing users: no, replace them (yes) or add the new ones (yes_no_override).', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'update-passwords', 'optional' => true, 'default' => 'yes', 'options' => array( 'yes', 'no' ), 'description' => __( 'Update the passwords of existing users when the file has a password column.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'empty-cells', 'optional' => true, 'default' => 'leave', 'options' => array( 'leave', 'delete' ), 'description' => __( 'What to do with empty cells of existing users: leave the old value or delete it.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'flag', 'name' => 'auto-map', 'optional' => true, 'description' => __( 'Match the columns of the file with WordPress fields like the import assistant does, so they can have any name and order.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'map', 'optional' => true, 'description' => __( 'Match columns by hand, for example "Correo=user_email,Empresa=billing_company,Notes=__skip". Columns not listed keep their name, or are guessed with --auto-map.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'flag', 'name' => 'dry-run', 'optional' => true, 'description' => __( 'Check the file and show what would happen, without saving anything.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'flag', 'name' => 'send-email', 'optional' => true, 'description' => __( 'Send the email of the Email options tab to the new users.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'flag', 'name' => 'send-email-updated', 'optional' => true, 'description' => __( 'Send it also to the updated users (with --send-email).', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'flag', 'name' => 'delete-not-present', 'optional' => true, 'description' => __( 'Delete the users who are not in the file. Administrators and the current user are never deleted, and nothing is deleted if there are errors or warnings.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'reassign', 'optional' => true, 'description' => __( 'ID of the user who receives the posts of the deleted users.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'change-role-not-present', 'optional' => true, 'description' => __( 'Change to this role the users who are not in the file, instead of deleting them.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'flag', 'name' => 'not-present-same-role', 'optional' => true, 'description' => __( 'Only delete or change the role of users with the roles that appear in the file.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'flag', 'name' => 'not-present-only-imported', 'optional' => true, 'description' => __( 'Only delete or change the role of users created by previous imports.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'format', 'optional' => true, 'default' => 'table', 'options' => array( 'table', 'json', 'csv', 'yaml' ), 'description' => __( 'Format of the list of problems.', 'import-users-from-csv-with-meta' ) ),
			),
			'longdesc' => "## EXAMPLES\n\n    wp acui import users.csv --role=subscriber\n    wp acui import export-from-crm.xlsx --auto-map --dry-run\n    wp acui import https://example.com/members.csv --map=\"Correo=user_email\" --update-roles=yes --user=admin",
		) );

		WP_CLI::add_command( 'acui export', array( $cli, 'export' ), array(
			'shortdesc' => __( 'Export users and customers to a file.', 'import-users-from-csv-with-meta' ),
			'synopsis' => array(
				array( 'type' => 'assoc', 'name' => 'file', 'optional' => true, 'description' => __( 'Where to save the file. Without it, the file is written to the standard output.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'format', 'optional' => true, 'default' => 'csv', 'description' => __( 'csv, or xlsx, ods and xml with the File Formats addon.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'role', 'optional' => true, 'description' => __( 'Only users with these roles, separated by commas.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'users', 'optional' => true, 'description' => __( 'Only these user IDs, separated by commas.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'from', 'optional' => true, 'description' => __( 'Only users registered from this date (YYYY-MM-DD).', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'to', 'optional' => true, 'description' => __( 'Only users registered until this date (YYYY-MM-DD).', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'columns', 'optional' => true, 'description' => __( 'Only these columns, in this order, separated by commas.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'delimiter', 'optional' => true, 'default' => 'COMMA', 'options' => array( 'COMMA', 'SEMICOLON', 'COLON', 'TAB' ), 'description' => __( 'Delimiter of the CSV file.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'orderby', 'optional' => true, 'description' => __( 'Order the users by ID, user_login, user_email, user_registered, display_name…', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'assoc', 'name' => 'order', 'optional' => true, 'default' => 'ASC', 'options' => array( 'ASC', 'DESC' ) ),
				array( 'type' => 'assoc', 'name' => 'datetime-format', 'optional' => true, 'description' => __( 'Convert timestamps to dates with this PHP format, for example "Y-m-d H:i:s".', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'flag', 'name' => 'include-guests', 'optional' => true, 'description' => __( 'Include WooCommerce guest customers.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'flag', 'name' => 'no-password-hashes', 'optional' => true, 'description' => __( 'Do not export the password hashes.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'flag', 'name' => 'arrays-as-list', 'optional' => true, 'description' => __( 'Write arrays as comma separated values instead of serialized data.', 'import-users-from-csv-with-meta' ) ),
				array( 'type' => 'flag', 'name' => 'alphabetical', 'optional' => true, 'description' => __( 'Order the columns alphabetically.', 'import-users-from-csv-with-meta' ) ),
			),
			'longdesc' => "## EXAMPLES\n\n    wp acui export --file=users.csv\n    wp acui export --role=customer --columns=user_email,first_name,billing_city --format=xlsx --file=customers.xlsx\n    wp acui export --from=2026-01-01 > new-users.csv",
		) );

		WP_CLI::add_command( 'acui columns', array( $cli, 'columns' ), array(
			'shortdesc' => __( 'List the columns you can use in the file, including the ones added by your plugins and addons.', 'import-users-from-csv-with-meta' ),
			'synopsis' => array(
				array( 'type' => 'assoc', 'name' => 'format', 'optional' => true, 'default' => 'table', 'options' => array( 'table', 'json', 'csv', 'yaml' ) ),
			),
		) );

		WP_CLI::add_command( 'acui history', array( $cli, 'history' ), array(
			'shortdesc' => __( 'Show the latest imports and exports.', 'import-users-from-csv-with-meta' ),
			'synopsis' => array(
				array( 'type' => 'assoc', 'name' => 'type', 'optional' => true, 'options' => array( 'import', 'export' ) ),
				array( 'type' => 'assoc', 'name' => 'number', 'optional' => true, 'default' => 20 ),
				array( 'type' => 'assoc', 'name' => 'format', 'optional' => true, 'default' => 'table', 'options' => array( 'table', 'json', 'csv', 'yaml' ) ),
			),
		) );

		WP_CLI::add_command( 'acui cron run', array( $cli, 'cron_run' ), array(
			'shortdesc' => __( 'Run the recurring import now, with its settings, in one go. Useful to run it from the crontab of the server instead of WP-Cron.', 'import-users-from-csv-with-meta' ),
		) );
	}

	function print_items( $format, $items, $fields ){
		ob_start();
		WP_CLI\Utils\format_items( $format, $items, $fields );
		fwrite( STDOUT, ob_get_clean() );
	}

	function ensure_user(){
		if( get_current_user_id() )
			return;

		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => array( 'ID', 'user_login' ), 'orderby' => 'ID' ) );

		if( empty( $admins ) )
			WP_CLI::error( __( 'There is no administrator to run the command. Use --user=<login>.', 'import-users-from-csv-with-meta' ) );

		wp_set_current_user( (int) $admins[0]->ID );
		fwrite( STDERR, sprintf( __( 'Running as %s. Use --user=<login> to choose another user.', 'import-users-from-csv-with-meta' ), $admins[0]->user_login ) . "\n" );
	}

	function temp_path( $extension = 'csv' ){
		$path = ACUI_Private_Files::get_path( 'acui-cli-' . ACUI_Private_Files::random_token() . '.' . $extension );
		$this->temp_files[] = $path;

		return $path;
	}

	function cleanup(){
		foreach( $this->temp_files as $file )
			@unlink( $file );

		$this->temp_files = array();
	}

	function allowed_extensions(){
		return array_unique( array_map( 'strtolower', (array) apply_filters( 'acui_allowed_upload_extensions', array( 'csv' ) ) ) );
	}

	function local_file( $source ){
		if( wp_http_validate_url( $source ) ){
			if( !function_exists( 'download_url' ) )
				require_once ABSPATH . 'wp-admin/includes/file.php';

			$extension = strtolower( pathinfo( (string) wp_parse_url( $source, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
			$downloaded = download_url( $source, 300 );

			if( is_wp_error( $downloaded ) )
				WP_CLI::error( sprintf( __( 'The file could not be downloaded: %s', 'import-users-from-csv-with-meta' ), $downloaded->get_error_message() ) );

			$path = $this->temp_path( in_array( $extension, $this->allowed_extensions(), true ) ? $extension : 'csv' );
			rename( $downloaded, $path );

			return $path;
		}

		$path = realpath( $source );

		if( !$path || !is_file( $path ) || !is_readable( $path ) )
			WP_CLI::error( sprintf( __( 'The file %s cannot be read.', 'import-users-from-csv-with-meta' ), $source ) );

		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if( !in_array( $extension, $this->allowed_extensions(), true ) ){
			$message = sprintf( __( 'Files .%1$s cannot be imported. Allowed: %2$s.', 'import-users-from-csv-with-meta' ), $extension, implode( ', ', $this->allowed_extensions() ) );

			if( in_array( $extension, array( 'xlsx', 'xls', 'ods', 'xml' ), true ) )
				$message .= ' ' . sprintf( __( 'XLSX, XLS, ODS and XML files can be imported with the File Formats addon: %s', 'import-users-from-csv-with-meta' ), ACUIHelper()->premium_url( 'file-formats-addon', 'cli' ) );

			WP_CLI::error( $message );
		}

		return $path;
	}

	function readable_file( $path ){
		$readable = apply_filters( 'acui_import_file_path', $path, array() );

		if( $readable !== $path && is_string( $readable ) && is_file( $readable ) && !in_array( $readable, $this->temp_files, true ) )
			$this->temp_files[] = $readable;

		return $readable;
	}

	function parse_map( $text ){
		$map = array();

		foreach( preg_split( '/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', (string) $text ) as $pair ){
			$position = strrpos( $pair, '=' );

			if( $position === false )
				continue;

			$header = trim( trim( substr( $pair, 0, $position ) ), '"' );
			$target = trim( substr( $pair, $position + 1 ) );

			if( $header !== '' && $target !== '' )
				$map[ ACUI_Wizard::header_key( $header ) ] = $target;
		}

		return $map;
	}

	function mapped_file( $path, $assoc_args ){
		$auto = WP_CLI\Utils\get_flag_value( $assoc_args, 'auto-map', false );
		$manual = isset( $assoc_args['map'] ) ? $this->parse_map( $assoc_args['map'] ) : array();

		if( !$auto && empty( $manual ) )
			return $path;

		$readable = $this->readable_file( $path );
		$wizard = ( new ReflectionClass( 'ACUI_Wizard' ) )->newInstanceWithoutConstructor();
		$analysis = $wizard->analyze_file( $readable );

		if( is_wp_error( $analysis ) )
			WP_CLI::error( $analysis->get_error_message() );

		$suggested = $auto ? ACUI_Wizard::suggest( $analysis['headers'], $analysis['samples'] ) : array();
		$target_keys = ACUI_Wizard::get_target_keys();
		$targets_lower = array_map( 'strtolower', $target_keys );
		$columns = array();
		$rows = array();

		foreach( $analysis['headers'] as $i => $header ){
			$key = ACUI_Wizard::header_key( $header );
			$lower = strtolower( $header );

			if( isset( $manual[ $key ] ) ){
				$target = $manual[ $key ];
				$match = 'manual';
				unset( $manual[ $key ] );
			}
			elseif( isset( $suggested[ $i ] ) ){
				$target = $suggested[ $i ]['target'] === '__meta' ? '__meta' : $suggested[ $i ]['target'];
				$match = $suggested[ $i ]['match'];
			}
			elseif( $lower === 'username' || $lower === 'email' ){
				$target = ( $lower === 'username' ) ? 'user_login' : 'user_email';
				$match = 'exact';
			}
			elseif( ( $index = array_search( $lower, $targets_lower, true ) ) !== false ){
				$target = $target_keys[ $index ];
				$match = 'exact';
			}
			else{
				$target = '__meta';
				$match = 'meta';
			}

			$meta = '';

			if( $target === '__meta' ){
				$meta = $header;
			}
			elseif( $target !== '__skip' && !in_array( $target, $target_keys, true ) ){
				$meta = $target;
				$target = '__meta';
			}

			$columns[] = array( 'header' => $header, 'target' => $target, 'meta' => $meta );
			$rows[] = array(
				'column' => $header,
				'import_as' => $target === '__skip' ? __( '(skipped)', 'import-users-from-csv-with-meta' ) : ( $target === '__meta' ? $meta . ' ' . __( '(custom field)', 'import-users-from-csv-with-meta' ) : $target ),
				'match' => $match,
			);
		}

		if( !empty( $manual ) )
			WP_CLI::error( sprintf( __( 'These columns of --map are not in the file: %s', 'import-users-from-csv-with-meta' ), implode( ', ', array_keys( $manual ) ) ) );

		WP_CLI::log( __( 'Columns:', 'import-users-from-csv-with-meta' ) );
		$this->print_items( 'table', $rows, array( 'column', 'import_as', 'match' ) );

		$mapped = ACUI_Wizard::build_mapped_file( $readable, array( 'columns' => $columns ) );

		if( is_wp_error( $mapped ) )
			WP_CLI::error( $mapped->get_error_message() );

		$this->temp_files[] = $mapped;

		return $mapped;
	}

	function form_data( $assoc_args ){
		$flag = function( $name ) use ( $assoc_args ){
			return (bool) WP_CLI\Utils\get_flag_value( $assoc_args, $name, false );
		};

		$form_data = array(
			'role' => isset( $assoc_args['role'] ) ? array_values( array_filter( array_map( 'trim', explode( ',', $assoc_args['role'] ) ) ) ) : array(),
			'update_existing_users' => isset( $assoc_args['update-existing'] ) ? $assoc_args['update-existing'] : 'yes',
			'update_emails_existing_users' => isset( $assoc_args['update-emails'] ) ? $assoc_args['update-emails'] : 'no',
			'update_roles_existing_users' => isset( $assoc_args['update-roles'] ) ? $assoc_args['update-roles'] : 'no',
			'update_allow_update_passwords' => isset( $assoc_args['update-passwords'] ) ? $assoc_args['update-passwords'] : 'yes',
			'empty_cell_action' => isset( $assoc_args['empty-cells'] ) ? $assoc_args['empty-cells'] : 'leave',
			'sends_email' => $flag( 'send-email' ) ? 'yes' : '',
			'send_email_updated' => $flag( 'send-email-updated' ) ? 'yes' : '',
			'delete_users_not_present' => $flag( 'delete-not-present' ) ? 'yes' : '',
			'delete_users_assign_posts' => isset( $assoc_args['reassign'] ) ? absint( $assoc_args['reassign'] ) : '',
			'change_role_not_present' => isset( $assoc_args['change-role-not-present'] ) ? 'yes' : '',
			'change_role_not_present_role' => isset( $assoc_args['change-role-not-present'] ) ? sanitize_key( $assoc_args['change-role-not-present'] ) : '',
			'not_present_same_role' => $flag( 'not-present-same-role' ) ? 'yes' : 'no',
			'not_present_only_imported' => $flag( 'not-present-only-imported' ) ? 'yes' : 'no',
		);

		if( $form_data['change_role_not_present'] === 'yes' && !get_role( $form_data['change_role_not_present_role'] ) )
			WP_CLI::error( sprintf( __( 'The role %s does not exist.', 'import-users-from-csv-with-meta' ), $form_data['change_role_not_present_role'] ) );

		foreach( $form_data['role'] as $role ){
			if( $role !== 'no_role' && !get_role( $role ) )
				WP_CLI::error( sprintf( __( 'The role %s does not exist.', 'import-users-from-csv-with-meta' ), $role ) );
		}

		return apply_filters( 'acui_cli_import_form_data', $form_data, $assoc_args );
	}

	function count_rows( $file ){
		$manager = new SplFileObject( $file );
		$delimiter = ACUIHelper()->detect_delimiter( $file );
		$rows = -1;

		while( !$manager->eof() ){
			$data = $manager->fgetcsv( $delimiter, '"', ACUIHelper()->get_csv_escape() );

			if( !ACUI_Wizard::is_blank_row( $data ) )
				$rows++;
		}

		return max( 0, $rows );
	}

	function print_problems( $problems, $format ){
		if( empty( $problems ) )
			return;

		$items = array();

		foreach( $problems as $problem ){
			$items[] = array(
				'row' => isset( $problem['row'] ) ? $problem['row'] : '',
				'type' => isset( $problem['type'] ) ? $problem['type'] : 'error',
				'message' => wp_strip_all_tags( html_entity_decode( isset( $problem['message'] ) ? (string) $problem['message'] : '', ENT_QUOTES, 'UTF-8' ) ),
			);
		}

		$this->print_items( $format, $items, array( 'row', 'type', 'message' ) );
	}

	function import( $args, $assoc_args ){
		global $acui_import, $errors;

		$this->ensure_user();

		if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
			WP_CLI::error( __( 'This user is not allowed to import users.', 'import-users-from-csv-with-meta' ) );

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$source = $args[0];
		$file = $this->local_file( $source );
		$file = $this->mapped_file( $file, $assoc_args );
		$form_data = $this->form_data( $assoc_args );

		if( WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false ) ){
			$this->dry_run( $file, $form_data, $format );
			$this->cleanup();
			return;
		}

		$total = $this->count_rows( $this->readable_file( $file ) );
		$progress = ( $format === 'table' && $total > 0 ) ? WP_CLI\Utils\make_progress_bar( sprintf( __( 'Importing %s rows', 'import-users-from-csv-with-meta' ), number_format_i18n( $total ) ), $total ) : null;
		$tick = function() use ( $progress ){
			if( $progress )
				$progress->tick();
		};

		add_action( 'acui_post_import_single_user', $tick, PHP_INT_MAX );

		ob_start( function(){ return ''; }, 8192 );
		$result = $acui_import->import_users( $file, $form_data, false, false );
		ob_end_clean();

		remove_action( 'acui_post_import_single_user', $tick, PHP_INT_MAX );

		if( $progress )
			$progress->finish();

		$results = ( is_array( $result ) && isset( $result['results'] ) ) ? $result['results'] : array( 'created' => 0, 'updated' => 0, 'deleted' => 0, 'ignored' => 0 );
		$problems = is_array( $errors ) ? $errors : array();

		ACUI_History::add_import( 'cli', wp_http_validate_url( $source ) ? (string) wp_parse_url( $source, PHP_URL_PATH ) : $source, $results, $problems );

		$this->print_problems( $problems, $format );
		$this->cleanup();

		$summary = sprintf( __( 'Created: %1$d. Updated: %2$d. Deleted: %3$d. Ignored: %4$d.', 'import-users-from-csv-with-meta' ), $results['created'], $results['updated'], $results['deleted'], $results['ignored'] );
		$failed = count( array_filter( $problems, function( $problem ){ return isset( $problem['type'] ) && $problem['type'] === 'error'; } ) );

		if( $failed > 0 )
			WP_CLI::warning( $summary . ' ' . sprintf( _n( '%d row with errors.', '%d rows with errors.', $failed, 'import-users-from-csv-with-meta' ), $failed ) );
		else
			WP_CLI::success( $summary );
	}

	function dry_run( $file, $form_data, $format ){
		global $acui_import;

		$file = $this->readable_file( $file );
		$wizard = ( new ReflectionClass( 'ACUI_Wizard' ) )->newInstanceWithoutConstructor();
		$settings = $acui_import->prepare_settings( $form_data );
		$delimiter = ACUIHelper()->detect_delimiter( $file );
		$escape = ACUIHelper()->get_csv_escape();
		$manager = new SplFileObject( $file );
		$headers = $manager->fgetcsv( $delimiter, '"', $escape );

		if( ACUI_Wizard::is_blank_row( $headers ) )
			WP_CLI::error( __( 'The first row of the file is empty. It must contain the name of each column.', 'import-users-from-csv-with-meta' ) );

		$headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $headers[0] );
		$headers = array_map( 'trim', $headers );
		$lower = array_map( 'strtolower', $headers );
		$positions = array(
			'role' => array_search( 'role', $lower, true ),
			'id' => array_search( 'id', $lower, true ),
			'password' => array_search( 'password', $lower, true ),
		);
		$preview = array( 'row' => 1, 'emails' => array(), 'logins' => array(), 'registered' => array(), 'roles_appeared' => $settings['role_default'] );
		$counts = array( 'created' => 0, 'updated' => 0, 'ignored' => 0, 'errors' => 0 );
		$problems = array();

		while( !$manager->eof() ){
			$row = $manager->fgetcsv( $delimiter, '"', $escape );

			if( ACUI_Wizard::is_blank_row( $row ) )
				continue;

			$preview['row']++;
			$result = $wizard->simulate_row( $row, count( $headers ), $positions, $settings, $preview );
			$result = apply_filters( 'acui_wizard_preview_row', $result, $headers, $row, $settings );
			$key = isset( $counts[ $result['result'] ] ) ? $result['result'] : 'errors';
			$counts[ $key ]++;

			if( $result['message'] !== '' )
				$problems[] = array( 'row' => $preview['row'], 'type' => $result['type'] ? $result['type'] : 'notice', 'message' => $result['message'] );
		}

		$this->print_problems( $problems, $format );
		WP_CLI::success( sprintf( __( 'Dry run, nothing has been saved. Would be created: %1$d. Updated: %2$d. Skipped: %3$d. With errors: %4$d.', 'import-users-from-csv-with-meta' ), $counts['created'], $counts['updated'], $counts['ignored'], $counts['errors'] ) );
	}

	function export( $args, $assoc_args ){
		$this->ensure_user();

		if( !current_user_can( apply_filters( 'acui_export_capability', 'edit_users' ) ) )
			WP_CLI::error( __( 'This user is not allowed to export users.', 'import-users-from-csv-with-meta' ) );

		$flag = function( $name ) use ( $assoc_args ){
			return (bool) WP_CLI\Utils\get_flag_value( $assoc_args, $name, false );
		};

		$format = isset( $assoc_args['format'] ) ? strtolower( $assoc_args['format'] ) : 'csv';

		if( !array_key_exists( $format, ACUI_Batch_Exporter::get_formats() ) ){
			$message = sprintf( __( 'The format %1$s is not available. Available: %2$s.', 'import-users-from-csv-with-meta' ), $format, implode( ', ', array_keys( ACUI_Batch_Exporter::get_formats() ) ) );

			if( in_array( $format, array( 'xlsx', 'ods', 'xml' ), true ) )
				$message .= ' ' . sprintf( __( 'Excel, OpenDocument and XML exports need the File Formats addon: %s', 'import-users-from-csv-with-meta' ), ACUIHelper()->premium_url( 'file-formats-addon', 'cli' ) );

			WP_CLI::error( $message );
		}

		$path = $this->temp_path( 'csv' );
		$this->temp_files[] = $path . '.headers';

		$make = function( $recalculate ) use ( $assoc_args, $flag, $format, $path ){
			$exporter = new ACUI_Batch_Exporter();
			$exporter->set_limit( (int) apply_filters( 'acui_cli_export_batch_size', 500 ) );
			$exporter->set_path( $path );
			$exporter->set_role( isset( $assoc_args['role'] ) ? array_filter( array_map( 'trim', explode( ',', $assoc_args['role'] ) ) ) : '' );
			$exporter->set_from( isset( $assoc_args['from'] ) ? $assoc_args['from'] : '' );
			$exporter->set_to( isset( $assoc_args['to'] ) ? $assoc_args['to'] : '' );
			$exporter->set_users( isset( $assoc_args['users'] ) ? $assoc_args['users'] : array() );
			$exporter->set_include_guests( $flag( 'include-guests' ) );
			$exporter->fill_total_rows( $recalculate );
			$exporter->set_format( $format );
			$exporter->set_delimiter( $format === 'csv' && isset( $assoc_args['delimiter'] ) ? $assoc_args['delimiter'] : '' );
			$exporter->set_convert_timestamp( isset( $assoc_args['datetime-format'] ) );
			$exporter->set_datetime_format( isset( $assoc_args['datetime-format'] ) ? $assoc_args['datetime-format'] : '' );
			$exporter->set_order_fields_alphabetically( $flag( 'alphabetical' ) );
			$exporter->set_double_encapsulate_serialized_values( false );
			$exporter->set_display_arrays_as_comma_separated_list_of_values( $flag( 'arrays-as-list' ) );
			$exporter->set_export_password_hashes( !$flag( 'no-password-hashes' ) );
			$exporter->set_filtered_columns( isset( $assoc_args['columns'] ) ? array_values( array_filter( array_map( 'trim', explode( ',', $assoc_args['columns'] ) ) ) ) : array() );
			$exporter->set_orderby( isset( $assoc_args['orderby'] ) ? $assoc_args['orderby'] : '' );
			$exporter->set_order( isset( $assoc_args['order'] ) ? $assoc_args['order'] : 'ASC' );

			return $exporter;
		};

		$exporter = $make( true );
		$to_stdout = empty( $assoc_args['file'] );
		$total_steps = max( 1, (int) $exporter->get_total_steps() );
		$progress = ( !$to_stdout && $total_steps > 1 ) ? WP_CLI\Utils\make_progress_bar( __( 'Exporting users', 'import-users-from-csv-with-meta' ), $total_steps ) : null;

		for( $step = 1; $step <= $total_steps; $step++ ){
			if( $step > 1 )
				$exporter = $make( false );

			$exporter->set_page( $step );
			$exporter->load_columns();
			$exporter->generate_file();

			if( $progress )
				$progress->tick();

			if( $exporter->get_percent_complete() >= 100 )
				break;
		}

		if( $progress )
			$progress->finish();

		$history = array(
			'type' => 'export',
			'source' => 'cli',
			'rows' => $exporter->get_total_rows(),
			'details' => array( __( 'Format', 'import-users-from-csv-with-meta' ) => ACUI_Batch_Exporter::get_formats()[ $format ] ),
		);

		if( $to_stdout ){
			$file = $exporter->build_export_file();

			if( false === $file )
				WP_CLI::error( __( 'The export file could not be created.', 'import-users-from-csv-with-meta' ) );

			$this->temp_files = array_merge( $this->temp_files, $file['temp_files'] );
			$input = fopen( $file['path'], 'rb' );
			stream_copy_to_stream( $input, STDOUT );
			fclose( $input );
			$history['file'] = 'stdout.' . $file['extension'];
			ACUI_History::add( $history );
			$this->cleanup();
			return;
		}

		$destination = $assoc_args['file'];

		if( !path_is_absolute( $destination ) )
			$destination = getcwd() . DIRECTORY_SEPARATOR . $destination;

		$saved = $exporter->save_export_file( $destination );
		$this->cleanup();

		if( false === $saved )
			WP_CLI::error( sprintf( __( 'The file %s could not be written.', 'import-users-from-csv-with-meta' ), $destination ) );

		$history['file'] = $saved;
		ACUI_History::add( $history );

		WP_CLI::success( sprintf( _n( '%1$s user exported to %2$s.', '%1$s users exported to %2$s.', $exporter->get_total_rows(), 'import-users-from-csv-with-meta' ), number_format_i18n( $exporter->get_total_rows() ), $saved ) );
	}

	function columns( $args, $assoc_args ){
		$items = array();

		foreach( ACUI_Wizard::get_targets() as $group => $targets ){
			foreach( $targets as $key => $label )
				$items[] = array( 'column' => $key, 'description' => wp_strip_all_tags( (string) $label ), 'group' => $group );
		}

		$this->print_items( isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table', $items, array( 'column', 'description', 'group' ) );
	}

	function history( $args, $assoc_args ){
		$type = isset( $assoc_args['type'] ) ? $assoc_args['type'] : '';
		$number = isset( $assoc_args['number'] ) ? max( 1, absint( $assoc_args['number'] ) ) : 20;
		$items = array();

		foreach( ACUI_History::get() as $entry ){
			if( $type !== '' && ( !isset( $entry['type'] ) || $entry['type'] !== $type ) )
				continue;

			$entry = wp_parse_args( $entry, array( 'type' => 'import', 'time' => 0, 'user' => '', 'file' => '', 'created' => 0, 'updated' => 0, 'deleted' => 0, 'ignored' => 0, 'errors' => 0, 'warnings' => 0, 'rows' => 0 ) );
			$items[] = array(
				'date' => $entry['time'] ? wp_date( 'Y-m-d H:i:s', $entry['time'] ) : '',
				'type' => $entry['type'],
				'source' => ACUI_History::source_label( $entry ),
				'user' => $entry['user'],
				'file' => $entry['file'],
				'created' => $entry['created'],
				'updated' => $entry['updated'],
				'deleted' => $entry['deleted'],
				'errors' => $entry['errors'],
				'warnings' => $entry['warnings'],
				'rows' => $entry['rows'],
			);

			if( count( $items ) >= $number )
				break;
		}

		if( empty( $items ) ){
			WP_CLI::log( __( 'There are no imports or exports yet.', 'import-users-from-csv-with-meta' ) );
			return;
		}

		$this->print_items( isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table', $items, array( 'date', 'type', 'source', 'user', 'file', 'created', 'updated', 'deleted', 'errors', 'warnings', 'rows' ) );
	}

	function cron_run( $args, $assoc_args ){
		global $acui_import, $errors;

		if( !class_exists( 'ACUI_Cron' ) )
			WP_CLI::error( __( 'The recurring import is not available.', 'import-users-from-csv-with-meta' ) );

		$cron = ( new ReflectionClass( 'ACUI_Cron' ) )->newInstanceWithoutConstructor();
		$path = $cron->clean_path_url_csv( get_option( 'acui_cron_path_to_file' ) );

		if( empty( $path ) )
			WP_CLI::error( __( 'The recurring import has no file. Set it in the Recurring tab first.', 'import-users-from-csv-with-meta' ) );

		$cron->set_cron_user();

		$form_data = array(
			'path_to_file' => $path,
			'role' => get_option( 'acui_cron_role' ),
			'caller_can_promote_users' => is_user_logged_in() ? current_user_can( 'promote_users' ) : $cron->get_unattended_can_promote_users(),
			'update_roles_existing_users' => ACUI_Cron::get_update_roles_existing_users(),
			'update_emails_existing_users' => 'no',
			'empty_cell_action' => 'leave',
		);

		$file = $acui_import->manage_file_upload( $path );

		if( false === $file )
			WP_CLI::error( sprintf( __( 'The file of the recurring import cannot be used: %s', 'import-users-from-csv-with-meta' ), $path ) );

		ob_start();
		$result = $acui_import->import_users( $file, $form_data, true, false );
		$log = ob_get_clean();

		if( get_option( 'acui_move_file_cron' ) && is_array( $result ) && !empty( $result['done'] ) && empty( $result['aborted'] ) && is_file( $path ) ){
			rename( $path, $cron->clean_path_url_csv( get_option( 'acui_cron_path_to_move' ) ) );
			$cron->auto_rename();
		}

		update_option( 'acui_cron_log', $log, false );

		if( is_array( $result ) && !empty( $result['done'] ) )
			$cron->save_execution_log( $result, 1 );

		$results = ( is_array( $result ) && isset( $result['results'] ) ) ? $result['results'] : array( 'created' => 0, 'updated' => 0, 'deleted' => 0, 'ignored' => 0 );
		$this->print_problems( is_array( $errors ) ? $errors : array(), 'table' );

		WP_CLI::success( sprintf( __( 'Recurring import finished. Created: %1$d. Updated: %2$d. Deleted: %3$d. Ignored: %4$d.', 'import-users-from-csv-with-meta' ), $results['created'], $results['updated'], $results['deleted'], $results['ignored'] ) );
	}
}

ACUI_CLI::register();
