<?php
if ( ! defined( 'ABSPATH' ) )
    exit;

class ACUI_History {

    const OPTION = 'acui_history';

    const MAX_MESSAGES = 20;

    static function hooks(){
        add_action( 'admin_post_acui_history_clear', array( __CLASS__, 'clear' ) );
    }

    static function max_entries(){
        return max( 1, (int) apply_filters( 'acui_history_max_entries', 100 ) );
    }

    static function get(){
        $entries = get_option( self::OPTION, array() );

        return is_array( $entries ) ? $entries : array();
    }

    static function add( $entry ){
        $user = wp_get_current_user();

        $entry = wp_parse_args( $entry, array(
            'type' => 'import',
            'source' => 'manual',
            'file' => '',
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'ignored' => 0,
            'errors' => 0,
            'warnings' => 0,
            'rows' => 0,
            'details' => array(),
            'messages' => array(),
        ) );

        $entry['time'] = time();
        $entry['user_id'] = $user->ID;
        $entry['user'] = $user->ID ? $user->user_login : '';
        $entry['file'] = sanitize_text_field( wp_basename( (string) $entry['file'] ) );
        $entry['messages'] = self::prepare_messages( $entry['messages'] );

        $entry = apply_filters( 'acui_history_entry', $entry );

        if( empty( $entry ) )
            return;

        $entries = self::get();
        array_unshift( $entries, $entry );
        $entries = array_slice( $entries, 0, self::max_entries() );

        update_option( self::OPTION, $entries, false );
    }

    static function prepare_messages( $errors ){
        $messages = array();

        foreach( (array) $errors as $error ){
            if( count( $messages ) >= self::MAX_MESSAGES )
                break;

            if( !is_array( $error ) || !isset( $error['message'] ) )
                continue;

            $messages[] = array(
                'row' => isset( $error['row'] ) ? (int) $error['row'] : 0,
                'type' => isset( $error['type'] ) ? sanitize_key( $error['type'] ) : 'error',
                'message' => wp_strip_all_tags( (string) $error['message'] ),
            );
        }

        return $messages;
    }

    static function count_errors( $errors, $type ){
        $count = 0;

        foreach( (array) $errors as $error ){
            if( is_array( $error ) && isset( $error['type'] ) && $error['type'] === $type )
                $count++;
        }

        return $count;
    }

    static function add_import( $source, $file, $results, $errors, $ignored = 0 ){
        $results = is_array( $results ) ? $results : array();
        $errors_count = self::count_errors( $errors, 'error' );
        $ignored = isset( $results['ignored'] ) ? (int) $results['ignored'] : (int) $ignored;

        self::add( array(
            'type' => 'import',
            'source' => $source,
            'file' => $file,
            'created' => isset( $results['created'] ) ? (int) $results['created'] : 0,
            'updated' => isset( $results['updated'] ) ? (int) $results['updated'] : 0,
            'deleted' => isset( $results['deleted'] ) ? (int) $results['deleted'] : 0,
            'ignored' => max( 0, $ignored - $errors_count ),
            'errors' => $errors_count,
            'warnings' => self::count_errors( $errors, 'warning' ),
            'messages' => $errors,
        ) );
    }

