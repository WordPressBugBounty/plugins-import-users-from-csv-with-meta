<?php
if ( ! defined( 'ABSPATH' ) )
    exit;

class ACUI_Log {

    static function admin_gui() {
        $log = get_option( 'acui_last_import_log', null );

        // Only show entries from manual imports; recurring imports have their own log (tab=cron-log)
        if ( is_array( $log ) && isset( $log['source'] ) && $log['source'] !== 'manual' ) {
            $log = null;
        }
        ?>
        <div style="margin-top: 20px;">
        <?php if ( empty( $log ) || empty( $log['html'] ) ) : ?>
            <p><?php _e( 'No import has been executed yet.', 'import-users-from-csv-with-meta' ); ?></p>
        <?php else : ?>
            <h3><?php printf( __( 'Last import: %s', 'import-users-from-csv-with-meta' ), esc_html( $log['date'] ) ); ?></h3>
            <?php echo wp_kses_post( $log['html'] ); ?>
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

    static function admin_gui_cron() {
        $log = get_option( 'acui_last_cron_import_log', null );
        ?>
        <div style="margin-top: 20px;">
        <?php if ( empty( $log ) || empty( $log['html'] ) ) : ?>
            <p><?php _e( 'No recurring import has been executed yet.', 'import-users-from-csv-with-meta' ); ?></p>
        <?php else : ?>
            <h3><?php printf( __( 'Last recurring import: %s', 'import-users-from-csv-with-meta' ), esc_html( $log['date'] ) ); ?></h3>
            <?php echo wp_kses_post( $log['html'] ); ?>
            <?php ACUIHelper()->execute_datatable(); ?>
            <p style="margin-top: 16px;">
                <a href="<?php echo esc_url( admin_url( 'tools.php?page=acui&tab=cron' ) ); ?>" class="button button-secondary">
                    <?php _e( 'Recurring import settings', 'import-users-from-csv-with-meta' ); ?>
                </a>
                &nbsp;
                <a href="<?php echo esc_url( admin_url( 'tools.php?page=acui&tab=cron-log' ) ); ?>" class="button button-secondary">
                    <?php _e( 'Execution log', 'import-users-from-csv-with-meta' ); ?>
                </a>
            </p>
        <?php endif; ?>
        </div>
        <?php
    }
}
