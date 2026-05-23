<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class ACUI_Cron{
	function __construct(){
		add_action( 'acui_cron_save_settings', array( $this, 'save_settings' ) );
		add_action( 'acui_cron_process', array( $this, 'process' ) );
		add_action( 'acui_cron_process_step', array( $this, 'process_step' ), 10, 3 );
		add_action( 'acui_cron_log_action', array( $this, 'handle_log_action' ) );
		add_action( 'wp_ajax_acui_fire_cron', array( $this, 'ajax_fire_cron' ) );
		add_action( 'wp_ajax_acui_fire_cron_no_session', array( $this, 'ajax_fire_cron_no_session' ) );
	}

	function clean_path_url_csv( $path_url ){
		if( filter_var( $path_url, FILTER_VALIDATE_URL) !== false )
			return $path_url;

		if( strtolower( pathinfo( $path_url, PATHINFO_EXTENSION ) ) !== 'csv' )
			return '';

		return $path_url;
	}

	function save_settings( $form_data ){
		if ( !isset( $form_data['security'] ) || !wp_verify_nonce( $form_data['security'], 'codection-security' ) ) {
			wp_die( __( 'Nonce check failed', 'import-users-from-csv-with-meta' ) );
		}

		if( !function_exists( 'as_unschedule_all_actions' ) )
			include_once( plugin_dir_path( dirname( __FILE__ ) ) . "lib/action-scheduler/action-scheduler.php" );

		$period = sanitize_text_field( $form_data[ "period" ] );

		if( isset( $form_data["cron-activated"] ) && $form_data["cron-activated"] == "1" ){
			update_option( "acui_cron_activated", true );

			as_unschedule_all_actions( 'acui_cron_process');
			as_schedule_recurring_action( time(), ACUIHelper()->get_seconds_by_period( $period ), 'acui_cron_process' );
		}
		else{
			update_option( "acui_cron_activated", false );
			as_unschedule_all_actions( 'acui_cron_process');
		}

		update_option( "acui_cron_send_mail", isset( $form_data["send-mail-cron"] ) && $form_data["send-mail-cron"] == "1" );
		update_option( "acui_cron_send_mail_updated", isset( $form_data["send-mail-updated"] ) && $form_data["send-mail-updated"] == "1" );
		update_option( "acui_cron_delete_users", isset( $form_data["cron-delete-users"] ) && $form_data["cron-delete-users"] == "1" );

        if( isset( $form_data["cron-delete-users-assign-posts"] ) )
            update_option( "acui_cron_delete_users_assign_posts", sanitize_text_field( $form_data["cron-delete-users-assign-posts"] ) );

		update_option( "acui_move_file_cron", isset( $form_data["move-file-cron"] ) && $form_data["move-file-cron"] == "1" );
		update_option( "acui_cron_path_to_move_auto_rename", isset( $form_data["path_to_move_auto_rename"] ) && $form_data["path_to_move_auto_rename"] == "1" );
		update_option( "acui_cron_allow_multiple_accounts", ( isset( $form_data["allow_multiple_accounts"] ) && $form_data["allow_multiple_accounts"] == "1" ) ? "allowed" : "not_allowed" );
		$submitted_user_id = isset( $form_data['cron_user_id'] ) ? absint( $form_data['cron_user_id'] ) : 0;
		if( $submitted_user_id && user_can( $submitted_user_id, apply_filters( 'acui_capability', 'create_users' ) ) )
			update_option( "acui_cron_user_id", $submitted_user_id );
		update_option( "acui_cron_path_to_file", $this->clean_path_url_csv( sanitize_text_field( $form_data["path_to_file"] ) ) );
		update_option( "acui_cron_path_to_move", $this->clean_path_url_csv( sanitize_text_field( $form_data["path_to_move"] ) ) );
		update_option( "acui_cron_period", sanitize_text_field( $form_data["period"] ) );
		update_option( "acui_cron_role", sanitize_text_field( $form_data["role"] ) );
		update_option( "acui_cron_update_roles_existing_users", isset( $form_data["update-roles-existing-users"] ) && $form_data["update-roles-existing-users"] == "1" );
		update_option( "acui_cron_change_role_not_present", isset( $form_data["cron-change-role-not-present"] ) && $form_data["cron-change-role-not-present"] == "1" );

        if( isset( $form_data["cron-change-role-not-present-role"] ) )
            update_option( "acui_cron_change_role_not_present_role", sanitize_text_field( $form_data["cron-change-role-not-present-role"] ) );
		?>
		<div class="updated">
	       <p><?php _e( 'Settings updated correctly', 'import-users-from-csv-with-meta' ) ?></p>
	    </div>
	    <?php
	}

	function set_cron_user(){
		$cron_user_id = absint( get_option( "acui_cron_user_id" ) );

		if( !$cron_user_id || !user_can( $cron_user_id, apply_filters( 'acui_capability', 'create_users' ) ) ){
			$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
			$cron_user_id = !empty( $admins ) ? $admins[0] : 0;
		}

		if( $cron_user_id && !is_user_logged_in() )
			wp_set_current_user( $cron_user_id );
	}

	function process(){
		$session_id = sanitize_key( uniqid( 'c' ) );
		$message = __('Import cron task - Step #1 - starts at', 'import-users-from-csv-with-meta' ) . ' ' . current_time('mysql') . '<br/>';

		$this->set_cron_user();


		$form_data = array();
		$form_data[ "acui_session_id" ] = $session_id;
		$form_data[ "path_to_file" ] = $this->clean_path_url_csv( get_option( "acui_cron_path_to_file") );
		$form_data[ "role" ] = get_option( "acui_cron_role" );
		$form_data[ "update_roles_existing_users" ] = ( get_option( "acui_cron_update_roles_existing_users" ) ) ? 'yes' : 'no';
		$form_data[ "update_emails_existing_users" ] = "no";
		$form_data[ "empty_cell_action" ] = "leave";
		$form_data[ "security" ] = wp_create_nonce( "codection-security" );

		ob_start();
		$acui_import = new ACUI_Import();
		$result = $acui_import->fileupload_process_batch_cron( $form_data );
		$message .= "<br/>" . ob_get_contents() . "<br/>";
		ob_end_clean();

		$move_file_cron = get_option( "acui_move_file_cron");

		if( $move_file_cron && ( empty( $result ) || !empty( $result['done'] ) ) ){
			$path_to_move = $this->clean_path_url_csv( get_option( "acui_cron_path_to_move") );
			rename( $form_data[ "path_to_file" ], $path_to_move );
			$this->auto_rename();
		}
		$message .= __( '--Finished at', 'import-users-from-csv-with-meta' ) . ' ' . current_time('mysql') . '<br/><br/>';

		update_option( "acui_cron_log", $message );

		if( !empty( $result['done'] ) ){
			$this->save_execution_log( $result, 1 );
		}
	}

	function process_step( $step, $initial_row, $session_id = '' ){
		$message = __('Import cron task - Step #' . $step . ' - starts at', 'import-users-from-csv-with-meta' ) . ' ' . current_time('mysql') . '<br/>';

		$this->set_cron_user();

		$form_data = array();
		$form_data[ "acui_session_id" ] = $session_id;
		$form_data[ "path_to_file" ] = $this->clean_path_url_csv( get_option( "acui_cron_path_to_file") );
		$form_data[ "role" ] = get_option( "acui_cron_role");
		$form_data[ "update_roles_existing_users" ] = ( get_option( "acui_cron_update_roles_existing_users" ) ) ? 'yes' : 'no';
		$form_data[ "update_emails_existing_users" ] = "no";
		$form_data[ "empty_cell_action" ] = "leave";
		$form_data[ "security" ] = wp_create_nonce( "codection-security" );

		ob_start();
		$acui_import = new ACUI_Import();
		$result = $acui_import->fileupload_process_batch_cron( $form_data, $step, $initial_row );
		$message .= "<br/>" . ob_get_contents() . "<br/>";
		ob_end_clean();

		if( get_option( "acui_move_file_cron") && ( empty( $result ) || !empty( $result['done'] ) ) ){
			$path_to_move = $this->clean_path_url_csv( get_option( "acui_cron_path_to_move") );
			rename( $form_data[ "path_to_file" ], $path_to_move );
			$this->auto_rename();
		}

		$message .= __( '--Finished at', 'import-users-from-csv-with-meta' ) . ' ' . current_time('mysql') . '<br/><br/>';

		update_option( "acui_cron_log", get_option( "acui_cron_log" ) . $message );

		if( !empty( $result['done'] ) ){
			$this->save_execution_log( $result, $step );
		}
	}

	function save_execution_log( $result, $steps ){
		$entry = array(
			'date'    => current_time( 'mysql' ),
			'created' => isset( $result['results']['created'] ) ? intval( $result['results']['created'] ) : 0,
			'updated' => isset( $result['results']['updated'] ) ? intval( $result['results']['updated'] ) : 0,
			'deleted' => isset( $result['results']['deleted'] ) ? intval( $result['results']['deleted'] ) : 0,
			'ignored' => isset( $result['results']['ignored'] ) ? intval( $result['results']['ignored'] ) : 0,
			'errors'  => isset( $result['errors_count'] ) ? intval( $result['errors_count'] ) : 0,
			'file'    => basename( get_option( 'acui_cron_path_to_file' ) ),
			'steps'   => intval( $steps ),
		);

		$log = get_option( 'acui_cron_execution_log', array() );
		if( !is_array( $log ) ) $log = array();

		array_unshift( $log, $entry );

		if( count( $log ) > 100 )
			$log = array_slice( $log, 0, 100 );

		update_option( 'acui_cron_execution_log', $log, false );
	}

	function auto_rename(){
		if( get_option( "acui_cron_path_to_move_auto_rename" ) != true )
			return;

		$movefile  = get_option( "acui_cron_path_to_move");

		if ( $movefile && file_exists( $movefile ) ) {
			$parts = pathinfo( $movefile );
			$filename = $parts['filename'];

			if ( $filename ){
				$date = date( 'YmdHis' );
				$newfile = $parts['dirname'] . '/' . $filename .'_' . $date . '.' . $parts['extension'];
				rename( $movefile , $newfile );
			}
		}
	}

	function handle_log_action( $form_data ){
		if( isset( $form_data['acui_clear_execution_log'] ) ){
			delete_option( 'acui_cron_execution_log' );
		}
	}

	static function get_health_status() {
		if( !function_exists( 'as_next_scheduled_action' ) )
			include_once( plugin_dir_path( dirname( __FILE__ ) ) . "lib/action-scheduler/action-scheduler.php" );

		$next_timestamp = as_next_scheduled_action( 'acui_cron_process' );
		$is_registered  = $next_timestamp !== false && $next_timestamp !== null;
		$is_activated   = (bool) get_option( 'acui_cron_activated' );

		$next_run_human = '';
		$next_run_date  = '';
		if ( $is_registered && $next_timestamp > 0 ) {
			$next_run_date  = get_date_from_gmt( date( 'Y-m-d H:i:s', $next_timestamp ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
			$diff           = $next_timestamp - time();
			if ( $diff > 0 ) {
				$next_run_human = human_time_diff( time(), $next_timestamp );
			}
		}

		$period          = get_option( 'acui_cron_period', 'hourly' );
		$loaded_periods  = wp_get_schedules();
		$period_label    = isset( $loaded_periods[ $period ]['display'] ) ? $loaded_periods[ $period ]['display'] : $period;
		$period_seconds  = isset( $loaded_periods[ $period ]['interval'] ) ? (int) $loaded_periods[ $period ]['interval'] : 0;
		$period_human    = $period_seconds > 0 ? human_time_diff( 0, $period_seconds ) : '';
		$log      = get_option( 'acui_cron_execution_log', array() );
		$last_run = ( is_array( $log ) && !empty( $log ) ) ? $log[0] : null;

		$cron_user_id   = absint( get_option( 'acui_cron_user_id' ) );
		$cron_user_data = $cron_user_id ? get_userdata( $cron_user_id ) : false;
		$cron_user_label = $cron_user_data ? $cron_user_data->user_login . ' (#' . $cron_user_id . ')' : '';

		$is_running = false;
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$running_main  = as_get_scheduled_actions( array( 'hook' => 'acui_cron_process',      'status' => 'in-progress', 'limit' => 1 ) );
			$running_steps = as_get_scheduled_actions( array( 'hook' => 'acui_cron_process_step', 'status' => 'in-progress', 'limit' => 1 ) );
			$is_running    = ! empty( $running_main ) || ! empty( $running_steps );
		}

		return compact( 'is_activated', 'is_registered', 'next_run_date', 'next_run_human', 'period', 'period_label', 'period_human', 'last_run', 'cron_user_label', 'is_running' );
	}

	static function admin_gui(){
		$upload_dir = wp_upload_dir();
		$sample_path = $upload_dir["path"] . '/test.csv';
		$sample_url = plugin_dir_url( dirname( __FILE__ ) ) . 'test.csv';

		$cron_activated = get_option( "acui_cron_activated");
		$send_mail_cron = get_option( "acui_cron_send_mail");
		$send_mail_updated = get_option( "acui_cron_send_mail_updated");
		$cron_delete_users = get_option( "acui_cron_delete_users");
		$cron_delete_users_assign_posts = get_option( "acui_cron_delete_users_assign_posts");
		$cron_change_role_not_present = get_option( "acui_cron_change_role_not_present" );
		$cron_change_role_not_present_role = get_option( "acui_cron_change_role_not_present_role" );
		$path_to_file = get_option( "acui_cron_path_to_file");
		$period = get_option( "acui_cron_period");
		$role = get_option( "acui_cron_role");
		$update_roles_existing_users = get_option( "acui_cron_update_roles_existing_users");
		$move_file_cron = get_option( "acui_move_file_cron");
		$path_to_move = get_option( "acui_cron_path_to_move");
		$path_to_move_auto_rename = get_option( "acui_cron_path_to_move_auto_rename");
		$log = get_option( "acui_cron_log");
		$allow_multiple_accounts = get_option("acui_cron_allow_multiple_accounts");

		$rest_api_execute_cron_url = home_url() . '/wp-json/import-users-from-csv-with-meta/v1/execute-cron/';

		if( empty( $cron_activated ) )
			$cron_activated = false;

		if( empty( $send_mail_cron ) )
			$send_mail_cron = false;

		if( empty( $send_mail_updated ) )
			$send_mail_updated = false;

		if( empty( $cron_delete_users ) )
			$cron_delete_users = false;

		if( empty( $update_roles_existing_users) )
			$update_roles_existing_users = false;

		if( empty( $cron_delete_users_assign_posts ) )
			$cron_delete_users_assign_posts = '';

		if( empty( $path_to_file ) )
			$path_to_file = dirname( __FILE__ ) . '/test.csv';

		if( empty( $period ) )
			$period = 'hourly';

		if( empty( $move_file_cron ) )
			$move_file_cron = false;

		if( empty( $path_to_move ) )
			$path_to_move = dirname( __FILE__ ) . '/move.csv';

		if( empty( $path_to_move_auto_rename ) )
			$path_to_move_auto_rename = false;

		if( empty( $log ) )
			$log = "No tasks done yet.";

		if( empty( $allow_multiple_accounts ) )
			$allow_multiple_accounts = "not_allowed";
		$health = self::get_health_status();
		?>
		<style>
		tr.log div.error,
		tr.log div.notice{
			display: none;
		}
		.acui-cron-layout {
			display: flex;
			gap: 24px;
			align-items: flex-start;
		}
		.acui-cron-main {
			flex: 1;
			min-width: 0;
		}
		.acui-cron-sidebar {
			width: 280px;
			flex-shrink: 0;
			position: sticky;
			top: 32px;
			align-self: flex-start;
		}
		.acui-health-card {
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			box-shadow: 0 1px 3px rgba(0,0,0,.07);
			overflow: hidden;
		}
		.acui-health-card h3 {
			margin: 0;
			padding: 12px 16px;
			font-size: 13px;
			font-weight: 600;
			border-bottom: 1px solid #c3c4c7;
			background: #f6f7f7;
		}
		.acui-health-rows {
			padding: 0;
			margin: 0;
		}
		.acui-health-row {
			display: flex;
			justify-content: space-between;
			align-items: flex-start;
			padding: 10px 16px;
			border-bottom: 1px solid #f0f0f1;
			font-size: 12px;
			gap: 8px;
		}
		.acui-health-row:last-child {
			border-bottom: 0;
		}
		.acui-health-label {
			color: #787c82;
			flex-shrink: 0;
		}
		.acui-health-value {
			text-align: right;
			font-weight: 500;
			word-break: break-word;
		}
		.acui-status-dot {
			display: inline-block;
			width: 8px;
			height: 8px;
			border-radius: 50%;
			margin-right: 4px;
			vertical-align: middle;
		}
		.acui-status-ok   { background: #00a32a; }
		.acui-status-warn { background: #dba617; }
		.acui-status-err  { background: #d63638; }
		@media (max-width: 960px) {
			.acui-cron-layout { flex-direction: column; }
			.acui-cron-sidebar { width: 100%; }
		}
		</style>

		<div class="acui-cron-layout">
		<div class="acui-cron-main">

		<?php if( !function_exists( 'acui_ic_check_active' ) ): ?>
		<div style="display:flex;align-items:center;justify-content:space-between;background:#fff;border-left:4px solid #2271b1;border-radius:3px;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:16px 20px;margin:16px 0 24px;gap:20px;">
			<div>
				<strong><?php _e( 'Need multiple import tasks with different schedules?', 'import-users-from-csv-with-meta' ); ?></strong>
				<p style="margin:4px 0 0;color:#50575e;"><?php _e( 'With the <strong>Recurring Import Addon</strong> you can create unlimited tasks, each with its own file, period, role and notification email.', 'import-users-from-csv-with-meta' ); ?></p>
			</div>
			<a href="https://import-wp.com/plugins/recurring-import-addon/" target="_blank" style="white-space:nowrap;background:#2271b1;color:#fff;border-radius:3px;padding:8px 18px;font-size:13px;font-weight:600;text-decoration:none;"><?php _e( 'Get the addon', 'import-users-from-csv-with-meta' ); ?></a>
		</div>
		<?php endif; ?>

		<form id="acui-cron-form" method="POST" enctype="multipart/form-data" action="" accept-charset="utf-8">
			<table class="form-table">
				<tbody>

				<tr class="form-field form-required">
					<th scope="row"><label for="cron-activated"><?php _e( 'Activate periodic import?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
                        <?php ACUIHTML()->checkbox( array( 'name' => 'cron-activated', 'compare_value' => $cron_activated ) ); ?>
					</td>
				</tr>

				<tr class="form-field form-required">
					<th scope="row"><label for="cron_user_id"><?php _e( 'User that runs the cron', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<?php
						$capable_users = get_users( array(
							'capability' => apply_filters( 'acui_capability', 'create_users' ),
							'fields'     => array( 'ID', 'user_login', 'display_name' ),
							'orderby'    => 'display_name',
						) );
						$cron_user_id_saved = absint( get_option( 'acui_cron_user_id' ) );
						?>
						<select name="cron_user_id" id="cron_user_id">
							<option value=""><?php _e( '— Select a user —', 'import-users-from-csv-with-meta' ); ?></option>
							<?php foreach ( $capable_users as $u ): ?>
								<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $cron_user_id_saved, $u->ID ); ?>>
									<?php echo esc_html( $u->display_name . ' (' . $u->user_login . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php _e( 'This user will be set as the active user during the cron execution. Only users with the required capability are listed.', 'import-users-from-csv-with-meta' ); ?></p>
					</td>
				</tr>

				<tr class="form-field">
					<th scope="row"><label for="path_to_file"><?php _e( "Path or URL of file that is going to be imported", 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
                        <?php ACUIHTML()->text( array( 'name' => 'path_to_file', 'value' => $path_to_file, 'class' => '', 'placeholder' => __( 'Insert complete path to the file', 'import-users-from-csv-with-meta' ) ) ); ?>
						<p class="description"><?php printf( __( 'You have to enter the URL or the path to the file, i.e.: %s or %s' ,'import-users-from-csv-with-meta' ), $sample_path, $sample_url ); ?></p>
					</td>
				</tr>

				<tr class="form-field form-required">
					<th scope="row"><label for="period"><?php _e( 'Period', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
                        <?php ACUIHTML()->select( array(
                            'options' => ACUI_Helper::get_loaded_periods(),
                            'name' => 'period',
                            'selected' => $period,
                            'show_option_all' => false,
                            'show_option_none' => false,
                        )); ?>
						<p class="description"><?php _e( 'How often should the event reoccur?', 'import-users-from-csv-with-meta' ); ?></p>
					</td>
				</tr>

				<tr class="form-field form-required">
					<th scope="row"><label for="send-mail-cron"><?php _e( 'Send email when using periodic import?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
                        <?php ACUIHTML()->checkbox( array( 'name' => 'send-mail-cron', 'compare_value' => $send_mail_cron ) ); ?>
					</td>
				</tr>

				<tr class="form-field form-required">
					<th scope="row"><label for="send-mail-updated"><?php _e( 'Send mail also to users that are being updated?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
                        <?php ACUIHTML()->checkbox( array( 'name' => 'send-mail-updated', 'compare_value' => $send_mail_updated ) ); ?>
					</td>
				</tr>

				<tr class="form-field form-required">
					<th scope="row"><label for="role"><?php _e( 'Role', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
                        <?php ACUIHTML()->select( array(
                            'options' => ACUI_Helper::get_editable_roles(),
                            'name' => 'role',
                            'selected' => $role,
                            'show_option_all' => false,
                            'show_option_none' => __( 'Disable role assignment in cron import', 'import-users-from-csv-with-meta' ),
                        )); ?>
						<p class="description"><?php _e( 'Which role will be used to import users?', 'import-users-from-csv-with-meta' ); ?></p>
					</td>
				</tr>

				<tr class="form-field form-required">
					<th scope="row"><label for="update-roles-existing-users"><?php _e( 'Update roles for existing users?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
                        <?php ACUIHTML()->checkbox( array( 'name' => 'update-roles-existing-users', 'compare_value' => $update_roles_existing_users ) ); ?>
					</td>
				</tr>

				<tr class="form-field form-required">
					<th scope="row"><label for="move-file-cron"><?php _e( 'Move file after import?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<div style="float:left;">
                            <?php ACUIHTML()->checkbox( array( 'name' => 'move-file-cron', 'compare_value' => $move_file_cron ) ); ?>
						</div>

						<div class="move-file-cron-cell" style="margin-left:25px;">
                            <?php ACUIHTML()->text( array( 'name' => 'path_to_move', 'value' => $path_to_move, 'class' => '', 'placeholder' => __( 'Insert complete path to the file', 'import-users-from-csv-with-meta' ) ) ); ?>
							<p class="description"><?php _e( 'You have to enter the path to file, i.e.:', 'import-users-from-csv-with-meta'); ?> <?php $upload_dir = wp_upload_dir(); echo $upload_dir["path"]; ?>/move.csv</p>
						</div>
					</td>
				</tr>

				<tr class="form-field form-required move-file-cron-cell">
					<th scope="row"><label for="move-file-cron"><?php _e( 'Auto rename after move?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<div style="float:left;">
                            <?php ACUIHTML()->checkbox( array( 'name' => 'path_to_move_auto_rename', 'compare_value' => $path_to_move_auto_rename ) ); ?>
						</div>

						<div style="margin-left:25px;">
							<p class="description"><?php _e( 'Your file will be renamed after moved, so you will not lost any version of it. The way to rename will be append the time stamp using this date format: YmdHis.', 'import-users-from-csv-with-meta'); ?></p>
						</div>
					</td>
				</tr>

				</tbody>
			</table>

			<h2><?php _e( 'Users not present in CSV file', 'import-users-from-csv-with-meta'); ?></h2>

			<table class="form-table">
				<tbody>

				<tr class="form-field form-required">
					<th scope="row"><label for="cron-delete-users"><?php _e( 'Delete users that are not present in the CSV?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<div style="float:left; margin-top: 10px;">
                            <?php ACUIHTML()->checkbox( array( 'name' => 'cron-delete-users', 'compare_value' => $cron_delete_users ) ); ?>
						</div>
						<div style="margin-left:25px;">
                            <?php ACUIHTML()->select( array(
                                'options' => ACUI_Helper::get_list_users_with_display_name(),
                                'name' => 'cron-delete-users-assign-posts',
                                'selected' => $cron_delete_users_assign_posts,
                                'show_option_all' => false,
                                'show_option_none' => __( 'Delete posts of deleted users without assigning to any user', 'import-users-from-csv-with-meta' ),
                            )); ?>
							</select>
							<p class="description"><?php _e( 'Administrators will not be deleted anyway. After deleting users, you can choose if you want to assign their posts to another user. If you do not choose a user, their content will be deleted.', 'import-users-from-csv-with-meta' ); ?></p>
						</div>
					</td>
				</tr>

				<tr class="form-field form-required">
					<th scope="row"><label for="cron-change-role-not-present"><?php _e( 'Change role of users that are not present in the CSV?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<div style="float:left; margin-top: 10px;">
                            <?php ACUIHTML()->checkbox( array( 'name' => 'cron-change-role-not-present', 'compare_value' => $cron_change_role_not_present ) ); ?>
						</div>
						<div style="margin-left:25px;">
                            <?php ACUIHTML()->select( array(
                                'options' => ACUI_Helper::get_editable_roles(),
                                'name' => 'cron-change-role-not-present-role',
                                'selected' => $cron_change_role_not_present_role,
                                'show_option_all' => false,
                                'show_option_none' => false,
                            )); ?>
							<p class="description"><?php _e( 'After importing users from a CSV, users not present in the CSV can have their roles changed to a different role.', 'import-users-from-csv-with-meta' ); ?></p>
						</div>
					</td>
				</tr>
				</tbody>
			</table>

			<h2><?php _e( 'Call cron process using REST-API', 'import-users-from-csv-with-meta'); ?></h2>

			<table class="form-table">
				<tbody>
				<tr class="form-field form-required">
					<th scope="row"><label for="log"><?php _e( 'GET endpoint to execute cron', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<?php _e( 'You can execute the cron process outside of your site using the next REST-API endpoint:', 'import-users-from-csv-with-meta' ); ?> <a href="<?php echo $rest_api_execute_cron_url; ?>"><?php echo $rest_api_execute_cron_url; ?></a>.<br/>
						<p class="description"><?php _e( 'This endpoint does an administrative task, so in order to run it you must be authenticated as a user with privileges.', 'import-users-from-csv-with-meta' ); ?></p>
					</td>
				</tr>
				</tbody>
			</table>

			<?php do_action( 'acui_tab_cron_before_log' ); ?>

			<h2><?php _e( 'Log', 'import-users-from-csv-with-meta'); ?></h2>

			<table class="form-table">
				<tbody>
				<tr class="form-field form-required log">
					<th scope="row"><label for="log"><?php _e( 'Last actions of schedule task', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<?php echo ACUIHelper()->remove_specific_html_tags( $log, array( 'script', 'style' ) ); ?>
					</td>
				</tr>

				</tbody>
			</table>

			<?php wp_nonce_field( 'codection-security', 'security' ); ?>
		</form>

		<script>
		jQuery( document ).ready( function( $ ){
			check_delete_users_checked();

			$( '#cron-delete-users' ).on( 'click', function() {
				check_delete_users_checked();
			});

			$( '#cron-execute-cron-task-now' ).click( function(){
				$( this )
					.prop( 'disabled', true )
					.val( 'Loading...' );

				var data = {
					'action': 'acui_fire_cron',
					'security': '<?php echo wp_create_nonce( "codection-security" ); ?>'
				};

				$.post( ajaxurl, data, function( response ) {
					if( response != "OK" )
						alert( "<?php _e( 'Problems executing cron task: ', 'import-users-from-csv-with-meta' ); ?>" + response );
					else{
						alert( "<?php _e( 'Cron task successfully executed', 'import-users-from-csv-with-meta' ); ?>" );
						document.location.reload();
					}
				});
			} );

			function check_delete_users_checked(){
				if( $('#cron-delete-users').is(':checked') ){
                    $( '#cron-delete-users-assign-posts' ).prop( 'disabled', false );
					$( '#cron-change-role-not-present-role' ).prop( 'disabled', true );
					$( '#cron-change-role-not-present' ).prop( 'disabled', true );
				} else {
                    $( '#cron-delete-users-assign-posts' ).prop( 'disabled', true );
					$( '#cron-change-role-not-present-role' ).prop( 'disabled', false );
					$( '#cron-change-role-not-present' ).prop( 'disabled', false );
				}
			}

			$( "[name='cron-delete-users']" ).change(function() {
		        if( $ (this ).is( ":checked" ) ) {
		            var returnVal = confirm("<?php _e( 'Are you sure you want to delete all users that are not present in the CSV? This action cannot be undone.', 'import-users-from-csv-with-meta' ); ?>");
		            $( this ).prop( "checked", returnVal );
		        }
		    });

		    $( "[name='move-file-cron']" ).change(function() {
		        if( $(this).is( ":checked" ) ){
		        	$( '.move-file-cron-cell' ).show();
		        }
		        else{
		        	$( '.move-file-cron-cell' ).hide();
		        }
		    });

		    <?php if( !$move_file_cron ): ?>
		    $( '.move-file-cron-cell' ).hide();
		    <?php endif; ?>

			$( '#acui-health-run-now' ).on( 'click', function() {
				var $btn = $( this );
				$btn.prop( 'disabled', true ).text( '<?php esc_attr_e( 'Running...', 'import-users-from-csv-with-meta' ); ?>' );

				$.post( ajaxurl, {
					action:   'acui_fire_cron_no_session',
					security: '<?php echo wp_create_nonce( "codection-security" ); ?>'
				}, function( response ) {
					if ( response !== 'OK' ) {
						alert( '<?php esc_attr_e( 'Problems executing cron task: ', 'import-users-from-csv-with-meta' ); ?>' + response );
						$btn.prop( 'disabled', false ).text( '<?php esc_attr_e( 'Run now', 'import-users-from-csv-with-meta' ); ?>' );
					} else {
						alert( '<?php esc_attr_e( 'Cron task successfully executed', 'import-users-from-csv-with-meta' ); ?>' );
						document.location.reload();
					}
				} );
			} );
		});
		</script>

		</div><!-- .acui-cron-main -->

		<div class="acui-cron-sidebar">
			<div class="acui-health-card" style="margin-bottom:16px;">
				<h3><?php _e( 'Actions', 'import-users-from-csv-with-meta' ); ?></h3>
				<div class="acui-health-rows">
					<div class="acui-health-row" style="flex-direction:column;gap:8px;padding:12px 16px;">
						<button type="submit" form="acui-cron-form" class="button button-primary" style="width:100%;">
							<?php _e( 'Save Settings', 'import-users-from-csv-with-meta' ); ?>
						</button>
						<button id="acui-health-run-now" class="button button-secondary" style="width:100%;">
							<?php _e( 'Execute cron task now', 'import-users-from-csv-with-meta' ); ?>
						</button>
					</div>
				</div>
			</div>

			<div class="acui-health-card">
				<h3><?php _e( 'Cron Health Status', 'import-users-from-csv-with-meta' ); ?></h3>
				<div class="acui-health-rows">

					<div class="acui-health-row">
						<span class="acui-health-label"><?php _e( 'Activated', 'import-users-from-csv-with-meta' ); ?></span>
						<span class="acui-health-value">
							<?php if ( $health['is_activated'] ): ?>
								<span class="acui-status-dot acui-status-ok"></span><?php _e( 'Yes', 'import-users-from-csv-with-meta' ); ?>
							<?php else: ?>
								<span class="acui-status-dot acui-status-err"></span><?php _e( 'No', 'import-users-from-csv-with-meta' ); ?>
							<?php endif; ?>
						</span>
					</div>

					<div class="acui-health-row">
						<span class="acui-health-label"><?php _e( 'Scheduled in Action Scheduler', 'import-users-from-csv-with-meta' ); ?></span>
						<span class="acui-health-value">
							<?php if ( $health['is_registered'] ): ?>
								<span class="acui-status-dot acui-status-ok"></span><?php _e( 'Yes', 'import-users-from-csv-with-meta' ); ?>
							<?php elseif ( $health['is_activated'] ): ?>
								<span class="acui-status-dot acui-status-err"></span><?php _e( 'No', 'import-users-from-csv-with-meta' ); ?>
							<?php else: ?>
								<span class="acui-status-dot acui-status-warn"></span><?php _e( 'Not active', 'import-users-from-csv-with-meta' ); ?>
							<?php endif; ?>
						</span>
					</div>

					<div class="acui-health-row">
						<span class="acui-health-label"><?php _e( 'Running now', 'import-users-from-csv-with-meta' ); ?></span>
						<span class="acui-health-value">
							<?php if ( $health['is_running'] ): ?>
								<span class="acui-status-dot acui-status-warn"></span><?php _e( 'Yes — import in progress', 'import-users-from-csv-with-meta' ); ?>
							<?php else: ?>
								<span class="acui-status-dot acui-status-ok"></span><?php _e( 'No', 'import-users-from-csv-with-meta' ); ?>
							<?php endif; ?>
						</span>
					</div>

					<?php if ( $health['is_registered'] && $health['next_run_date'] ): ?>
					<div class="acui-health-row">
						<span class="acui-health-label"><?php _e( 'Next run', 'import-users-from-csv-with-meta' ); ?></span>
						<span class="acui-health-value">
							<?php echo esc_html( $health['next_run_date'] ); ?>
							<?php if ( $health['next_run_human'] ): ?>
								<br><span style="color:#787c82;font-weight:400;"><?php printf( __( 'in %s', 'import-users-from-csv-with-meta' ), esc_html( $health['next_run_human'] ) ); ?></span>
							<?php endif; ?>
						</span>
					</div>
					<?php endif; ?>

					<div class="acui-health-row">
						<span class="acui-health-label"><?php _e( 'Period', 'import-users-from-csv-with-meta' ); ?></span>
						<span class="acui-health-value">
							<?php echo esc_html( $health['period_label'] ); ?>
							<?php if ( $health['period_human'] ): ?>
								<br><span style="color:#787c82;font-weight:400;"><?php echo esc_html( $health['period_human'] ); ?></span>
							<?php endif; ?>
						</span>
					</div>

					<?php if ( $health['last_run'] ): ?>
					<div class="acui-health-row">
						<span class="acui-health-label"><?php _e( 'Last execution', 'import-users-from-csv-with-meta' ); ?></span>
						<span class="acui-health-value">
							<?php echo esc_html( $health['last_run']['date'] ); ?>
							<?php if ( intval( $health['last_run']['errors'] ) > 0 ): ?>
								<br><span style="color:#d63638;font-weight:400;">
									<?php printf( _n( '%d error', '%d errors', intval( $health['last_run']['errors'] ), 'import-users-from-csv-with-meta' ), intval( $health['last_run']['errors'] ) ); ?>
								</span>
							<?php else: ?>
								<br><span style="color:#00a32a;font-weight:400;"><?php _e( 'No errors', 'import-users-from-csv-with-meta' ); ?></span>
							<?php endif; ?>
						</span>
					</div>
					<?php else: ?>
					<div class="acui-health-row">
						<span class="acui-health-label"><?php _e( 'Last execution', 'import-users-from-csv-with-meta' ); ?></span>
						<span class="acui-health-value" style="color:#787c82;"><?php _e( 'Never', 'import-users-from-csv-with-meta' ); ?></span>
					</div>
					<?php endif; ?>

					<div class="acui-health-row">
						<span class="acui-health-label"><?php _e( 'Runs as user', 'import-users-from-csv-with-meta' ); ?></span>
						<span class="acui-health-value">
							<?php if ( $health['cron_user_label'] ): ?>
								<?php echo esc_html( $health['cron_user_label'] ); ?>
							<?php else: ?>
								<span style="color:#787c82;"><?php _e( 'Not set', 'import-users-from-csv-with-meta' ); ?></span>
							<?php endif; ?>
						</span>
					</div>

					<?php if ( $health['is_activated'] && !$health['is_registered'] ): ?>
					<div class="acui-health-row" style="background:#fff8e5;">
						<span style="color:#996800;font-size:12px;">
							<?php _e( 'The cron is marked as active but is not found in Action Scheduler. Try saving the settings again.', 'import-users-from-csv-with-meta' ); ?>
						</span>
					</div>
					<?php endif; ?>

				</div>
			</div>
		</div><!-- .acui-cron-sidebar -->

		</div><!-- .acui-cron-layout -->
	<?php
	}

	static function admin_gui_log(){
		$log = get_option( 'acui_cron_execution_log', array() );
		if( !is_array( $log ) ) $log = array();

		if( isset( $_GET['acui_log_cleared'] ) ): ?>
		<div class="updated"><p><?php _e( 'Execution log cleared.', 'import-users-from-csv-with-meta' ); ?></p></div>
		<?php endif; ?>

		<div style="margin-top:20px;">

		<?php if( empty( $log ) ): ?>
			<p><?php _e( 'No recurring executions recorded yet.', 'import-users-from-csv-with-meta' ); ?></p>
		<?php else: ?>

		<table class="widefat striped" style="margin-bottom:20px;">
			<thead>
				<tr>
					<th><?php _e( 'Date', 'import-users-from-csv-with-meta' ); ?></th>
					<th><?php _e( 'File', 'import-users-from-csv-with-meta' ); ?></th>
					<th style="text-align:center;"><?php _e( 'Created', 'import-users-from-csv-with-meta' ); ?></th>
					<th style="text-align:center;"><?php _e( 'Updated', 'import-users-from-csv-with-meta' ); ?></th>
					<th style="text-align:center;"><?php _e( 'Deleted', 'import-users-from-csv-with-meta' ); ?></th>
					<th style="text-align:center;"><?php _e( 'Ignored', 'import-users-from-csv-with-meta' ); ?></th>
					<th style="text-align:center;"><?php _e( 'Errors', 'import-users-from-csv-with-meta' ); ?></th>
					<th style="text-align:center;"><?php _e( 'Steps', 'import-users-from-csv-with-meta' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach( $log as $entry ): ?>
				<tr>
					<td><?php echo esc_html( $entry['date'] ); ?></td>
					<td><?php echo esc_html( $entry['file'] ); ?></td>
					<td style="text-align:center;"><?php echo intval( $entry['created'] ); ?></td>
					<td style="text-align:center;"><?php echo intval( $entry['updated'] ); ?></td>
					<td style="text-align:center;"><?php echo intval( $entry['deleted'] ); ?></td>
					<td style="text-align:center;"><?php echo intval( $entry['ignored'] ); ?></td>
					<td style="text-align:center;<?php echo intval( $entry['errors'] ) > 0 ? 'color:#d63638;font-weight:bold;' : ''; ?>">
						<?php echo intval( $entry['errors'] ); ?>
					</td>
					<td style="text-align:center;"><?php echo intval( $entry['steps'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php endif; ?>

		<form method="POST" action="">
			<?php wp_nonce_field( 'codection-security', 'security' ); ?>
			<input type="hidden" name="acui_clear_execution_log" value="1" />
			<button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to clear the execution log?', 'import-users-from-csv-with-meta' ); ?>');">
				<?php _e( 'Clear log', 'import-users-from-csv-with-meta' ); ?>
			</button>
		</form>

		</div>
	<?php
	}

	function ajax_fire_cron(){
		check_ajax_referer( 'codection-security', 'security' );

		do_action( 'acui_cron_process' );
		echo "OK";
		wp_die();
	}

	function ajax_fire_cron_no_session(){
		check_ajax_referer( 'codection-security', 'security' );

		wp_set_current_user( 0 );
		do_action( 'acui_cron_process' );
		echo "OK";
		wp_die();
	}
}

new ACUI_Cron();
