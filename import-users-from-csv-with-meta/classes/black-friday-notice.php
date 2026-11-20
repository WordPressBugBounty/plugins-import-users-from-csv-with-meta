<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ACUI_Black_Friday_Notice{
    const META = 'acui_black_friday_2026';
    const START = '2026-11-27 00:00:00';
    const END = '2026-11-30 23:59:59';
    const TIMEZONE = 'Europe/Madrid';
    const SCREENS = array( 'tools_page_acui', 'plugins' );

    function hooks(){
        add_action( 'admin_notices', array( $this, 'show' ) );
        add_action( 'wp_ajax_acui_black_friday_notice', array( $this, 'ajax_dismiss' ) );
    }

    static function timestamp( $date ){
        return ( new DateTimeImmutable( $date, new DateTimeZone( self::TIMEZONE ) ) )->getTimestamp();
    }

    static function is_running(){
        $now = time();
        return $now >= self::timestamp( self::START ) && $now <= self::timestamp( self::END );
    }

    function should_show(){
        if( !self::is_running() || !apply_filters( 'acui_show_black_friday_notice', true ) )
            return false;

        if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
            return false;

        $screen = get_current_screen();
        if( !$screen || !in_array( $screen->id, self::SCREENS, true ) )
            return false;

        return get_user_meta( get_current_user_id(), self::META, true ) !== 'done';
    }

    function show(){
        if( !$this->should_show() )
            return;

        $end = wp_date( get_option( 'date_format' ), self::timestamp( self::END ) );
        ?>
        <div class="notice notice-info inline is-dismissible acui-addon-notice" id="acui-black-friday-notice" style="border-left-color:#111;">
            <p><strong><?php _e( 'Black Friday at Import and export users and customers', 'import-users-from-csv-with-meta' ); ?></strong></p>
            <p><?php printf( __( 'Until %s: <strong>40%% off All Access</strong> (every premium addon in one subscription) and <strong>30%% off any single addon</strong>, on the first year. Our only sale of the year.', 'import-users-from-csv-with-meta' ), esc_html( $end ) ); ?></p>
            <p>
                <a href="<?php echo esc_url( ACUI_Helper::premium_url( 'all-access', 'blackfriday' ) ); ?>" class="button button-primary" target="_blank" rel="noopener"><?php _e( 'Get All Access with 40% off', 'import-users-from-csv-with-meta' ); ?></a>
                <a href="<?php echo esc_url( add_query_arg( array( 'utm_source' => 'wp-free-dashboard', 'utm_medium' => 'import-users-from-csv-with-meta', 'utm_campaign' => 'blackfriday' ), 'https://import-wp.com/pricing/' ) ); ?>" class="button" target="_blank" rel="noopener"><?php _e( 'See all the addons', 'import-users-from-csv-with-meta' ); ?></a>
            </p>
        </div>
        <script>
        document.addEventListener( 'click', function( event ){
            if( !event.target.closest || !event.target.closest( '#acui-black-friday-notice .notice-dismiss' ) )
                return;

            var data = new FormData();
            data.append( 'action', 'acui_black_friday_notice' );
            data.append( 'security', '<?php echo esc_js( wp_create_nonce( 'acui_black_friday_notice' ) ); ?>' );
            fetch( '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body: data, credentials: 'same-origin' } );
        } );
        </script>
        <?php
    }

    function ajax_dismiss(){
        check_ajax_referer( 'acui_black_friday_notice', 'security' );

        if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
            wp_send_json_error();

        update_user_meta( get_current_user_id(), self::META, 'done' );

        wp_send_json_success();
    }
}

$acui_black_friday_notice = new ACUI_Black_Friday_Notice();
$acui_black_friday_notice->hooks();
