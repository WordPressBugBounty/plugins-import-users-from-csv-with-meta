<?php
if ( ! defined( 'ABSPATH' ) ) 
    exit;

class ACUI_Homepage{
	function __construct(){
	}

    function hooks(){
        add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ), 10, 1 );
		add_action( 'wp_ajax_acui_delete_users_assign_posts_data', array( $this, 'delete_users_assign_posts_data' ) );
    }

    function load_scripts( $hook ){
        if( $hook != 'tools_page_acui' )
            return;

        wp_enqueue_style( 'select2-css', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/select2.min.css' );
        wp_enqueue_script( 'select2-js', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/select2.min.js' );
    }

	static function admin_gui(){
		$settings = new ACUI_Settings( 'import_backend' );
		$settings->maybe_migrate_old_options( 'import_backend' );
		$upload_dir = wp_upload_dir();
		$sample_path = $upload_dir["path"] . '/test.csv';
		$sample_url = plugin_dir_url( dirname( __FILE__ ) ) . 'test.csv';

		if( ctype_digit( $settings->get( 'delete_users_assign_posts' ) ) ){
			$delete_users_assign_posts_user_id = $settings->get( 'delete_users_assign_posts' );
			$delete_users_assign_posts_user = get_user_by( 'id', $delete_users_assign_posts_user_id );

			if( $delete_users_assign_posts_user ) {
				$delete_users_assign_posts_options = array( $delete_users_assign_posts_user_id => $delete_users_assign_posts_user->display_name);
			} else {
				$delete_users_assign_posts_options = array();
			}

			$delete_users_assign_posts_option_selected = $delete_users_assign_posts_user_id;
		}
		else{
			$delete_users_assign_posts_options = array( 0 => __( 'No user selected', 'import-users-from-csv-with-meta' ) );
			$delete_users_assign_posts_option_selected = 0;
		}

		$wizard = ACUI_Wizard::is_active();
?>
		<?php ob_start(); ?>
		<?php if( $wizard ):
			$acui_default_role = get_option( 'default_role', 'subscriber' );
			$acui_role_names = ACUI_Helper::get_editable_roles();
			$acui_saved_roles = array_values( array_filter( (array) $settings->get( 'role' ) ) );
			$acui_role_custom = !empty( $acui_saved_roles ) && $acui_saved_roles !== array( $acui_default_role );
		?>
					<tr id="acui_wizard_role_row" class="form-field">
						<th scope="row"><?php _e( 'Role of new users', 'import-users-from-csv-with-meta' ); ?></th>
						<td>
							<p class="acui-role-from-column" hidden><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php printf( __( 'Each user gets the role written in the column %s of your file. Rows with an empty role are skipped: write a role in them, or no_role if they must not have any.', 'import-users-from-csv-with-meta' ), '<strong class="acui-role-column-name"></strong>' ); ?></p>
							<div class="acui-role-choice">
								<fieldset class="acui-file-source acui-role-source">
									<legend class="screen-reader-text"><?php _e( 'Role of new users', 'import-users-from-csv-with-meta' ); ?></legend>
									<label class="acui-file-source__option">
										<input type="radio" name="acui_role_mode" value="default" <?php checked( !$acui_role_custom ); ?>>
										<span class="acui-file-source__title"><?php printf( __( 'Default role of this site: %s', 'import-users-from-csv-with-meta' ), esc_html( isset( $acui_role_names[ $acui_default_role ] ) ? $acui_role_names[ $acui_default_role ] : $acui_default_role ) ); ?></span>
										<span class="acui-file-source__hint"><?php printf( __( 'The one set in <a href="%s">Settings &gt; General</a>', 'import-users-from-csv-with-meta' ), esc_url( admin_url( 'options-general.php' ) ) ); ?></span>
									</label>
									<label class="acui-file-source__option">
										<input type="radio" name="acui_role_mode" value="custom" <?php checked( $acui_role_custom ); ?>>
										<span class="acui-file-source__title"><?php _e( 'Choose one or more roles', 'import-users-from-csv-with-meta' ); ?></span>
										<span class="acui-file-source__hint"><?php _e( 'For example, Customer and Subscriber at the same time', 'import-users-from-csv-with-meta' ); ?></span>
									</label>
								</fieldset>
								<div class="acui-role-custom"<?php echo $acui_role_custom ? '' : ' style="display:none;"'; ?>>
									<?php ACUIHTML()->select( array(
										'options' => $acui_role_names,
										'name' => 'role[]',
										'show_option_all' => false,
										'show_option_none' => false,
										'multiple' => true,
										'placeholder' => __( 'Click to choose roles', 'import-users-from-csv-with-meta' ),
										'selected' => $acui_role_custom ? $acui_saved_roles : array( $acui_default_role ),
										'style' => 'width:100%;'
									)); ?>
								</div>
							</div>
						</td>
					</tr>
		<?php else: ?>
					<tr class="form-field">
						<th scope="row"><label for="role"><?php _e( 'Default role', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
						<?php ACUIHTML()->select( array(
                            'options' => ACUI_Helper::get_editable_roles(),
                            'name' => 'role[]',
                            'show_option_all' => false,
                            'show_option_none' => true,
							'multiple' => true,
							'selected' => is_array( $settings->get( 'role' ) ) ? $settings->get( 'role' ) : array( $settings->get( 'role' ) ),
							'style' => 'width:100%;'
                        )); ?>
						<p class="description"><?php _e( 'You can also import roles from a CSV column. Please read the Documentation tab to see how this can be done. WordPress core allows assigning multiple roles to a user; however, the default interface only displays one role, although some plugins solve this limitation.', 'import-users-from-csv-with-meta' ); ?></p>

						</td>
					</tr>
		<?php endif; ?>
		<?php $acui_row_role = ob_get_clean(); ?>
		<?php ob_start(); ?>
					<tr id="acui_update_existing_users_wrapper" class="form-field form-required">
						<th scope="row"><label for="update_existing_users"><?php echo $wizard ? __( 'Users who already exist', 'import-users-from-csv-with-meta' ) : __( 'Update existing users?', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
							<?php ACUIHTML()->select( array(
								'options' => $wizard ? array( 'no' => __( 'Skip them, only create new users', 'import-users-from-csv-with-meta' ), 'yes' => __( 'Update them with the data in the file', 'import-users-from-csv-with-meta' ) ) : array( 'no' => __( 'No', 'import-users-from-csv-with-meta' ), 'yes' => __( 'Yes', 'import-users-from-csv-with-meta' ), ),
								'name' => 'update_existing_users',
								'show_option_all' => false,
								'show_option_none' => false,
								'selected' => $settings->get( 'update_existing_users' ),
							)); ?>
							<?php if( $wizard ): ?>
							<p class="description"><?php _e( 'Users are matched by ID, username or email. Passwords, roles and emails of existing users only change if you allow it in More options.', 'import-users-from-csv-with-meta' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
		<?php $acui_row_existing = ob_get_clean(); ?>
		<?php ob_start(); ?>
					<tr id="acui_send_email_wrapper" class="form-field">
						<th scope="row"><label for="user_login"><?php _e( 'Send email', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
							<p id="sends_email_wrapper">
								<?php ACUIHTML()->checkbox( array( 'name' => 'sends_email', 'label' => sprintf( __( 'Do you wish to send an email from this plugin with credentials and other data? <a href="%s">(email template found here)</a>', 'import-users-from-csv-with-meta' ), admin_url( 'tools.php?page=acui&tab=mail-options' ) ), 'current' => 'yes', 'compare_value' => $settings->get( 'sends_email' ) ) ); ?>
							</p>
							<p id="send_email_updated_wrapper">
								<?php ACUIHTML()->checkbox( array( 'name' => 'send_email_updated', 'label' => __( 'Do you wish to send this mail also to users that are being updated? (not just to the one which are being created)', 'import-users-from-csv-with-meta' ), 'current' => 'yes', 'compare_value' => $settings->get( 'send_email_updated' ) ) ); ?>
							</p>
						</td>
					</tr>
		<?php $acui_row_email = ob_get_clean(); ?>
		<?php ob_start(); ?>
					<tr id="acui_empty_cell_wrapper" class="form-field form-required">
						<th scope="row"><label for="empty_cell_action"><?php _e( 'What should the plugin do with empty cells?', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
							<?php ACUIHTML()->select( array(
								'options' => array( 'leave' => __( 'Leave the old value for this metadata', 'import-users-from-csv-with-meta' ), 'delete' => __( 'Delete the metadata', 'import-users-from-csv-with-meta' ) ),
								'name' => 'empty_cell_action',
								'show_option_all' => false,
								'show_option_none' => false,
								'selected' => $settings->get( 'empty_cell_action' ),
							)); ?>
						</td>
					</tr>
		<?php $acui_row_empty = ob_get_clean(); ?>
	<div class="wrap acui<?php echo $wizard ? ' acui-wizard' : ''; ?>" data-acui-step="1">	
		<style>
		#acui_import_results{
			display: none;
			margin-top: 20px;
			margin-bottom: 20px;
		}

		.user-importer-progress-wrapper{
			padding: 20px;
			background-color: white;
			width: 100%;
			margin: 0 0 20px 0;
			text-align: center;
			border-radius: 9px;
			box-shadow: 0 4px 6px rgba(0,0,0,0.1);
			box-sizing: border-box;
			display: none;
		}

		.user-importer-progress{
			width: 100%;
			height: 42px;
			border: 0;
			border-radius: 9px;
		}

		.user-importer-progress::-webkit-progress-bar {
			background-color: #f3f3f3;
			border-radius: 9px;
		}

		.user-importer-progress::-webkit-progress-value {
			background: #2271b1;
			border-radius: 9px;
		}

		.user-importer-progress::-moz-progress-bar {
			background: #2271b1;
			border-radius: 9px;
		}

		.acui-importing .acui-import-options,
		.acui-importing .sidebar,
		.acui-importing .header,
		.acui-importing .acui-message{
			display: none !important;
		}

		.acui-importing .user-importer-progress-wrapper{
			display: block !important;
		}

		.acui-path-check-result{
			font-weight: 600;
		}

		.acui-path-check-result.acui-path-ok{
			color: #007017;
		}

		.acui-path-check-result.acui-path-bad{
			color: #d63638;
		}
		</style>
		<?php do_action( 'acui_homepage_start' ); ?>

		<p class="acui-mode-switch">
			<?php if( $wizard ): ?>
			<a href="<?php echo esc_url( ACUI_Wizard::switch_mode_url( 'classic' ) ); ?>"><?php _e( 'Switch to classic mode', 'import-users-from-csv-with-meta' ); ?></a>
			<?php else: ?>
			<a href="<?php echo esc_url( ACUI_Wizard::switch_mode_url( 'wizard' ) ); ?>"><?php _e( 'Try the new import assistant: match your columns, whatever their names', 'import-users-from-csv-with-meta' ); ?></a>
			<?php endif; ?>
		</p>

		<div class="row">
			<div class="main_bar">
				<form method="POST" id="acui_form" enctype="multipart/form-data" action="" accept-charset="utf-8">

				<div class="user-importer-progress-wrapper">
					<progress class="user-importer-progress" value="0" max="100"></progress>
					<div style="margin-top: 10px; font-weight: bold;">
						<span class="user-importer-progress-value">0%</span>
					</div>
					<div class="user-importer-controls" style="margin-top: 20px;">
						<button type="button" id="acui_pause_btn" class="button button-secondary"><?php _e( 'Pause', 'import-users-from-csv-with-meta' ); ?></button>
						<button type="button" id="acui_stop_btn" class="button button-link-delete" style="color: #d63638;"><?php _e( 'Stop', 'import-users-from-csv-with-meta' ); ?></button>
					</div>
				</div>

				<div class="acui-import-options">
				<?php if( $wizard ): ?>
				<ol class="acui-wizard-steps">
					<li data-step="1"><span class="acui-wizard-step-number">1</span><?php _e( 'File', 'import-users-from-csv-with-meta' ); ?></li>
					<li data-step="2"><span class="acui-wizard-step-number">2</span><?php _e( 'Columns', 'import-users-from-csv-with-meta' ); ?></li>
					<li data-step="3"><span class="acui-wizard-step-number">3</span><?php _e( 'Options', 'import-users-from-csv-with-meta' ); ?></li>
					<li data-step="4"><span class="acui-wizard-step-number">4</span><?php _e( 'Preview', 'import-users-from-csv-with-meta' ); ?></li>
					<li data-step="5"><span class="acui-wizard-step-number">5</span><?php _e( 'Import', 'import-users-from-csv-with-meta' ); ?></li>
				</ol>

				<div id="acui_wizard_columns" class="acui-wizard-panel">
					<h2><?php _e( 'Match your columns', 'import-users-from-csv-with-meta' ); ?></h2>
					<p class="description"><?php _e( 'We matched what we could recognise. Check the suggestions and choose what to do with the rest.', 'import-users-from-csv-with-meta' ); ?></p>
					<p class="acui-wizard-file-facts"></p>
					<div class="acui-wizard-table-wrapper">
						<table class="widefat acui-wizard-map">
							<thead>
								<tr>
									<th><?php _e( 'Column in your file', 'import-users-from-csv-with-meta' ); ?></th>
									<th><?php _e( 'First values', 'import-users-from-csv-with-meta' ); ?></th>
									<th><?php _e( 'Import as', 'import-users-from-csv-with-meta' ); ?></th>
									<th><?php _e( 'Status', 'import-users-from-csv-with-meta' ); ?></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
					<div class="acui-wizard-fixed">
						<ul class="acui-wizard-fixed-list"></ul>
						<button type="button" class="button acui-wizard-fixed-add"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php _e( 'Add a field with the same value for every user', 'import-users-from-csv-with-meta' ); ?></button>
					</div>
					<fieldset class="acui-wizard-username" hidden>
						<legend><?php _e( 'Your file has no username column. How should usernames be created?', 'import-users-from-csv-with-meta' ); ?></legend>
						<label><input type="radio" name="acui_username_mode" value="email" checked> <?php _e( 'Use the email as username', 'import-users-from-csv-with-meta' ); ?></label>
						<label><input type="radio" name="acui_username_mode" value="random"> <?php _e( 'Generate a random username, like user_123456', 'import-users-from-csv-with-meta' ); ?></label>
					</fieldset>
					<div class="acui-wizard-check" role="status"></div>
					<input type="hidden" name="acui_wizard_token" id="acui_wizard_token" value="">
				</div>

				<div id="acui_wizard_preview" class="acui-wizard-panel">
					<h2><?php _e( 'Preview', 'import-users-from-csv-with-meta' ); ?></h2>
					<p class="description"><?php _e( 'This is what will happen. Nothing has been saved yet.', 'import-users-from-csv-with-meta' ); ?></p>
					<div class="acui-log-cards acui-wizard-counts">
						<div class="acui-log-card acui-log-card--created">
							<span class="acui-log-card__number" id="acui_preview_created">0</span>
							<span class="acui-log-card__label"><?php _e( 'Will be created', 'import-users-from-csv-with-meta' ); ?></span>
						</div>
						<div class="acui-log-card acui-log-card--updated">
							<span class="acui-log-card__number" id="acui_preview_updated">0</span>
							<span class="acui-log-card__label"><?php _e( 'Will be updated', 'import-users-from-csv-with-meta' ); ?></span>
						</div>
						<div class="acui-log-card acui-log-card--processed">
							<span class="acui-log-card__number" id="acui_preview_ignored">0</span>
							<span class="acui-log-card__label"><?php _e( 'Will be skipped', 'import-users-from-csv-with-meta' ); ?></span>
						</div>
						<div class="acui-log-card acui-log-card--no-errors" id="acui_preview_errors_card">
							<span class="acui-log-card__number" id="acui_preview_errors">0</span>
							<span class="acui-log-card__label"><?php _e( 'Have errors', 'import-users-from-csv-with-meta' ); ?></span>
						</div>
					</div>
					<div class="acui-wizard-not-present" hidden></div>
					<h3><?php _e( 'First rows', 'import-users-from-csv-with-meta' ); ?></h3>
					<div class="acui-wizard-table-wrapper">
						<table class="widefat acui-wizard-sample">
							<thead>
								<tr>
									<th><?php _e( 'Row', 'import-users-from-csv-with-meta' ); ?></th>
									<th><?php _e( 'Email', 'import-users-from-csv-with-meta' ); ?></th>
									<th><?php _e( 'Username', 'import-users-from-csv-with-meta' ); ?></th>
									<th><?php _e( 'Role', 'import-users-from-csv-with-meta' ); ?></th>
									<th><?php _e( 'Result', 'import-users-from-csv-with-meta' ); ?></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
					<div class="acui-wizard-issues" hidden>
						<h3></h3>
						<ul></ul>
						<p class="acui-wizard-issues-more"></p>
						<p><a href="#" class="button acui-wizard-errors-download"><?php _e( 'Download the rows with errors (CSV)', 'import-users-from-csv-with-meta' ); ?></a></p>
					</div>
					<p class="description"><?php _e( 'The preview follows the same rules as the import. Add-ons that change data while importing may change some results.', 'import-users-from-csv-with-meta' ); ?></p>
				</div>
				<?php endif; ?>

				<div class="acui-accordion">

				<details id="acui_file_header" open>
				<summary><span class="acui-summary-label"><?php _e( 'File', 'import-users-from-csv-with-meta'); ?></span><span class="acui-summary-chevron">▼</span></summary>
				<table id="acui_file_wrapper" class="form-table">
					<tbody>

					<?php do_action( 'acui_homepage_before_file_rows' ); ?>

					<?php
					$acui_extensions = array_unique( array_map( 'strtolower', (array) apply_filters( 'acui_allowed_upload_extensions', array( 'csv' ) ) ) );
					?>
					<tr class="form-field form-required">
						<th scope="row"><?php _e( 'File', 'import-users-from-csv-with-meta' ); ?> <span class="description"><?php _e( '(required)', 'import-users-from-csv-with-meta' ); ?></span></th>
						<td>
							<fieldset class="acui-file-source">
								<legend class="screen-reader-text"><?php _e( 'Where is your file?', 'import-users-from-csv-with-meta' ); ?></legend>
								<label class="acui-file-source__option">
									<input type="radio" name="acui_file_source" value="upload" checked>
									<span class="acui-file-source__title"><?php _e( 'Upload from your computer', 'import-users-from-csv-with-meta' ); ?></span>
									<span class="acui-file-source__hint"><?php _e( 'Choose or drag a file', 'import-users-from-csv-with-meta' ); ?></span>
								</label>
								<label class="acui-file-source__option">
									<input type="radio" name="acui_file_source" value="path">
									<span class="acui-file-source__title"><?php _e( 'Use a URL or a file on this server', 'import-users-from-csv-with-meta' ); ?></span>
									<span class="acui-file-source__hint"><?php _e( 'Google Sheets, a link to a CSV or a path in uploads', 'import-users-from-csv-with-meta' ); ?></span>
								</label>
							</fieldset>

							<div id="upload_file">
								<div class="acui-dropzone">
									<input type="file" name="uploadfile" id="uploadfile" class="uploadfile acui-dropzone__input" accept="<?php echo esc_attr( '.' . implode( ',.', $acui_extensions ) ); ?>" />
									<label for="uploadfile" class="acui-dropzone__label">
										<span class="dashicons dashicons-upload" aria-hidden="true"></span>
										<span class="acui-dropzone__text"><strong><?php _e( 'Drag your file here', 'import-users-from-csv-with-meta' ); ?></strong> <?php _e( 'or click to choose it', 'import-users-from-csv-with-meta' ); ?></span>
										<span class="acui-dropzone__file" hidden></span>
										<span class="acui-dropzone__formats"><?php printf( __( 'Accepted formats: %s', 'import-users-from-csv-with-meta' ), esc_html( strtoupper( implode( ', ', $acui_extensions ) ) ) ); ?></span>
									</label>
								</div>
							</div>

							<div id="introduce_path" style="display:none;">
								<div class="acui-path-field">
									<input type="text" name="path_to_file" id="path_to_file" value="<?php echo esc_attr( preg_match( '#^https?://[^/]*%#i', (string) $settings->get( 'path_to_file' ) ) ? '' : $settings->get( 'path_to_file' ) ); ?>" placeholder="https://example.com/users.csv" aria-describedby="acui_path_help" />
									<button type="button" class="button acui-check-path-btn" data-target="path_to_file"><?php _e( 'Check', 'import-users-from-csv-with-meta' ); ?></button>
								</div>
								<p class="acui-path-check-result" data-target="path_to_file" style="display:none;"></p>
								<ul id="acui_path_help" class="acui-path-help">
									<li><?php _e( '<strong>A URL</strong> to a CSV file, for example <code>https://example.com/users.csv</code>', 'import-users-from-csv-with-meta' ); ?></li>
									<li><?php _e( '<strong>Google Sheets</strong>: in File &gt; Share &gt; Publish to the web, choose "Comma-separated values (.csv)" and paste the link', 'import-users-from-csv-with-meta' ); ?></li>
									<li><?php printf( __( '<strong>A file on this server</strong> inside the uploads folder, for example %s. Files outside that folder are rejected for security reasons.', 'import-users-from-csv-with-meta' ), '<code>' . esc_html( $sample_path ) . '</code>' ); ?></li>
								</ul>
								<p class="acui-recurring-hint"><span class="dashicons dashicons-update" aria-hidden="true"></span>
								<?php if( function_exists( 'acui_ic_check_active' ) ): ?>
									<?php printf( __( 'Does this file change often? <a href="%s">Create a recurring import task</a> and it will be imported automatically.', 'import-users-from-csv-with-meta' ), esc_url( admin_url( 'tools.php?page=acui&tab=cron-import&section=new_edit' ) ) ); ?>
								<?php elseif( get_option( 'acui_cron_activated' ) ): ?>
									<?php printf( __( 'You already import a file automatically in the <a href="%1$s">Recurring</a> tab. Do you need more files, each with its own schedule, role and rules, with the columns mapped like in this assistant and an email to you when each run finishes? That is the <a href="%2$s" target="_blank">Recurring Import addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( admin_url( 'tools.php?page=acui&tab=cron' ) ), esc_url( ACUIHelper()->premium_url( 'recurring-import-addon', 'wizard-path-option' ) ) ); ?>
								<?php else: ?>
									<?php printf( __( 'Does this file change often? Import it automatically from the <a href="%1$s">Recurring</a> tab, included for free for one file in the classic format. To keep the columns of this file mapped like in this assistant, or for several files, each with its own schedule, role and rules, and an email to you when each run finishes, there is the <a href="%2$s" target="_blank">Recurring Import addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( admin_url( 'tools.php?page=acui&tab=cron' ) ), esc_url( ACUIHelper()->premium_url( 'recurring-import-addon', 'wizard-path-option' ) ) ); ?>
								<?php endif; ?>
								</p>
							</div>

							<p class="acui-templates"><span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span> <?php _e( 'No file yet? Download a template with example data:', 'import-users-from-csv-with-meta' ); ?> <?php echo ACUI_CSV_Templates::get_links_html(); ?></p>

							<?php if( !is_plugin_active( 'import-export-users-customers-file-formats/import-export-users-customers-file-formats.php' ) ): ?>
							<p class="description"><?php printf( __( 'You can also import <strong>XLSX, XLS, ODS or XML</strong> files with the <a href="%s" target="_blank">File Formats addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( ACUIHelper()->premium_url( 'file-formats-addon', 'homepage-file-field' ) ) ); ?></p>
							<?php endif; ?>
						</td>
					</tr>

					<?php do_action( 'acui_homepage_after_file_rows' ); ?>

					</tbody>
				</table>
				</details>

				<?php if( $wizard ): ?>
				<details id="acui_main_options_header" open>
				<summary><span class="acui-summary-label"><?php _e( 'Main options', 'import-users-from-csv-with-meta' ); ?></span><span class="acui-summary-chevron">▼</span></summary>
				<table id="acui_main_options_wrapper" class="form-table">
					<tbody>
					<?php echo $acui_row_role . $acui_row_existing . $acui_row_email . $acui_row_empty; ?>
					</tbody>
				</table>
				</details>

				<button type="button" class="button-link acui-wizard-more-toggle" aria-expanded="false" aria-controls="acui_roles_header acui_options_header acui_update_users_header acui_users_not_present_header">
					<span class="acui-wizard-more-closed"><?php _e( 'More options: passwords, roles and emails of existing users, users not present in the file', 'import-users-from-csv-with-meta' ); ?> ▸</span>
					<span class="acui-wizard-more-open"><?php _e( 'Fewer options', 'import-users-from-csv-with-meta' ); ?> ▴</span>
				</button>
				<?php endif; ?>

				<details id="acui_roles_header" open<?php echo $wizard ? ' class="acui-advanced"' : ''; ?>>
				<summary><span class="acui-summary-label"><?php _e( 'Roles', 'import-users-from-csv-with-meta'); ?></span><span class="acui-summary-chevron">▼</span></summary>
				<table id="acui_roles_wrapper" class="form-table">
					<tbody>

					<?php do_action( 'acui_homepage_before_roles_rows' ); ?>

					<?php if( !$wizard ) echo $acui_row_role; ?>

					<?php do_action( 'acui_homepage_after_roles_rows' ); ?>

					</tbody>
				</table>
				</details>

				<details id="acui_options_header"<?php echo $wizard ? ' class="acui-advanced"' : ''; ?>>
				<summary><span class="acui-summary-label"><?php _e( 'Options', 'import-users-from-csv-with-meta'); ?></span><span class="acui-summary-chevron">▼</span></summary>
				<table id="acui_options_wrapper" class="form-table">
					<tbody>

					<?php do_action( 'acui_homepage_before_options_rows' ); ?>

					<?php if( !$wizard ) echo $acui_row_empty; ?>

					<?php if( !$wizard ) echo $acui_row_email; ?>

					<tr class="form-field form-required">
						<th scope="row"><label for=""><?php _e( 'Force users to reset their passwords?', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
							<?php ACUIHTML()->checkbox( array( 'name' => 'force_user_reset_password', 'label' => __( 'If a password is set to a user and you activate this option, the user will be forced to reset their password at their first login', 'import-users-from-csv-with-meta' ), 'current' => 'yes', 'compare_value' => $settings->get( 'force_user_reset_password' ) ) ); ?>
							<p class="description"><?php echo sprintf( __( 'Please, <a href="%s">read the documentation</a> before activating this option', 'import-users-from-csv-with-meta' ), admin_url( 'tools.php?page=acui&tab=doc#force_user_reset_password' ) ); ?></p>
						</td>
					</tr>

					<?php do_action( 'acui_homepage_after_options_rows' ); ?>

					</tbody>
				</table>
				</details>

				<details id="acui_update_users_header"<?php echo $wizard ? ' class="acui-advanced"' : ''; ?>>
				<summary><span class="acui-summary-label"><?php _e( 'Update users', 'import-users-from-csv-with-meta'); ?></span><span class="acui-summary-chevron">▼</span></summary>
				<table id="acui_update_users_wrapper" class="form-table">
					<tbody>

					<?php do_action( 'acui_homepage_before_update_users_rows' ); ?>

					<?php if( !$wizard ) echo $acui_row_existing; ?>

					<tr id="acui_update_emails_existing_users_wrapper" class="form-field form-required">
						<th scope="row"><label for="update_emails_existing_users"><?php _e( 'Update emails?', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
							<?php ACUIHTML()->select( array(
								'options' => array( 'no' => __( 'No', 'import-users-from-csv-with-meta' ), 'create' => __( 'No, but create a new user with a prefix in the username', 'import-users-from-csv-with-meta' ), 'yes' => __( 'Yes', 'import-users-from-csv-with-meta' ) ),
								'name' => 'update_emails_existing_users',
								'show_option_all' => false,
								'show_option_none' => false,
								'selected' => $settings->get( 'update_emails_existing_users' ),
							)); ?>
							<p class="description"><?php _e( 'What the plugin should do if the plugin find a user, identified by their username, with a different email', 'import-users-from-csv-with-meta' ); ?></p>
						</td>
					</tr>

					<tr id="acui_update_roles_existing_users_wrapper" class="form-field form-required">
						<th scope="row"><label for="update_roles_existing_users"><?php _e( 'Update roles for existing users?', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
							<?php ACUIHTML()->select( array(
								'options' => array( 'no' => __( 'No', 'import-users-from-csv-with-meta' ), 'yes' => __( 'Yes, update and override existing roles', 'import-users-from-csv-with-meta' ), 'yes_no_override' => __( 'Yes, add new roles and do not override existing ones', 'import-users-from-csv-with-meta' ) ),
								'name' => 'update_roles_existing_users',
								'show_option_all' => false,
								'show_option_none' => false,
								'selected' => $settings->get( 'update_roles_existing_users' ),
							)); ?>
						</td>
					</tr>

					<tr id="acui_update_allow_update_passwords_wrapper" class="form-field form-required">
						<th scope="row"><label for="update_allow_update_passwords"><?php _e( 'Update passwords for existing users?', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
							<?php ACUIHTML()->select( array(
								'options' => array( 'no' => __( 'No, never update passwords for existing users', 'import-users-from-csv-with-meta' ), 'yes' => __( 'Yes, update passwords as described in documentation', 'import-users-from-csv-with-meta' ) ),
								'name' => 'update_allow_update_passwords',
								'show_option_all' => false,
								'show_option_none' => false,
								'selected' => $settings->get( 'update_allow_update_passwords' ),
							)); ?>
						</td>
					</tr>

					<?php do_action( 'acui_homepage_after_update_users_rows' ); ?>

					</tbody>
				</table>
				</details>

				<details id="acui_users_not_present_header"<?php echo $wizard ? ' class="acui-advanced"' : ''; ?>>
				<summary><span class="acui-summary-label"><?php _e( 'Users not present in CSV file', 'import-users-from-csv-with-meta'); ?></span><span class="acui-summary-chevron">▼</span></summary>
				<table id="acui_users_not_present_wrapper" class="form-table">
					<tbody>

					<?php do_action( 'acui_homepage_before_users_not_present_rows' ); ?>

					<tr id="acui_delete_users_wrapper" class="form-field form-required">
						<th scope="row"><label for="delete_users_not_present"><?php _e( 'Delete users that are not present in the CSV?', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
							<div style="float:left; margin-top: 10px;">
								<?php ACUIHTML()->checkbox( array( 'name' => 'delete_users_not_present', 'current' => 'yes', 'compare_value' => $settings->get( 'delete_users_not_present' ) ) ); ?>
							</div>
							<div style="margin-left:25px;">
								<?php ACUIHTML()->select( array(
									'options' => $delete_users_assign_posts_options,
									'name' => 'delete_users_assign_posts',
									'show_option_all' => false,
									'show_option_none' => __( 'Delete posts of deleted users without assigning them to another user, or type to search for a user to assign the posts to', 'import-users-from-csv-with-meta' ),
									'selected' => $delete_users_assign_posts_option_selected,
								)); ?>
								<p class="description"><?php _e( 'Administrators will not be deleted anyway. After deleting users, you can choose if you want to assign their posts to another user. If you do not choose a user, their content will be deleted.', 'import-users-from-csv-with-meta' ); ?></p>
							</div>
						</td>
					</tr>

					<tr id="acui_not_present_wrapper" class="form-field form-required">
						<th scope="row"><label for="change_role_not_present"><?php _e( 'Change role of users that are not present in the CSV?', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
							<div style="float:left; margin-top: 10px;">
								<?php ACUIHTML()->checkbox( array( 'name' => 'change_role_not_present', 'current' => 'yes', 'compare_value' => $settings->get( 'change_role_not_present' ) ) ); ?>
							</div>
							<div style="margin-left:25px;">
								<?php ACUIHTML()->select( array(
									'options' => ACUI_Helper::get_editable_roles(),
									'name' => 'change_role_not_present_role',
									'show_option_all' => false,
									'show_option_none' => false,
									'selected' => $settings->get( 'change_role_not_present_role' ),
								)); ?>
								<p class="description"><?php _e( 'After importing users from a CSV, users not present in the CSV can have their roles changed to a different role.', 'import-users-from-csv-with-meta' ); ?></p>
							</div>
						</td>
					</tr>

					<tr id="acui_not_present_same_role" class="form-field form-required">
						<th scope="row"><label for="not_present_same_role"><?php _e( 'Apply only to users with the same role as imported users', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
							<?php ACUIHTML()->select( array(
								'options' => array(
									'no'  => __( 'No, apply to all users regardless of their role', 'import-users-from-csv-with-meta' ),
									'yes' => __( 'Yes, only users who have the role(s) of the imported users', 'import-users-from-csv-with-meta' ),
								),
								'name' => 'not_present_same_role',
								'show_option_all' => false,
								'show_option_none' => false,
								'selected' => $settings->get( 'not_present_same_role' ),
							)); ?>
							<p class="description"><?php _e( 'Sometimes, you may want only the users of the imported users\' role to be affected and not the rest of the system user.', 'import-users-from-csv-with-meta' ); ?></p>
						</td>
					</tr>

					<tr id="acui_not_present_only_imported_wrapper" class="form-field form-required">
						<th scope="row"><label for="not_present_only_imported"><?php _e( 'Apply only to users previously imported by this plugin?', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
							<?php ACUIHTML()->select( array(
								'options' => array(
									'no'  => __( 'No, apply to all existing users', 'import-users-from-csv-with-meta' ),
									'yes' => __( 'Yes, only affect users that were imported by this plugin', 'import-users-from-csv-with-meta' ),
								),
								'name' => 'not_present_only_imported',
								'show_option_all' => false,
								'show_option_none' => false,
								'selected' => $settings->get( 'not_present_only_imported' ),
							)); ?>
							<p class="description"><?php _e( 'Users created manually in WordPress will not be affected.', 'import-users-from-csv-with-meta' ); ?></p>
						</td>
					</tr>

					<?php do_action( 'acui_homepage_after_users_not_present_rows' ); ?>

					</tbody>
				</table>
				</details>

				</div><?php /* .acui-accordion */ ?>

				<div class="acui-wizard-extra">
				<?php do_action( 'acui_tab_import_before_import_button' ); ?>
				</div>

				<?php if( $wizard ): ?>
				<div class="acui-wizard-nav">
					<button type="button" class="button acui-wizard-back"><?php _e( 'Back', 'import-users-from-csv-with-meta' ); ?></button>
					<span class="acui-wizard-nav-info"></span>
					<button type="button" class="button button-primary acui-wizard-next"><?php _e( 'Continue', 'import-users-from-csv-with-meta' ); ?></button>
					<button type="submit" class="button button-primary acui-wizard-import" id="acui_wizard_import"><?php _e( 'Start importing', 'import-users-from-csv-with-meta' ); ?></button>
				</div>
				<?php endif; ?>

				</div>
				<?php wp_nonce_field( 'codection-security', 'security' ); ?>
				</form>
				<div id="acui_import_results">
					<div class="acui-log-summary">
						<h3><?php _e( 'Results', 'import-users-from-csv-with-meta' ); ?></h3>
						<div class="acui-log-cards">
							<div class="acui-log-card acui-log-card--processed">
								<span class="acui-log-card__number" id="acui_result_processed">0</span>
								<span class="acui-log-card__label"><?php _e( 'Processed', 'import-users-from-csv-with-meta' ); ?></span>
							</div>
							<div class="acui-log-card acui-log-card--created">
								<span class="acui-log-card__number" id="acui_result_created">0</span>
								<span class="acui-log-card__label"><?php _e( 'Created', 'import-users-from-csv-with-meta' ); ?></span>
							</div>
							<div class="acui-log-card acui-log-card--updated">
								<span class="acui-log-card__number" id="acui_result_updated">0</span>
								<span class="acui-log-card__label"><?php _e( 'Updated', 'import-users-from-csv-with-meta' ); ?></span>
							</div>
							<div class="acui-log-card acui-log-card--deleted">
								<span class="acui-log-card__number" id="acui_result_deleted">0</span>
								<span class="acui-log-card__label"><?php _e( 'Deleted', 'import-users-from-csv-with-meta' ); ?></span>
							</div>
							<div class="acui-log-card acui-log-card--no-errors" id="acui_issues_card">
								<span class="acui-log-card__number" id="acui_result_errors">0</span>
								<span class="acui-log-card__label"><?php _e( 'Issues', 'import-users-from-csv-with-meta' ); ?></span>
							</div>
						</div>
					</div>
				</div>
				<div id="acui_import_log" style="margin-top: 20px;"></div>
			</div>

			<div class="sidebar" style="position:sticky;top:32px;align-self:flex-start;">
				<div class="sidebar_section" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:hidden;padding:0;margin-bottom:16px;">
					<h3 style="margin:0;padding:12px 16px;font-size:13px;font-weight:600;border-bottom:1px solid #c3c4c7;background:#f6f7f7;"><?php _e( 'Actions', 'import-users-from-csv-with-meta' ); ?></h3>
					<div style="padding:12px 16px;display:flex;flex-direction:column;gap:8px;">
						<button type="submit" name="uploadfile" id="uploadfile_btn" form="acui_form" class="button button-primary" style="width:100%;">
							<?php _e( 'Start importing', 'import-users-from-csv-with-meta' ); ?>
						</button>
						<button type="submit" name="save_options" form="acui_form" class="button button-secondary" style="width:100%;">
							<?php _e( 'Save Settings', 'import-users-from-csv-with-meta' ); ?>
						</button>
					</div>
				</div>

				<div class="sidebar_section sidebar-info">
					<?php if( $wizard ): ?>
					<p><?php _e( 'Your columns can have <strong>any name and any order</strong>: you match them with WordPress fields in the second step. The email is required to create users.', 'import-users-from-csv-with-meta' ); ?></p>
					<?php else: ?>
					<p><?php printf( __( 'The CSV must have at least <strong>2 columns: username and email</strong>, in that order. Extra columns are matched by their header name. Both fields are required unless you use <a href="%s">the Allow No Email addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( ACUIHelper()->premium_url( 'allow-no-email-addon', 'homepage-sidebar' ) ) ); ?></p>
					<?php endif; ?>
					<p><?php _e( 'Read the documentation on how <strong>passwords are managed</strong>. This plugin is <strong>case sensitive</strong>.', 'import-users-from-csv-with-meta' ); ?></p>
					<?php if( ( class_exists( 'WeDevs_Dokan' ) || function_exists( 'dokan' ) ) && !function_exists( 'acui_da_check_active' ) ): ?>
					<p><?php printf( __( 'Using <strong>Dokan</strong>? Import your marketplace vendors with the <a href="%s" target="_blank">Dokan addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( ACUIHelper()->premium_url( 'dokan-addon', 'homepage-sidebar-dokan' ) ) ); ?></p>
					<?php endif; ?>
				</div>

				<div class="sidebar_section premium_addons">
					<a class="premium-addons" color="primary" type="button" name="premium-addons" data-tag="premium-addons" href="<?php echo esc_url( ACUIHelper()->premium_url( '', 'homepage-sidebar-button' ) ); ?>" role="button" target="_blank">
						<div><span><?php _e( 'Premium Addons', 'import-users-from-csv-with-meta'); ?></span></div>
					</a>
				</div>

				<div class="sidebar_section">
					<h3><?php _e( 'Having issues?', 'import-users-from-csv-with-meta'); ?></h3>
					<ul>
						<li><label><?php _e( 'You can create a ticket', 'import-users-from-csv-with-meta'); ?></label> <a target="_blank" href="http://wordpress.org/support/plugin/import-users-from-csv-with-meta"><label><?php _e( 'WordPress support forum', 'import-users-from-csv-with-meta'); ?></label></a></li>
						<li><label><?php _e( 'You can ask for premium support', 'import-users-from-csv-with-meta'); ?></label> <a target="_blank" href="mailto:contacto@codection.com"><label>contacto@codection.com</label></a></li>
					</ul>
					<p style="margin-top:10px; font-size:11px;">
						<a target="_blank" href="https://ko-fi.com/codection"><?php _e( '☕ Buy me a coffee', 'import-users-from-csv-with-meta'); ?></a>
						&nbsp;·&nbsp;
						<a target="_blank" href="https://www.patreon.com/carazo"><?php _e( 'Become a patron', 'import-users-from-csv-with-meta'); ?></a>
					</p>
				</div>
			</div>
		</div>
	</div>

	<script type="text/javascript">
	jQuery( document ).ready( function( $ ){
		document.querySelectorAll( '.acui-accordion details' ).forEach( function( el ){
			var key = 'acui_acc_' + el.id;
			var stored = localStorage.getItem( key );
			if( stored === 'open' ) el.open = true;
			if( stored === 'closed' ) el.open = false;
			el.addEventListener( 'toggle', function(){
				localStorage.setItem( key, el.open ? 'open' : 'closed' );
			} );
		} );

		check_delete_users_checked();
		toggle_update_existing_users_fields();

		$( '#update_existing_users' ).on( 'change', function(){
			toggle_update_existing_users_fields();
		} );

        $( '#uploadfile_btn,#uploadfile_btn_up' ).click( function(){
            if( $( '#uploadfile' ).val() == "" && $( '#upload_file' ).is( ':visible' ) ) {
                alert("<?php _e( 'Please choose a file', 'import-users-from-csv-with-meta' ); ?>");
                return false;
            }

            if( $( '#path_to_file' ).val() == "" && $( '#introduce_path' ).is( ':visible' ) ) {
                alert("<?php _e( 'Please enter a path to the file', 'import-users-from-csv-with-meta' ); ?>");
                return false;
            }
        } );

		$( '.acui-checkbox.roles[value="no_role"]' ).click( function(){
			var checked = $( this ).is(':checked');
			if( checked ) {
				if( !confirm( '<?php _e( 'Are you sure you want to disables roles from these users?', 'import-users-from-csv-with-meta' ); ?>' ) ){         
					$( this ).removeAttr( 'checked' );
					return;
				}
				else{
					$( '.acui-checkbox.roles' ).not( '.acui-checkbox.roles[value="no_role"]' ).each( function(){
						$( this ).removeAttr( 'checked' );
					} )
				}
			}
		} );

		$( '.acui-checkbox.roles' ).click( function(){
			if( $( this ).val() != 'no_role' && $( this ).val() != '' )
				$( '.acui-checkbox.roles[value="no_role"]' ).removeAttr( 'checked' );
		} );

		$( '#delete_users_not_present' ).on( 'click', function() {
			check_delete_users_checked();
		});

		$( 'input[name="acui_file_source"]' ).on( 'change', function(){
			var path = $( 'input[name="acui_file_source"]:checked' ).val() === 'path';
			$( '#upload_file' ).toggle( !path );
			$( '#introduce_path' ).toggle( path );
		} );

		function acui_show_file_name(){
			var input = document.getElementById( 'uploadfile' );
			var name = ( input && input.files && input.files.length ) ? input.files[0].name : '';
			$( '.acui-dropzone__file' ).text( name ).prop( 'hidden', name === '' );
			$( '.acui-dropzone' ).toggleClass( 'acui-dropzone--filled', name !== '' );
		}

		$( '#uploadfile' ).on( 'change', acui_show_file_name );

		$( '.acui-dropzone' ).on( 'dragenter dragover', function( e ){
			e.preventDefault();
			$( this ).addClass( 'acui-dropzone--over' );
		} ).on( 'dragleave dragend drop', function(){
			$( this ).removeClass( 'acui-dropzone--over' );
		} ).on( 'drop', function( e ){
			e.preventDefault();
			var files = e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : null;
			if( files && files.length ){
				document.getElementById( 'uploadfile' ).files = files;
				$( '#uploadfile' ).trigger( 'change' );
			}
		} );

		$( '.acui-check-path-btn' ).click( function( e ){
			e.preventDefault();

			var $btn    = $( this );
			var target  = $btn.data( 'target' );
			var $input  = $( '#' + target );
			var $result = $( '.acui-path-check-result[data-target="' + target + '"]' );

			$btn.prop( 'disabled', true );
			$result.show().removeClass( 'acui-path-ok acui-path-bad' ).text( '<?php echo esc_js( __( 'Checking…', 'import-users-from-csv-with-meta' ) ); ?>' );

			$.post( ajaxurl, {
				action: 'acui_check_local_path',
				security: '<?php echo wp_create_nonce( 'codection-security' ); ?>',
				path: $input.val()
			}, function( response ){
				var ok = ( response.status === 'valid' || response.status === 'url' );
				$result.text( response.message ).toggleClass( 'acui-path-ok', ok ).toggleClass( 'acui-path-bad', !ok );
			}, 'json' ).fail( function(){
				$result.text( '<?php echo esc_js( __( 'Could not check the path, please try again.', 'import-users-from-csv-with-meta' ) ); ?>' ).addClass( 'acui-path-bad' );
			} ).always( function(){
				$btn.prop( 'disabled', false );
			} );
		} );

		$( '#vote_us' ).click( function(){
			var win=window.open( 'http://wordpress.org/support/view/plugin-reviews/import-users-from-csv-with-meta?free-counter?rate=5#postform', '_blank');
			win.focus();
		} );

		function acui_init_role_select(){
			if( $( '#role' ).data( 'select2' ) )
				$( '#role' ).select2( 'destroy' );

			$( '#role' ).select2({
				width: $( '#acui_wizard_role_row' ).length ? '100%' : '80%',
				placeholder: $( '#role' ).attr( 'data-placeholder' ) || ''
			});
		}

		acui_init_role_select();

		$( 'input[name="acui_role_mode"]' ).on( 'change', function(){
			var custom = $( 'input[name="acui_role_mode"]:checked' ).val() === 'custom';
			$( '.acui-role-custom' ).toggle( custom );

			if( custom )
				acui_init_role_select();
		} );

        $( '#change_role_not_present_role' ).select2({ width: '80%' });

        $( '#delete_users_assign_posts' ).select2({
            allowClear: true,
            placeholder: '<?php _e( 'Delete posts of deleted users without assigning to any user', 'import-users-from-csv-with-meta' )  ?>',
            width: '80%',
            ajax: {
                url: '<?php echo admin_url( 'admin-ajax.php' ) ?>',
                cache: true,
                dataType: 'json',
                minimumInputLength: 3,
                data: function( params ) {
                    var query = {
                        search: params.term,
                        _wpnonce: '<?php echo wp_create_nonce( 'codection-security' ); ?>',
                        action: 'acui_delete_users_assign_posts_data',
                    }

                    return query;
                }
            },
        });

		function toggle_update_existing_users_fields(){
			var show = $( '#update_existing_users' ).val() == 'yes';
			$( '#acui_update_emails_existing_users_wrapper, #acui_update_roles_existing_users_wrapper, #acui_update_allow_update_passwords_wrapper' ).toggle( show );
		}

		function check_delete_users_checked(){
			if( $( '#delete_users_not_present' ).is( ':checked' ) ){
                $( '#delete_users_assign_posts' ).prop( 'disabled', false );
				$( '#change_role_not_present' ).prop( 'disabled', true );
				$( '#change_role_not_present_role' ).prop( 'disabled', true );				
			} else {
                $( '#delete_users_assign_posts' ).prop( 'disabled', true );
				$( '#change_role_not_present' ).prop( 'disabled', false );
				$( '#change_role_not_present_role' ).prop( 'disabled', false );
			}
		}
	} );
	</script>
	<?php 
	}

	function delete_users_assign_posts_data(){
        check_ajax_referer( 'codection-security', 'security' );
	
		if( ! current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
            wp_die( __( 'Only users who are allowed to create users can manage this option.', 'import-users-from-csv-with-meta' ) );

        $results = array( array( 'id' => '', 'value' => __( 'Delete posts of deleted users without assigning to any user', 'import-users-from-csv-with-meta' ) ) );
        $search = sanitize_text_field( $_GET['search'] );

        if( strlen( $search ) >= 3 ){
            $blogusers = get_users( array( 'fields' => array( 'ID', 'display_name' ), 'search' => '*' . $search . '*' ) );
            
            foreach ( $blogusers as $bloguser ) {
                $results[] = array( 'id' => $bloguser->ID, 'text' => $bloguser->display_name );
            }
        }
        
        echo json_encode( array( 'results' => $results, 'more' => 'false' ) );
        
        wp_die();
    }
}

$acui_homepage = new ACUI_Homepage();
$acui_homepage->hooks();
