<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ACUI_Review_Notice{
    const META = 'acui_review_notice';
    const REVIEW_URL = 'https://wordpress.org/support/plugin/import-users-from-csv-with-meta/reviews/#new-post';

    function hooks(){
        add_action( 'wp_ajax_acui_review_notice', array( $this, 'ajax_answer' ) );
    }

    static function should_show( $results, $errors_totals ){
        if( !apply_filters( 'acui_show_review_notice', true ) )
            return false;

        $status = get_user_meta( get_current_user_id(), self::META, true );

        if( $status === 'done' )
            return false;

        if( is_numeric( $status ) && time() < (int) $status )
            return false;

        $imported = ( isset( $results['created'] ) ? (int) $results['created'] : 0 ) + ( isset( $results['updated'] ) ? (int) $results['updated'] : 0 );
        $errors = ( is_array( $errors_totals ) && isset( $errors_totals['errors'] ) ) ? (int) $errors_totals['errors'] : 0;

        return $imported > 0 && $errors === 0;
    }

    static function get_html( $results, $errors_totals ){
        if( !self::should_show( $results, $errors_totals ) )
            return '';

        $imported = (int) $results['created'] + (int) $results['updated'];

        ob_start();
        ?>
        <div class="acui-review-notice" role="status">
            <p class="acui-review-notice__title"><?php printf( _n( '%s user imported without errors.', '%s users imported without errors.', $imported, 'import-users-from-csv-with-meta' ), number_format_i18n( $imported ) ); ?></p>
            <p><?php _e( 'If the plugin saved you time, a review on WordPress.org helps other people find it and helps me keep improving it. It only takes a minute.', 'import-users-from-csv-with-meta' ); ?></p>
            <p class="acui-review-notice__signature"><?php _e( 'Thanks, Javier (plugin author)', 'import-users-from-csv-with-meta' ); ?></p>
            <p class="acui-review-notice__actions">
                <a href="<?php echo esc_url( self::REVIEW_URL ); ?>" target="_blank" rel="noopener" class="button button-primary" data-acui-review="done"><?php _e( 'Leave a review', 'import-users-from-csv-with-meta' ); ?></a>
                <button type="button" class="button" data-acui-review="later"><?php _e( 'Maybe later', 'import-users-from-csv-with-meta' ); ?></button>
                <button type="button" class="button-link" data-acui-review="done"><?php _e( 'I already did', 'import-users-from-csv-with-meta' ); ?></button>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    function ajax_answer(){
        check_ajax_referer( 'acui_review_notice', 'security' );

        if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
            wp_send_json_error();

        $answer = isset( $_POST['answer'] ) ? sanitize_key( $_POST['answer'] ) : '';
        $value = ( $answer === 'later' ) ? time() + 30 * DAY_IN_SECONDS : 'done';

        update_user_meta( get_current_user_id(), self::META, $value );

        wp_send_json_success();
    }
}

$acui_review_notice = new ACUI_Review_Notice();
$acui_review_notice->hooks();
