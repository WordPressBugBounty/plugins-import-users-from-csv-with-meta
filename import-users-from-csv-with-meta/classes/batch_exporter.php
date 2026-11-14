<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ACUI_Batch_Exporter{
	protected $path = '';
	protected $filename;
	protected $limit = 50;
	protected $exported_row_count = 0;
	protected $row_data = array();
	protected $total_rows = 0;
	protected $columns_to_export = array();
	
    protected $page = 1;
    protected $delimiter = ',';
    protected $role = '';
    protected $from = '';
    protected $to = '';
    protected $convert_timestamp = false;
    protected $datetime_format;
    protected $order_fields_alphabetically = false;
    protected $double_encapsulate_serialized_values = false;
	protected $display_arrays_as_comma_separated_list_of_values = false;
	protected $export_password_hashes = true;
    protected $filtered_columns = array();
    protected $orderby = '';
    protected $order = '';
    protected $users = array();
    protected $include_guests = false;
    protected $offset_rows = 0;
    protected $max_rows = 0;
    protected $format = 'csv';

    protected $user_data;
    protected $accepted_order_by;
    protected $woocommerce_default_user_meta_keys;
    protected $other_non_date_keys;
    
    function __construct() {
        add_filter( 'acui_export_columns', array( $this, 'maybe_order_columns_alphabetacally' ), 10, 2 );
		add_filter( 'acui_export_data', array( $this, 'maybe_double_encapsulate_serialized_values' ), 8, 3 );
        add_filter( 'acui_export_data', array( $this, 'maybe_order_row_alphabetically' ), 10, 3 );
		add_filter( 'acui_export_data', array( $this, 'maybe_order_row_filtered_columns_parameter' ), 11, 5 );
        
		$this->filename = 'user-export-' . get_current_user_id() . '-' . ACUI_Private_Files::random_token() . '.csv';
		$this->user_data = array( "user_login", "user_email", "source_user_id", "user_pass", "user_nicename", "user_url", "user_registered", "display_name" );
        $this->accepted_order_by = array( 'ID', 'display_name', 'name', 'user_name', 'login', 'user_login', 'nicename', 'user_nicename', 'email', 'user_email', 'url', 'user_url', 'registered', 'user_registered', 'post_count' );
		$this->woocommerce_default_user_meta_keys = array( 'billing_first_name', 'billing_last_name', 'billing_email', 'billing_phone', 'billing_country', 'billing_address_1', 'billing_city', 'billing_state', 'billing_postcode', 'shipping_first_name', 'shipping_last_name', 'shipping_country', 'shipping_address_1', 'shipping_address_2', 'shipping_city', 'shipping_state', 'shipping_postcode' );
		$this->other_non_date_keys = array( 'shipping_phone', '_vat_number', '_billing_vat_number' );
	}

	function fill_total_rows( $recalculate_total_rows = false ){
		$this->total_rows = $this->get_total_rows( $recalculate_total_rows );
	}

    function get_non_date_keys(){
		return apply_filters( 'acui_export_non_date_keys', array_merge( $this->user_data, $this->woocommerce_default_user_meta_keys, $this->other_non_date_keys ) );
	}

    function maybe_order_columns_alphabetacally( $row, $args ){
        if( !$args['order_fields_alphabetically'] )
			return $row;
		
		sort( $row, SORT_LOCALE_STRING );
		return $row;
	}

	function maybe_order_row_alphabetically( $row, $user, $args ){
        if( !$args['order_fields_alphabetically'] )
			return $row;

        $row_sorted = array();
        foreach( $args['columns'] as $field ){
            $row_sorted[ $field ] = $row[ $field ];
        }

        return $row_sorted;
	}

    function maybe_order_row_filtered_columns_parameter( $row, $user, $args ){
        if( !is_array( $args['filtered_columns'] ) || count( $args['filtered_columns'] ) == 0 )
			return $row;

        $row_sorted = array();
        foreach( $args['filtered_columns'] as $field ){
            $row_sorted[ $field ] = isset( $row[ $field ] ) ? $row[ $field ] : '';
        }

        return $row_sorted;
	}

    function maybe_double_encapsulate_serialized_values( $row, $user, $args ){
        if( !$args['double_encapsulate_serialized_values'] )
			return $row;

        foreach( $args['columns'] as $field ){
            if( is_serialized( $row[ $field ] ) )
                $row[ $field ] = '"' . $row[ $field ] . '"';
        }

        return $row;
	}

    function load_columns(){
        $row = array();
		
		foreach ( $this->get_user_data() as $key ) {
            $row[ $key ] = $key;
		}

        if( count( $this->get_filtered_columns() ) == 0 || in_array( 'role', $this->get_filtered_columns() ) )
		    $row[ "role" ] = "role";

		foreach ( $this->get_user_meta_keys() as $key ) {
			$row[ $key ] = $key;
		}

		if( $this->get_include_guests() ){
			foreach( $this->get_guest_keys() as $key ){
				if( !isset( $row[ $key ] ) && ( count( $this->get_filtered_columns() ) == 0 || in_array( $key, $this->get_filtered_columns() ) ) )
					$row[ $key ] = $key;
			}
		}

		$columns = apply_filters( 'acui_export_columns', $row, array( 
			'order_fields_alphabetically' => $this->get_order_fields_alphabetically(), 
			'double_encapsulate_serialized_values' => $this->get_double_encapsulate_serialized_values(), 
			'filtered_columns' => $this->get_filtered_columns() ) );
		
		$this->set_columns_to_export( $columns );
    }

	function set_path( $path ){
		$this->path = $path;
	}

	function get_path(){
		return $this->path;
	}

    function set_columns_to_export( $columns ) {
		$this->columns_to_export = $columns;
	}

	function extract_keys_names_from_columns( $columns ){
		$elements = array_values( $columns );
		$columns_array = array();

		foreach( $elements as $element ){
			if( strpos( $element, '=>' ) !== false ){
				list( $key, $value ) = explode( '=>', $element );
				$columns_array[ trim( $key ) ] = trim( $value );
			} else {
				$columns_array[ $element ] = trim( $element );
			}
		}

		return $columns_array;
	}

	function get_columns_to_export() {
		return $this->columns_to_export;
	}

	function get_columns_keys_to_export(){
		return array_keys( $this->columns_to_export );
	}

	function get_columns_names_to_export(){
		return ( count( $this->get_filtered_columns( 'names' ) ) > 0 ) ? array_values( $this->get_filtered_columns( 'names' ) ) : $this->columns_to_export;
	}

    function set_delimiter( $delimiter ){
        switch ( $delimiter ) {
			case 'COMMA':
				$this->delimiter = ",";
				break;
			
			case 'COLON':
				$this->delimiter = ":";
				break;

			case 'SEMICOLON':
				$this->delimiter = ";";
				break;

			case 'TAB':
				$this->delimiter = "\t";
				break;

            default:
                $this->delimiter = ",";
                break;
		}
    }

    function get_delimiter() {
		return $this->delimiter;
	}

    function set_role( $role ){
        $this->role = $role;
    }

    function get_role(){
		if( empty( $this->role ) )
			return '';
		
		return is_array( $this->role ) ? $this->role : explode( ',', $this->role );
    }

    function set_from( $from ){
        $this->from = $from;
    }

    function get_from(){
        return $this->from;
    }

    function set_to( $to ){
        $this->to = $to;
    }

    function get_to(){
        return $this->to;
    }

    function set_convert_timestamp( $convert_timestamp ){
        $this->convert_timestamp = filter_var( $convert_timestamp, FILTER_VALIDATE_BOOLEAN );
    }

    function get_convert_timestamp(){
        return $this->convert_timestamp;
    }

    function set_datetime_format( $datetime_format ){
        $this->datetime_format = $datetime_format;
    }

    function get_datetime_format(){
        return $this->datetime_format;
    }

    function set_order_fields_alphabetically( $order_fields_alphabetically ){
        $this->order_fields_alphabetically = filter_var( $order_fields_alphabetically, FILTER_VALIDATE_BOOLEAN );
    }

    function get_order_fields_alphabetically(){
        return $this->order_fields_alphabetically;
    }

    function set_double_encapsulate_serialized_values( $double_encapsulate_serialized_values ){
        $this->double_encapsulate_serialized_values = filter_var( $double_encapsulate_serialized_values, FILTER_VALIDATE_BOOLEAN );
    }

    function get_double_encapsulate_serialized_values(){
        return $this->double_encapsulate_serialized_values;
    }

	function set_display_arrays_as_comma_separated_list_of_values( $display_arrays_as_comma_separated_list_of_values ){
        $this->display_arrays_as_comma_separated_list_of_values = filter_var( $display_arrays_as_comma_separated_list_of_values, FILTER_VALIDATE_BOOLEAN );
    }

    function get_display_arrays_as_comma_separated_list_of_values(){
        return $this->display_arrays_as_comma_separated_list_of_values;
    }

    function set_export_password_hashes( $export_password_hashes ){
        $this->export_password_hashes = filter_var( $export_password_hashes, FILTER_VALIDATE_BOOLEAN );
    }

    function get_export_password_hashes(){
        return (bool) apply_filters( 'acui_export_password_hashes', $this->export_password_hashes );
    }

    function set_filtered_columns( $filtered_columns ){
        if ( ! is_array( $filtered_columns ) ) {
            $filtered_columns = preg_replace( '/\s*,\s*/', ',', $filtered_columns );
        }
        $filtered_columns = ( is_array( $filtered_columns ) ) ? array_map( 'sanitize_text_field', $filtered_columns ) : explode( ',', sanitize_text_field( $filtered_columns ) );

        if( empty( $filtered_columns[0] ) )
            $filtered_columns = array();

        $this->filtered_columns = array_map( 'trim', $this->extract_keys_names_from_columns( $filtered_columns ) );
		
		if( $this->get_order_fields_alphabetically() )
			ksort( $this->filtered_columns );
    }

    function get_filtered_columns( $return = 'keys' ){
        $filtered_columns = $this->filtered_columns;

        if( !$this->get_export_password_hashes() )
            unset( $filtered_columns['user_pass'] );

        return ( $return == 'keys' ) ? array_keys( $filtered_columns ) : array_values( $filtered_columns );
    }

    function set_orderby( $orderby ){
        $this->orderby = $orderby;
    }

    function get_orderby(){
        return $this->orderby;
    }

    function set_order( $order ){
        $this->order = $order;
    }

    function get_order(){
        return $this->order;
    }

    function set_users( $users ){
        if( !is_array( $users ) )
            $users = explode( ',', (string) $users );

        $this->users = array_values( array_filter( array_map( 'absint', $users ) ) );
    }

    function set_offset_rows( $offset_rows ){
        $this->offset_rows = max( 0, (int) $offset_rows );
    }

    function get_offset_rows(){
        return $this->offset_rows;
    }

    function set_max_rows( $max_rows ){
        $this->max_rows = max( 0, (int) $max_rows );
    }

    function get_max_rows(){
        return $this->max_rows;
    }

    function get_page_range(){
        $start = $this->get_offset_rows() + ( $this->get_limit() == -1 ? 0 : ( $this->get_page() - 1 ) * $this->get_limit() );
        $end = $this->get_offset_rows() + $this->get_total_rows();

        if( $this->get_limit() != -1 )
            $end = min( $end, $start + $this->get_limit() );

        return array( $start, max( $start, $end ) );
    }

    function get_users_filter(){
        return $this->users;
    }

    function set_include_guests( $include_guests ){
        $this->include_guests = filter_var( $include_guests, FILTER_VALIDATE_BOOLEAN );
    }

    function get_include_guests(){
        return $this->include_guests && class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' );
    }

    function get_guest_keys(){
        return apply_filters( 'acui_export_guest_keys', array( 'first_name', 'last_name', 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_country', 'billing_state', 'billing_email', 'billing_phone', 'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address_1', 'shipping_address_2', 'shipping_city', 'shipping_postcode', 'shipping_country', 'shipping_state', 'shipping_phone' ) );
    }

    function get_guest_emails( $recalculate = false ){
        if( !$this->get_include_guests() )
            return array();

        $emails = $recalculate ? false : get_transient( 'acui_export_guest_emails' );

        if( !is_array( $emails ) ){
            $emails = $this->query_guest_emails();
            set_transient( 'acui_export_guest_emails', $emails, HOUR_IN_SECONDS );
        }

        return $emails;
    }

    function query_guest_emails(){
        global $wpdb;

        $excluded_statuses = array( 'trash', 'auto-draft', 'draft', 'wc-checkout-draft' );
        $placeholders = implode( ',', array_fill( 0, count( $excluded_statuses ), '%s' ) );
        $having = array();
        $having_values = array();

        if( !empty( $this->get_from() ) ){
            $having[] = 'first_order >= %s';
            $having_values[] = $this->get_from() . ' 00:00:00';
        }

        if( !empty( $this->get_to() ) ){
            $having[] = 'first_order <= %s';
            $having_values[] = $this->get_to() . ' 23:59:59';
        }

        $having_sql = empty( $having ) ? '' : ' HAVING ' . implode( ' AND ', $having );

        if( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ){
            $table = $wpdb->prefix . 'wc_orders';
            $sql = "SELECT LOWER(billing_email) AS email, MIN(date_created_gmt) AS first_order FROM {$table} WHERE type = 'shop_order' AND customer_id = 0 AND billing_email <> '' AND status NOT IN ({$placeholders}) GROUP BY LOWER(billing_email){$having_sql} ORDER BY first_order ASC";
        }
        else{
            $sql = "SELECT LOWER(email.meta_value) AS email, MIN(p.post_date_gmt) AS first_order FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} email ON email.post_id = p.ID AND email.meta_key = '_billing_email' LEFT JOIN {$wpdb->postmeta} customer ON customer.post_id = p.ID AND customer.meta_key = '_customer_user' WHERE p.post_type = 'shop_order' AND ( customer.meta_value IS NULL OR customer.meta_value IN ( '', '0' ) ) AND email.meta_value <> '' AND p.post_status NOT IN ({$placeholders}) GROUP BY LOWER(email.meta_value){$having_sql} ORDER BY first_order ASC";
        }

        $emails = $wpdb->get_col( $wpdb->prepare( $sql, array_merge( $excluded_statuses, $having_values ) ) );
        $emails = array_values( array_unique( array_filter( array_map( 'trim', $emails ), 'is_email' ) ) );

        $registered = array();
        foreach( array_chunk( $emails, 500 ) as $chunk ){
            $in = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
            $found = $wpdb->get_col( $wpdb->prepare( "SELECT LOWER(user_email) FROM {$wpdb->users} WHERE user_email IN ({$in})", $chunk ) );
            foreach( $found as $email )
                $registered[ $email ] = true;
        }

        $guests = array();
        foreach( $emails as $email ){
            if( !isset( $registered[ $email ] ) )
                $guests[] = $email;
        }

        return apply_filters( 'acui_export_guest_emails', $guests, $this );
    }

    function get_guest_row( $email ){
        $orders = wc_get_orders( array(
            'billing_email' => $email,
            'type' => 'shop_order',
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => array_keys( wc_get_order_statuses() ),
        ) );

        $orders = array_values( array_filter( $orders, function( $order ){
            return is_a( $order, 'WC_Order' ) && 0 == $order->get_customer_id();
        } ) );

        if( empty( $orders ) )
            return array();

        $last = $orders[0];
        $first = $orders[ count( $orders ) - 1 ];
        $data = array(
            'user_login' => '',
            'user_email' => $email,
            'source_user_id' => '',
            'user_pass' => '',
            'user_nicename' => '',
            'user_url' => '',
            'user_registered' => $first->get_date_created() ? $first->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
            'display_name' => trim( $last->get_billing_first_name() . ' ' . $last->get_billing_last_name() ),
            'role' => wp_roles()->is_role( 'customer' ) ? 'customer' : '',
            'first_name' => $last->get_billing_first_name(),
            'last_name' => $last->get_billing_last_name(),
            'shipping_phone' => method_exists( $last, 'get_shipping_phone' ) ? $last->get_shipping_phone() : '',
        );

        foreach( array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'country', 'state', 'email', 'phone' ) as $field ){
            $getter = 'get_billing_' . $field;
            $data[ 'billing_' . $field ] = $last->$getter();
        }

        foreach( array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'country', 'state' ) as $field ){
            $getter = 'get_shipping_' . $field;
            $data[ 'shipping_' . $field ] = $last->$getter();
        }

        return apply_filters( 'acui_export_guest_data', $data, $email, $orders );
    }

    function get_total_rows( $recalculate_total_rows = false ){
        $total_rows = ( $recalculate_total_rows ) ? 0 : get_transient( 'acui_export_total_rows' );
		
        if( empty( $total_rows ) ){
            $this->total_rows = $this->calculate_total();
        }
        else{
            $this->total_rows = $total_rows;
        }

        return $this->total_rows;
    }

    function get_total_steps(){
        return floor( $this->get_total_rows() / $this->get_limit() ) + 1;
    }

    function set_time_limit( $limit = 0 ) {
        if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) { // phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.safe_modeDeprecatedRemoved
            @set_time_limit( $limit );
        }
    }

    function maybe_define_constant( $name, $value ) {
        if ( ! defined( $name ) ) {
            define( $name, $value );
        }
    }

    function set_nocache_constants() {
		$this->maybe_define_constant( 'DONOTCACHEPAGE', true );
		$this->maybe_define_constant( 'DONOTCACHEOBJECT', true );
		$this->maybe_define_constant( 'DONOTCACHEDB', true );
	}

    function send_nocache_headers() {
        $this->set_nocache_constants();
        nocache_headers();
    }

	static function get_formats(){
		$formats = apply_filters( 'acui_export_formats', array() );
		$formats = is_array( $formats ) ? $formats : array();

		return array_merge( array( 'csv' => 'CSV' ), array_change_key_case( $formats, CASE_LOWER ) );
	}

	function set_format( $format ){
		$format = sanitize_key( (string) $format );
		$this->format = array_key_exists( $format, self::get_formats() ) ? $format : 'csv';
	}

	function get_format(){
		return $this->format;
	}

	function build_export_file(){
		$csv_path = $this->get_file_path() . '.export.csv';
		$output = @fopen( $csv_path, 'w' );

		if( !$output )
			return false;

		fwrite( $output, $this->get_headers_row_file() );

		$input = @fopen( $this->get_file_path(), 'r' );
		if( $input ){
			stream_copy_to_stream( $input, $output );
			fclose( $input );
		}

		fclose( $output );

		$file = array( 'path' => $csv_path, 'mime' => 'text/csv; charset=utf-8', 'extension' => 'csv', 'temp_files' => array( $csv_path ) );

		if( 'csv' === $this->get_format() )
			return $file;

		$converted = apply_filters( 'acui_export_convert_file', false, $csv_path, $this->get_format(), $this );

		if( is_array( $converted ) && !empty( $converted['path'] ) && @file_exists( $converted['path'] ) ){
			$file['path'] = $converted['path'];
			$file['mime'] = !empty( $converted['mime'] ) ? $converted['mime'] : 'application/octet-stream';
			$file['extension'] = $this->get_format();
			$file['temp_files'][] = $converted['path'];
		}

		return $file;
	}

	function save_export_file( $destination ){
		$file = $this->build_export_file();

		if( false === $file )
			return false;

		$destination = self::replace_extension( $destination, $file['extension'] );

		@unlink( $destination );
		$saved = @copy( $file['path'], $destination );

		foreach( $file['temp_files'] as $temp_file )
			@unlink( $temp_file );

		return $saved ? $destination : false;
	}

	static function replace_extension( $path, $extension ){
		$current = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if( $current === $extension )
			return $path;

		if( '' === $current || array_key_exists( $current, self::get_formats() ) )
			return ( '' === $current ? $path : substr( $path, 0, - strlen( $current ) - 1 ) ) . '.' . $extension;

		return $path . '.' . $extension;
	}

	function send_headers( $content_type = 'text/csv; charset=utf-8', $filename = '' ) {
		if ( function_exists( 'gc_enable' ) ) {
			gc_enable();
		}
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', 1 );
		}
		@ini_set( 'zlib.output_compression', 'Off' );
		@ini_set( 'output_buffering', 'Off' );
		@ini_set( 'output_handler', '' );
		ignore_user_abort( true );
		$this->set_time_limit( 0 );
		$this->send_nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename=' . ( '' === $filename ? $this->get_download_filename() : $filename ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
	}

	function set_filename( $filename ) {
		$this->filename = sanitize_file_name( str_replace( '.csv', '', $filename ) . '.csv' );
	}

	function get_filename() {
		return sanitize_file_name( $this->filename );
	}

	function get_download_filename( $extension = '' ) {
		$filename = $this->get_filename();
		$extension = '' === $extension ? $this->get_format() : $extension;

		if( preg_match( '/^user-export-\d+-[a-z0-9]{32}\.csv$/', $filename ) )
			$filename = 'user-export-' . wp_date( 'Y-m-d-His' ) . '.csv';

		$filename = preg_replace( '/\.csv$/i', '', $filename ) . '.' . $extension;

		return apply_filters( 'acui_export_download_filename', $filename, $this->get_filename(), $extension );
	}

	function send_content( $csv_data ) {
		echo $csv_data;
	}

	protected function get_csv_data() {
		return $this->export_rows();
	}

	protected function export_column_headers() {
		$columns = $this->get_columns_names_to_export();
		$export_row = array();
		$buffer = fopen( 'php://output', 'w' );
		ob_start();

		foreach ( $columns as $key => $value ) {
			$export_row[] = $this->format_data( $value );
		}

		$this->fputcsv( $buffer, $export_row );

		return ob_get_clean();
	}

	protected function get_data_to_export() {
		return $this->row_data;
	}

	protected function export_rows() {
		$data   = $this->get_data_to_export();
		$buffer = fopen( 'php://output', 'w' );
		ob_start();

		array_walk( $data, array( $this, 'export_row' ), $buffer );

		return ob_get_clean();
	}

	protected function export_row( $row_data, $key, $buffer ) {
		$this->fputcsv( $buffer, $row_data );
		++ $this->exported_row_count;
	}

	function get_limit() {
		return $this->limit;
	}

	function set_limit( $limit ) {
		$this->limit = ( $limit == -1 ) ? $limit : absint( $limit );
	}

	function escape_data( $data ) {
		$active_content_triggers = array( '=', '+', '-', '@' );

		if ( in_array( mb_substr( $data, 0, 1 ), $active_content_triggers, true ) ) {
			$data = "'" . $data;
		}

		return $data;
	}

	function format_data( $data ) {
		if ( is_bool( $data ) ) {
			$data = $data ? 1 : 0;
		}

		$use_mb = function_exists( 'mb_convert_encoding' );
		if ( $use_mb ) {
			$encoding = mb_detect_encoding( $data, mb_detect_order(), true );
			if ( $encoding ) {
				$data = mb_convert_encoding( $data, 'UTF-8', $encoding );
			} else {
				$data = mb_convert_encoding( $data, 'UTF-8', 'UTF-8' );
			}
		} else {
			$data = wp_check_invalid_utf8( $data, true );
		}

		return $this->escape_data( $data );
	}
    
	protected function implode_values( $values ) {
		$values_to_implode = array();

		foreach ( $values as $value ) {
			$value               = (string) is_scalar( $value ) ? $value : '';
			$values_to_implode[] = str_replace( ',', '\\,', $value );
		}

		return implode( ', ', $values_to_implode );
	}

	protected function fputcsv( $buffer, $export_row ) {
		foreach ( $export_row as $key => $value ) {
			if ( is_string( $value ) )
				$export_row[ $key ] = str_replace( "\0", '', $value );
		}

		if ( version_compare( PHP_VERSION, '5.5.4', '<' ) ) {
			ob_start();
			$temp = fopen( 'php://output', 'w' );
    		fputcsv( $temp, $export_row, $this->get_delimiter(), '"' );
			fclose( $temp );
			$row = ob_get_clean();
			$row = str_replace( '\\"', '\\""', $row );
			fwrite( $buffer, $row );
		} else {
			fputcsv( $buffer, $export_row, $this->get_delimiter(), '"', ACUIHelper()->get_csv_escape() );
		}
	}

    protected function get_file_path() {
		if( !empty( $this->get_path() ) )
			return $this->get_path();
		
		return ACUI_Private_Files::get_path( $this->get_filename() );
	}

    protected function get_headers_row_file_path() {
		return $this->get_file_path() . '.headers';
	}

	function get_headers_row_file() {
		$file = chr( 239 ) . chr( 187 ) . chr( 191 ) . $this->export_column_headers();

		if ( @file_exists( $this->get_headers_row_file_path() ) ){
			$file = @file_get_contents( $this->get_headers_row_file_path() );
		}

		return $file;
	}

	function get_file() {
		$file = '';
		if ( @file_exists( $this->get_file_path() ) ){
			$file = @file_get_contents( $this->get_file_path() );
		} else {
			@file_put_contents( $this->get_file_path(), '' );
			@chmod( $this->get_file_path(), 0664 );
		}
		return $file;
	}

	function export() {
		$file = $this->build_export_file();

		if( false === $file ){
			$this->send_headers();
			$this->send_content( $this->get_headers_row_file() . $this->get_file() );
		}
		else{
			$this->send_headers( $file['mime'], $this->get_download_filename( $file['extension'] ) );
			while( ob_get_level() )
				ob_end_clean();

			header( 'Content-Length: ' . filesize( $file['path'] ) );
			readfile( $file['path'] );
		}

		do_action( 'acui_export_before_delete_file', $this->get_file_path() );

		if( false !== $file ){
			foreach( $file['temp_files'] as $temp_file )
				@unlink( $temp_file );
		}

		@unlink( $this->get_file_path() );
		@unlink( $this->get_headers_row_file_path() );
		die();
	}
    
    function generate_file() {
        if( 1 === $this->get_page() ){
			@unlink( $this->get_file_path() );

			$this->get_file();
		}
		$this->prepare_data_to_export();
		$this->write_csv_data( $this->get_csv_data() );
	}

    function calculate_total(){
        $total_users = count( $this->get_user_id_list( true ) );
        $total_rows = max( 0, $total_users + count( $this->get_guest_emails( true ) ) - $this->get_offset_rows() );

        if( $this->get_max_rows() > 0 )
            $total_rows = min( $total_rows, $this->get_max_rows() );

        set_transient( 'acui_export_total_users', $total_users, HOUR_IN_SECONDS );
        set_transient( 'acui_export_total_rows', $total_rows, HOUR_IN_SECONDS );

        return $total_rows;
    }

    function get_total_users(){
        $total_users = get_transient( 'acui_export_total_users' );

        if( false === $total_users ){
            $total_users = count( $this->get_user_id_list( true ) );
            set_transient( 'acui_export_total_users', $total_users, HOUR_IN_SECONDS );
        }

        return (int) $total_users;
    }

	protected function write_csv_data( $data ) {
		if ( ! file_exists( $this->get_file_path() ) || ! is_writeable( $this->get_file_path() ) ) {
			return false;
		}

		$fp = fopen( $this->get_file_path(), 'a+' );

		if ( $fp ) {
			fwrite( $fp, $data );
			fclose( $fp );
		}

		if ( 100 <= $this->get_percent_complete() ) {
			$header = chr( 239 ) . chr( 187 ) . chr( 191 ) . $this->export_column_headers();

			@file_put_contents( $this->get_headers_row_file_path(), $header );
		}

	}

	function get_page() {
		return $this->page;
	}

    function set_page( $page ) {
		$this->page = absint( $page );
	}

	function get_total_exported() {
		return ( ( $this->get_page() - 1 ) * $this->get_limit() ) + $this->exported_row_count;
	}

	function get_percent_complete() {
        return $this->get_total_rows() ? floor( ( $this->get_total_exported() / $this->get_total_rows() ) * 100 ) : 100;
	}
    
    function get_user_data(){
        $user_data = $this->user_data;

        if( !$this->get_export_password_hashes() )
            $user_data = array_values( array_diff( $user_data, array( 'user_pass' ) ) );

        if( count( $this->get_filtered_columns() ) == 0 )
            return $user_data;
        
        $result = array();
        foreach( $user_data as $column ){
            if( in_array( $column, $this->get_filtered_columns() ) )
                $result[] = $column;
        }
        
        return $result;
    }

    function get_user_id_list( $calculate_total = false ){
        $args = array( 'fields' => array( 'ID' ), 'order' => $this->get_order() );

        if( !$calculate_total ){
            list( $start, $end ) = $this->get_page_range();
            $end = min( $end, $this->get_total_users() );

            if( $start >= $end )
                return array();

            $args['number'] = $end - $start;
            $args['offset'] = $start;
        }

		if( !empty( $this->get_users_filter() ) ){
			$args['include'] = $this->get_users_filter();
		}
		else{
			if( !empty( $this->get_role() ) )
				$args['role__in'] = $this->get_role();

			$date_query = array();

			if( !empty( $this->get_from() ) )
				$date_query[] = array( 'after' => $this->get_from(), 'inclusive' => true );
			
			if( !empty( $this->get_to() ) )
				$date_query[] = array( 'before' => $this->get_to(), 'inclusive' => true );

			if( !empty( $date_query ) ){
				$date_query['inclusive'] = true;
				$args['date_query'] = $date_query;
			}
		}

        if( !empty( $this->get_orderby() ) ){
			if( in_array( $this->get_orderby(), $this->accepted_order_by ) )
			    $args['orderby'] = $this->get_orderby();
            else{
                $args['orderby'] = "meta_value";
                $args['meta_key'] = $this->get_orderby();
            }
		}

		$args['meta_query'] = array(
			'relation' => 'OR',
			array( 'key' => '_acui_exclude_from_export', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_acui_exclude_from_export', 'value' => '1', 'compare' => '!=' ),
		);

		$users = get_users( $args );

        if( $calculate_total )
            return $users;

		$list = array();

	    foreach ( $users as $user ) {
	    	$list[] = $user->ID;
	    }

	    return $list;
	}
    
	function prepare_data_to_export() {
        $this->row_data = array();
		$users = $this->get_user_id_list();

		foreach ( $users as $user ) {
            $row = array();
			$userdata = get_userdata( $user );

            foreach ( $this->get_user_data() as $key ) {
				if( $key == 'source_user_id' )
					$row[ $key ] = $this->prepare( $key, $userdata->ID, $this->get_datetime_format(), $user );
				else
					$row[ $key ] = $this->prepare( $key, $userdata->data->{$key}, $this->get_datetime_format(), $user );
			}

            if( count( $this->get_filtered_columns() ) == 0 || in_array( 'role', $this->get_filtered_columns() ) )
			    $row['role'] = implode( ',', ACUIHelper()->get_roles_by_user_id( $user ) );

			foreach ( $this->get_user_meta_keys() as $key ) {
				$row[ $key ] = $this->prepare( $key, get_user_meta( $user, $key, true ), $this->get_datetime_format(), $user );
			}

			if( $this->get_include_guests() ){
				foreach( array_values( $this->get_columns_to_export() ) as $key ){
					if( !array_key_exists( $key, $row ) )
						$row[ $key ] = $this->prepare( $key, get_user_meta( $user, $key, true ), $this->get_datetime_format(), $user );
				}
			}

            if( count( $this->get_filtered_columns() ) == 0 || in_array( 'user_email', $this->get_filtered_columns() ) || in_array( 'user_login', $this->get_filtered_columns() ) )
			    $row = $this->maybe_fill_empty_data( $row, $user, $this->get_filtered_columns() );
			
			$row = apply_filters( 'acui_export_data', $row, $user, array( 
				'columns' => $this->get_columns_to_export(), 
				'datetime_format' => $this->get_datetime_format(), 
				'order_fields_alphabetically' => $this->get_order_fields_alphabetically(), 
				'double_encapsulate_serialized_values' => $this->get_double_encapsulate_serialized_values(),
				'filtered_columns' => $this->get_filtered_columns() 
			) );

			foreach( $row as $key => $value )
				$row[ $key ] = apply_filters( 'acui_export_data_' . $key, $value );

            $this->row_data[] = array_values( $row );
		}

		if( $this->get_include_guests() )
			$this->prepare_guests_to_export();
	}

	function prepare_guests_to_export(){
		list( $start, $end ) = $this->get_page_range();
		$total_users = $this->get_total_users();

		if( $end <= $total_users )
			return;

		$from = max( $start, $total_users );
		$emails = array_slice( $this->get_guest_emails(), $from - $total_users, $end - $from );

		foreach( $emails as $email ){
			$guest = $this->get_guest_row( $email );

			if( empty( $guest ) )
				continue;

			$row = array();
			foreach( array_values( $this->get_columns_to_export() ) as $key ){
				if( !isset( $guest[ $key ] ) )
					$row[ $key ] = '';
				elseif( $key == 'role' )
					$row[ $key ] = $guest[ $key ];
				else
					$row[ $key ] = $this->prepare( $key, $guest[ $key ], $this->get_datetime_format(), 0 );
			}

			$row = apply_filters( 'acui_export_data', $row, 0, array( 
				'columns' => $this->get_columns_to_export(), 
				'datetime_format' => $this->get_datetime_format(), 
				'order_fields_alphabetically' => $this->get_order_fields_alphabetically(), 
				'double_encapsulate_serialized_values' => $this->get_double_encapsulate_serialized_values(),
				'filtered_columns' => $this->get_filtered_columns(),
				'guest_email' => $email,
			) );

			foreach( $row as $key => $value )
				$row[ $key ] = apply_filters( 'acui_export_data_' . $key, $value );

			$this->row_data[] = array_values( $row );
		}
	}

    function prepare( $key, $value, $datetime_format = '', $user = 0 ){
		$original_value = $value;

		$value = $this->clean_bad_characters_formulas( $value, $key, $user );

        if( has_filter( 'acui_export_prepare' ) ){
            return apply_filters( 'acui_export_prepare', $value, $original_value );
        }

		if( $key == 'role' ){
			return implode( ',', ACUIHelper()->get_roles_by_user_id( $user ) );
		}

		if( 
			( is_array( $value ) || ( is_string( $value ) && is_array( @unserialize( $value, array( 'allowed_classes' => false ) ) ) ) ) 
			&& 
			$this->get_display_arrays_as_comma_separated_list_of_values() 
		){
			$value_array = is_serialized( $value ) ? unserialize( $value, array( 'allowed_classes' => false ) ) : $value;

			if( ACUIHelper()->array_one_dimension( $value_array ) && ACUIHelper()->array_correlative_index( $value_array ) && !ACUIHelper()->array_contains_wp_error( $value_array ) ){
				return implode( ',', $value_array );
			}
		}

		if( is_array( $value ) || is_object( $value ) ){
			return serialize( $value );
		}

		if( in_array( $key, $this->get_non_date_keys() ) || empty( $datetime_format ) ){
			return $value;
		}

		if( $this->get_convert_timestamp() && (string)(int)$value == $value && ( ( $this->is_valid_timestamp( $value ) && strlen( $value ) > 4 ) || in_array( $key, apply_filters( 'acui_export_timestamp_keys', array( 'wc_last_active' ) ) ) ) ){ // dates in timestamp format
			return date( $datetime_format, $value );
		}

        return $value;
	}

    function clean_bad_characters_formulas( $value, $key, $user ){
        if( !is_string( $value ) )
            return $value;

		if( strlen( $value ) == 0 )
			return $value;

		$bad_characters = array( "\t", "\r", "+", "-", "=", "@" );
		$first_character = substr( $value, 0, 1 );
		if( in_array( $first_character, $bad_characters ) ){
			$value = "'" . $first_character . substr( $value, 1 );
			$this->add_bad_character_formulas_values_cleaned( $key, $value, $user );
		}

		return $value;
	}

	function add_bad_character_formulas_values_cleaned( $key, $value, $user ){
		$current = get_transient( 'acui_export_bad_character_formulas_values_cleaned' );
		if( !is_array( $current ) )
			$current = array();

		$current[] = array( 'key' => $key, 'value' => $value, 'user_id' => $user );
		set_transient( 'acui_export_bad_character_formulas_values_cleaned', $current );
	}

    function is_valid_timestamp( $timestamp ){
		return ( (string) (int) $timestamp === $timestamp ) && ( $timestamp <= PHP_INT_MAX ) && ( $timestamp >= ~PHP_INT_MAX );
	}

    function maybe_fill_empty_data( $row, $user_id, $filtered_columns ){
		if( empty( $row['user_login'] ) || empty( $row['user_email'] ) ){
			$user = new WP_User( $user_id );

			if( $user->ID == 0 )
				return $row;

            if( count( $filtered_columns ) == 0 || in_array( 'user_login', $filtered_columns ) )
			    $row['user_login'] = $user->user_login;

            if( count( $filtered_columns ) == 0 || in_array( 'user_email', $filtered_columns ) )
			    $row['user_email'] = $user->user_email;
		}
		
		return $row;
	}

    function get_user_meta_keys() {
	    global $wpdb;
	    $meta_keys = array();

        $usermeta = get_transient( 'acui_export_user_meta_keys' );
		
        if( empty( $usermeta ) ){
            $usermeta = $wpdb->get_results( "SELECT distinct BINARY($wpdb->usermeta.meta_key) as meta_key FROM $wpdb->usermeta", ARRAY_A );
            set_transient( 'acui_export_user_meta_keys', $usermeta, HOUR_IN_SECONDS );
        }

	  	foreach( $usermeta as $key => $value) {
			if( $value["meta_key"] == 'role' || $value["meta_key"] == 'source_user_id' )
				continue;

            if( count( $this->get_filtered_columns() ) == 0 || in_array( $value["meta_key"], $this->get_filtered_columns() ) )
			    $meta_keys[] = $value["meta_key"];
		}

	    return apply_filters( 'acui_export_get_user_meta_keys', $meta_keys );
	}
}
