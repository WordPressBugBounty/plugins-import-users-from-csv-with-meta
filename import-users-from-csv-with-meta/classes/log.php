<?php
if ( ! defined( 'ABSPATH' ) )
    exit;

class ACUI_Log {

    static function admin_gui() {
        $log = get_option( 'acui_last_import_log', null );
        ?>
        <div style="margin-top: 20px;">
        <?php if ( empty( $log ) || empty( $log['html'] ) ) : ?>
            <p><?php _e( 'No import has been executed yet.', 'import-users-from-csv-with-meta' ); ?></p>
        <?php else : ?>
            <h3><?php printf( __( 'Last import: %s', 'import-users-from-csv-with-meta' ), esc_html( $log['date'] ) ); ?></h3>
            <?php echo $log['html']; ?>
            <?php ACUIHelper()->execute_datatable(); ?>
            <p style="margin-top: 16px;">
                <a href="<?php echo esc_url( admin_url( 'tools.php?page=acui&tab=homepage' ) ); ?>" class="button button-primary">
                    <?php _e( 'New import', 'import-users-from-csv-with-meta' ); ?>
                </a>
                &nbsp;
                <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="button button-secondary">
                    <?php _e( 'View users', 'import-users-from-csv-with-meta' ); ?>
                </a>
            </p>
        <?php endif; ?>
        </div>
        <?php
    }
}