    static function clear(){
        if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) || !check_admin_referer( 'acui_history_clear' ) )
            wp_die( __( 'You are not allowed to do this.', 'import-users-from-csv-with-meta' ) );

        delete_option( self::OPTION );

        wp_safe_redirect( admin_url( 'tools.php?page=acui&tab=log-history&cleared=1' ) );
        exit;
    }

    static function source_label( $entry ){
        $labels = array(
            'manual' => __( 'Backend', 'import-users-from-csv-with-meta' ),
            'frontend' => __( 'Frontend', 'import-users-from-csv-with-meta' ),
            'cron' => __( 'Recurring', 'import-users-from-csv-with-meta' ),
        );

        return isset( $labels[ $entry['source'] ] ) ? $labels[ $entry['source'] ] : $entry['source'];
    }

    static function result_text( $entry ){
        if( $entry['type'] === 'export' )
            return sprintf( _n( '%s row exported', '%s rows exported', $entry['rows'], 'import-users-from-csv-with-meta' ), number_format_i18n( $entry['rows'] ) );

        $parts = array(
            sprintf( __( '%s created', 'import-users-from-csv-with-meta' ), number_format_i18n( $entry['created'] ) ),
            sprintf( __( '%s updated', 'import-users-from-csv-with-meta' ), number_format_i18n( $entry['updated'] ) ),
        );

        if( $entry['deleted'] )
            $parts[] = sprintf( __( '%s deleted', 'import-users-from-csv-with-meta' ), number_format_i18n( $entry['deleted'] ) );

        if( $entry['ignored'] )
            $parts[] = sprintf( __( '%s skipped', 'import-users-from-csv-with-meta' ), number_format_i18n( $entry['ignored'] ) );

        return implode( ' · ', $parts );
    }

    static function admin_gui(){
        $entries = self::get();
        ?>
        <div class="acui-history">
            <?php if( isset( $_GET['cleared'] ) ): ?>
            <div class="notice notice-success inline"><p><?php _e( 'The history has been cleared.', 'import-users-from-csv-with-meta' ); ?></p></div>
            <?php endif; ?>

            <p class="description"><?php printf( __( 'The last %s imports and exports made with this plugin, with who made them, the file and the result.', 'import-users-from-csv-with-meta' ), number_format_i18n( self::max_entries() ) ); ?></p>

            <?php if( empty( $entries ) ): ?>
            <p><?php _e( 'No import or export has been made since the history was enabled.', 'import-users-from-csv-with-meta' ); ?></p>
            <?php else: ?>
            <div class="acui-history-table-wrapper">
            <table class="widefat striped acui-history-table">
                <thead>
                    <tr>
                        <th><?php _e( 'Date', 'import-users-from-csv-with-meta' ); ?></th>
                        <th><?php _e( 'Type', 'import-users-from-csv-with-meta' ); ?></th>
                        <th><?php _e( 'User', 'import-users-from-csv-with-meta' ); ?></th>
                        <th><?php _e( 'File', 'import-users-from-csv-with-meta' ); ?></th>
                        <th><?php _e( 'Result', 'import-users-from-csv-with-meta' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach( $entries as $entry ):
                    $entry = wp_parse_args( $entry, array( 'type' => 'import', 'source' => 'manual', 'time' => 0, 'user' => '', 'file' => '', 'created' => 0, 'updated' => 0, 'deleted' => 0, 'ignored' => 0, 'errors' => 0, 'warnings' => 0, 'rows' => 0, 'details' => array(), 'messages' => array() ) );
                    $has_more = !empty( $entry['messages'] ) || !empty( $entry['details'] );
                ?>
                    <tr>
                        <td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ) ); ?></td>
                        <td>
                            <span class="acui-history-type acui-history-type--<?php echo esc_attr( $entry['type'] ); ?>"><?php echo $entry['type'] === 'export' ? esc_html__( 'Export', 'import-users-from-csv-with-meta' ) : esc_html__( 'Import', 'import-users-from-csv-with-meta' ); ?></span>
                            <span class="acui-history-source"><?php echo esc_html( self::source_label( $entry ) ); ?></span>
                        </td>
                        <td><?php echo $entry['user'] !== '' ? esc_html( $entry['user'] ) : '&mdash;'; ?></td>
                        <td class="acui-history-file"><?php echo $entry['file'] !== '' ? esc_html( $entry['file'] ) : '&mdash;'; ?></td>
                        <td>
                            <?php echo esc_html( self::result_text( $entry ) ); ?>
                            <?php if( $entry['errors'] ): ?>
                            <span class="acui-history-badge acui-history-badge--error"><?php echo esc_html( sprintf( _n( '%s error', '%s errors', $entry['errors'], 'import-users-from-csv-with-meta' ), number_format_i18n( $entry['errors'] ) ) ); ?></span>
                            <?php endif; ?>
                            <?php if( $entry['warnings'] ): ?>
                            <span class="acui-history-badge acui-history-badge--warning"><?php echo esc_html( sprintf( _n( '%s warning', '%s warnings', $entry['warnings'], 'import-users-from-csv-with-meta' ), number_format_i18n( $entry['warnings'] ) ) ); ?></span>
                            <?php endif; ?>
                            <?php if( $has_more ): ?>
                            <details class="acui-history-details">
                                <summary><?php _e( 'Details', 'import-users-from-csv-with-meta' ); ?></summary>
                                <?php if( !empty( $entry['details'] ) ): ?>
                                <ul class="acui-history-filters">
                                    <?php foreach( (array) $entry['details'] as $label => $value ): ?>
                                    <li><strong><?php echo esc_html( $label ); ?>:</strong> <?php echo esc_html( $value ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                                <?php if( !empty( $entry['messages'] ) ): ?>
                                <ul class="acui-history-messages">
                                    <?php foreach( $entry['messages'] as $message ): ?>
                                    <li class="acui-history-message--<?php echo esc_attr( $message['type'] ); ?>">
                                        <?php if( $message['row'] ): ?><span class="acui-history-row"><?php echo esc_html( sprintf( __( 'Row %s', 'import-users-from-csv-with-meta' ), $message['row'] ) ); ?></span><?php endif; ?>
                                        <?php echo esc_html( $message['message'] ); ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if( count( $entry['messages'] ) >= self::MAX_MESSAGES ): ?>
                                <p class="description"><?php printf( __( 'Only the first %s messages are kept.', 'import-users-from-csv-with-meta' ), self::MAX_MESSAGES ); ?></p>
                                <?php endif; ?>
                                <?php endif; ?>
                            </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acui-history-clear" onsubmit="return confirm( '<?php echo esc_js( __( 'Do you want to clear the whole history?', 'import-users-from-csv-with-meta' ) ); ?>' );">
                <input type="hidden" name="action" value="acui_history_clear">
                <?php wp_nonce_field( 'acui_history_clear' ); ?>
                <button type="submit" class="button"><?php _e( 'Clear history', 'import-users-from-csv-with-meta' ); ?></button>
            </form>
            <?php endif; ?>
        </div>
        <?php
    }
}

ACUI_History::hooks();
