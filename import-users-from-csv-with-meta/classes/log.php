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

        $has_content = !empty( $log ) && ( !empty( $log['html_summary'] ) || !empty( $log['html'] ) );
        ?>
        <div style="margin-top: 20px;">
        <?php if ( !$has_content ) : ?>
            <p><?php _e( 'No import has been executed yet.', 'import-users-from-csv-with-meta' ); ?></p>
        <?php else : ?>
            <p style="color:#646970; margin-bottom:4px;">
                <?php printf( __( 'Last import: %s', 'import-users-from-csv-with-meta' ), '<strong>' . esc_html( $log['date'] ) . '</strong>' ); ?>
            </p>

            <?php if ( isset( $log['html_summary'] ) ) : // structured format (new) ?>

                <?php echo wp_kses_post( $log['html_summary'] ); ?>

                <?php if ( !empty( $log['html_errors'] ) ) : ?>
                <details class="acui-log-section" open>
                    <summary class="acui-log-section__summary">
                        <?php _e( 'Errors, warnings and notices', 'import-users-from-csv-with-meta' ); ?>
                        <span class="acui-log-section__chevron">&#9660;</span>
                    </summary>
                    <div class="acui-log-section__body">
                        <?php echo wp_kses_post( $log['html_errors'] ); ?>
                    </div>
                </details>
                <?php endif; ?>

                <details class="acui-log-section">
                    <summary class="acui-log-section__summary">
                        <?php _e( 'Import rows', 'import-users-from-csv-with-meta' ); ?>
                        <span class="acui-log-section__chevron">&#9660;</span>
                    </summary>
                    <div class="acui-log-section__body">
                        <?php echo wp_kses_post( $log['html_rows'] ); ?>
                    </div>
                </details>

                <script>
                jQuery( document ).ready( function( $ ) {
                    $( 'details.acui-log-section' ).on( 'toggle', function() {
                        if ( ! this.open ) return;
                        var $section = $( this );
                        var $rows = $section.find( '.acui-import-rows' );
                        if ( $rows.length > 1 ) {
                            var $first = $rows.first();
                            $rows.not( ':first' ).each( function() {
                                $first.find( 'tbody' ).append( $( this ).find( 'tbody tr' ) );
                                $( this ).closest( '.wrap' ).remove();
                            } );
                        }
                        if ( $rows.length && ! $.fn.DataTable.isDataTable( $rows.first()[0] ) ) {
                            $rows.first().DataTable( { scrollX: true } );
                        }
                        var $errors = $section.find( '#acui_errors' );
                        if ( $errors.length && ! $.fn.DataTable.isDataTable( $errors[0] ) ) {
                            $errors.DataTable( { scrollX: true } );
                        }
                    } );
                } );
                </script>

            <?php else : // legacy format ?>
                <?php echo wp_kses_post( $log['html'] ); ?>
                <?php ACUIHelper()->execute_datatable(); ?>
            <?php endif; ?>

            <p style="margin-top: 20px;">
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
