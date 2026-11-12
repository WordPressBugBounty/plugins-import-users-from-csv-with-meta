<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ACUI_CSV_Templates{
    function hooks(){
        add_action( 'admin_post_acui_csv_template', array( $this, 'download' ) );
    }

    static function role_or_default( $role ){
        return wp_roles()->is_role( $role ) ? $role : get_option( 'default_role', 'subscriber' );
    }

    static function get_templates(){
        $basic_headers = array( 'user_login', 'user_email', 'first_name', 'last_name', 'display_name', 'role', 'password' );
        $subscriber = self::role_or_default( 'subscriber' );

        $templates = array(
            'basic' => array(
                'label' => __( 'Basic users', 'import-users-from-csv-with-meta' ),
                'filename' => 'users-template.csv',
                'headers' => $basic_headers,
                'rows' => array(
                    array( 'jane.doe', 'jane.doe@example.com', 'Jane', 'Doe', 'Jane Doe', $subscriber, '' ),
                    array( 'john.smith', 'john.smith@example.com', 'John', 'Smith', 'John Smith', $subscriber, '' ),
                    array( 'maria.garcia', 'maria.garcia@example.com', 'María', 'García', 'María García', $subscriber, '' ),
                ),
            ),
        );

        if( class_exists( 'WooCommerce' ) ){
            $customer = self::role_or_default( 'customer' );
            $address_fields = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'state', 'country', 'phone' );
            $headers = array( 'user_login', 'user_email', 'first_name', 'last_name', 'role' );

            foreach( array( 'billing', 'shipping' ) as $type ){
                foreach( $address_fields as $field )
                    $headers[] = $type . '_' . $field;

                if( $type === 'billing' )
                    $headers[] = 'billing_email';
            }

            $customers = array(
                array( 'jane.doe', 'jane.doe@example.com', 'Jane', 'Doe', '', '123 Example Street', 'Apartment 4', 'Springfield', '62701', 'IL', 'US', '+1 555 0100' ),
                array( 'maria.garcia', 'maria.garcia@example.com', 'María', 'García', 'Ejemplo S.L.', 'Calle Mayor 1', '', 'Murcia', '30001', 'MU', 'ES', '+34 600 000 000' ),
                array( 'john.smith', 'john.smith@example.com', 'John', 'Smith', '', '10 Sample Road', '', 'London', 'SW1A 1AA', '', 'GB', '+44 20 7946 0000' ),
            );

            $rows = array();

            foreach( $customers as $c ){
                $address = array( $c[2], $c[3], $c[4], $c[5], $c[6], $c[7], $c[8], $c[9], $c[10], $c[11] );
                $rows[] = array_merge( array( $c[0], $c[1], $c[2], $c[3], $customer ), $address, array( $c[1] ), $address );
            }

            $templates['woocommerce'] = array(
                'label' => __( 'WooCommerce customers', 'import-users-from-csv-with-meta' ),
                'filename' => 'woocommerce-customers-template.csv',
                'headers' => $headers,
                'rows' => $rows,
            );
        }

        $templates['empty'] = array(
            'label' => __( 'Empty (headers only)', 'import-users-from-csv-with-meta' ),
            'filename' => 'empty-template.csv',
            'headers' => $basic_headers,
            'rows' => array(),
        );

        return apply_filters( 'acui_csv_templates', $templates );
    }

    static function download_url( $template ){
        return wp_nonce_url( add_query_arg( array( 'action' => 'acui_csv_template', 'template' => $template ), admin_url( 'admin-post.php' ) ), 'acui_csv_template' );
    }

    static function get_links_html(){
        $links = array();

        foreach( self::get_templates() as $key => $template )
            $links[] = '<a href="' . esc_url( self::download_url( $key ) ) . '">' . esc_html( $template['label'] ) . '</a>';

        return implode( ' · ', $links );
    }

    function download(){
        check_admin_referer( 'acui_csv_template' );

        if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
            wp_die( __( 'You are not allowed to see this content.', 'import-users-from-csv-with-meta' ) );

        $templates = self::get_templates();
        $key = isset( $_GET['template'] ) ? sanitize_key( $_GET['template'] ) : '';

        if( !isset( $templates[ $key ] ) )
            wp_die( __( 'This template does not exist.', 'import-users-from-csv-with-meta' ) );

        $template = $templates[ $key ];
        $escape = ACUIHelper()->get_csv_escape();

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $template['filename'] ) . '"' );

        $output = fopen( 'php://output', 'w' );
        fwrite( $output, "\xEF\xBB\xBF" );
        fputcsv( $output, $template['headers'], ',', '"', $escape );

        foreach( $template['rows'] as $row )
            fputcsv( $output, $row, ',', '"', $escape );

        fclose( $output );
        exit;
    }
}

$acui_csv_templates = new ACUI_CSV_Templates();
$acui_csv_templates->hooks();
