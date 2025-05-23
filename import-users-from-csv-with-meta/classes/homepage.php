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

        wp_enqueue_style( 'select2-css', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css' );
        wp_enqueue_script( 'select2-js', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js' );
    }

	static function admin_gui(){
		$settings = new ACUI_Settings( 'import_backend' );
		$settings->maybe_migrate_old_options( 'import_backend' );
		$upload_dir = wp_upload_dir();
		$sample_path = $upload_dir["path"] . '/test.csv';
		$sample_url = plugin_dir_url( dirname( __FILE__ ) ) . 'test.csv';

		if( ctype_digit( $settings->get( 'delete_users_assign_posts' ) ) ){
			$delete_users_assign_posts_user = get_user_by( 'id', $settings->get( 'delete_users_assign_posts' ) );
			$delete_users_assign_posts_options = array( $settings->get( 'delete_users_assign_posts' ) => $delete_users_assign_posts_user->display_name );
			$delete_users_assign_posts_option_selected = $settings->get( 'delete_users_assign_posts' );
		}
		else{
			$delete_users_assign_posts_options = array( 0 => __( 'No user selected', 'import-users-from-csv-with-meta' ) );
			$delete_users_assign_posts_option_selected = 0;
		}
?>
	<div class="wrap acui">	

		<div class="row">
			<div class="header">
				<?php do_action( 'acui_homepage_start' ); ?>

				<div id='message' class='updated acui-message'><?php printf( __( 'File must contain at least <strong>2 columns: username and email</strong>. These should be the first two columns and it should be placed <strong>in this order: username and email</strong>. Both data are required unless you use <a href="%s">this addon to allow empty emails</a>. If there are more columns, this plugin will manage it automatically.', 'import-users-from-csv-with-meta' ), 'https://import-wp.com/allow-no-email-addon/' ); ?></div>
				<div id='message-password' class='error acui-message'><?php _e( 'Please, read carefully how <strong>passwords are managed</strong> and also take note about capitalization, this plugin is <strong>case sensitive</strong>.', 'import-users-from-csv-with-meta' ); ?></div>
			</div>
		</div>

		<div class="row">
			<div class="main_bar">
				<form method="POST" id="acui_form" enctype="multipart/form-data" action="" accept-charset="utf-8">

				<input class="button-primary" type="submit" name="uploadfile" id="uploadfile_btn_up" value="<?php _e( 'Start importing', 'import-users-from-csv-with-meta' ); ?>"/>
				<input class="button-primary" type="submit" name="save_options" value="<?php _e( 'Save options without importing', 'import-users-from-csv-with-meta' ); ?>"/>

				<h2 id="acui_file_header"><?php _e( 'File', 'import-users-from-csv-with-meta'); ?></h2>
				<table  id="acui_file_wrapper" class="form-table">
					<tbody>

					<?php do_action( 'acui_homepage_before_file_rows' ); ?>

					<tr class="form-field form-required">
						<th scope="row"><label for="uploadfile"><?php _e( 'CSV file <span class="description">(required)</span></label>', 'import-users-from-csv-with-meta' ); ?></th>
						<td>
							<div id="upload_file">
								<input type="file" name="uploadfile" id="uploadfile" size="35" class="uploadfile" />
								<?php _e( '<em>or you can choose directly a file from your host or from an external URL', 'import-users-from-csv-with-meta' ) ?> <a href="#" class="toggle_upload_path"><?php _e( 'click here', 'import-users-from-csv-with-meta' ) ?></a>.</em>
							</div>
							<div id="introduce_path" style="display:none;">
								<input placeholder="<?php printf( __( 'You have to enter the URL or the path to the file, i.e.: %s or %s' ,'import-users-from-csv-with-meta' ), $sample_path, $sample_url ); ?>" type="text" name="path_to_file" id="path_to_file" value="<?php echo $settings->get( 'path_to_file' ); ?>" style="width:70%;" />
								<em><?php _e( 'or you can upload it directly from your computer', 'import-users-from-csv-with-meta' ); ?>, <a href="#" class="toggle_upload_path"><?php _e( 'click here', 'import-users-from-csv-with-meta' ); ?></a>.</em>
							</div>
						</td>
					</tr>

					<?php do_action( 'acui_homepage_after_file_rows' ); ?>

					</tbody>
				</table>
					
				<h2 id="acui_roles_header"><?php _e( 'Roles', 'import-users-from-csv-with-meta'); ?></h2>
				<table id="acui_roles_wrapper" class="form-table">
					<tbody>

					<?php do_action( 'acui_homepage_before_roles_rows' ); ?>

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
						<p class="description"><?php _e( sprintf( 'You can also import roles from a CSV column. Please read documentation tab to see how it can be done. If you choose more than one role, the roles would be assigned correctly but you should use <a href="https://wordpress.org/plugins/profile-builder/">Profile Builder - Roles Editor</a> to manage them. <a href="%s">Click to Install & Activate</a>', esc_url( wp_nonce_url( self_admin_url('update.php?action=install-plugin&plugin=profile-builder'), 'install-plugin_profile-builder') ) ), 'import-users-from-csv-with-meta' ); ?></p>
						
						</td>
					</tr>

					<?php do_action( 'acui_homepage_after_roles_rows' ); ?>

					</tbody>
				</table>

				<h2 id="acui_options_header"><?php _e( 'Options', 'import-users-from-csv-with-meta'); ?></h2>
				<table id="acui_options_wrapper" class="form-table">
					<tbody>

					<?php do_action( 'acui_homepage_before_options_rows' ); ?>

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

				<h2 id="acui_update_users_header"><?php _e( 'Update users', 'import-users-from-csv-with-meta'); ?></h2>

				<table id="acui_update_users_wrapper" class="form-table">
					<tbody>

					<?php do_action( 'acui_homepage_before_update_users_rows' ); ?>

					<tr id="acui_update_existing_users_wrapper" class="form-field form-required">
						<th scope="row"><label for="update_existing_users"><?php _e( 'Update existing users?', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
							<?php ACUIHTML()->select( array(
								'options' => array( 'no' => __( 'No', 'import-users-from-csv-with-meta' ), 'yes' => __( 'Yes', 'import-users-from-csv-with-meta' ), ),
								'name' => 'update_existing_users',
								'show_option_all' => false,
								'show_option_none' => false,
								'selected' => $settings->get( 'update_existing_users' ),
							)); ?>
						</td>
					</tr>

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
						<th scope="row"><label for="update_allow_update_passwords"><?php _e( 'Never update passwords?', 'import-users-from-csv-with-meta' ); ?></label></th>
						<td>
							<?php ACUIHTML()->select( array(
								'options' => array( 'no' => __( 'Never update passwords when updating a user', 'import-users-from-csv-with-meta' ), 'yes_no_override' => __( 'Yes, add new roles and do not override existing ones', 'import-users-from-csv-with-meta' ), 'yes' => __( 'Update passwords as it is described in documentation', 'import-users-from-csv-with-meta' ) ),
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

				<h2 id="acui_users_not_present_header"><?php _e( 'Users not present in CSV file', 'import-users-from-csv-with-meta'); ?></h2>

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
								'options' => array( 'no' => __( 'No, apply to all users regardless of their role', 'import-users-from-csv-with-meta' ), 'yes' => __( 'Yes, delete or modify the role only for users who have the role(s) of the imported user(s).', 'import-users-from-csv-with-meta' ) ),
								'name' => 'not_present_same_role',
								'show_option_all' => false,
								'show_option_none' => false,
								'selected' => $settings->get( 'not_present_same_role' ),
							)); ?>
							<p class="description"><?php _e( 'Sometimes, you may want only the users of the imported users\' role to be affected and not the rest of the system user.', 'import-users-from-csv-with-meta' ); ?></p>
						</td>
					</tr>

					<?php do_action( 'acui_homepage_after_users_not_present_rows' ); ?>

					</tbody>
				</table>

				<?php do_action( 'acui_tab_import_before_import_button' ); ?>
					
				<?php wp_nonce_field( 'codection-security', 'security' ); ?>

				<input class="button-primary" type="submit" name="uploadfile" id="uploadfile_btn" value="<?php _e( 'Start importing', 'import-users-from-csv-with-meta' ); ?>"/>
				<input class="button-primary" type="submit" name="save_options" value="<?php _e( 'Save options without importing', 'import-users-from-csv-with-meta' ); ?>"/>
				</form>
			</div>

			<div class="sidebar">
				<div class="sidebar_section premium_addons">
					<a class="premium-addons" color="primary" type="button" name="premium-addons" data-tag="premium-addons" href="https://www.import-wp.com/" role="button" target="_blank">
						<div><span><?php _e( 'Premium Addons', 'import-users-from-csv-with-meta'); ?></span></div>
					</a>
				</div>

				<div class="sidebar_section premium_addons">
					<a class="premium-addons" color="primary" type="button" name="premium-addons" data-tag="premium-addons" href="https://import-wp.com/recurring-export-addon/" role="button" target="_blank">
						<div><span><?php _e( 'Automatic Exports', 'import-users-from-csv-with-meta'); ?></span></div>
					</a>
				</div>

				<div class="sidebar_section premium_addons">
					<a class="premium-addons" color="primary" type="button" name="premium-addons" data-tag="premium-addons" href="https://import-wp.com/allow-no-email-addon/" role="button" target="_blank">
						<div><span><?php _e( 'Allow No Email', 'import-users-from-csv-with-meta'); ?></span></div>
					</a>
				</div>

				<div class="sidebar_section become_patreon">
					<a class="patreon" color="primary" type="button" name="become-a-patron" data-tag="become-patron-button" href="https://www.patreon.com/carazo" role="button" target="_blank">
						<div><span><?php _e( 'Become a patron', 'import-users-from-csv-with-meta'); ?></span></div>
					</a>
				</div>

				<div class="sidebar_section buy_me_a_coffee">
					<a class="ko-fi" color="primary" type="button" name="buy-me-a-coffee" data-tag="buy-me-a-button" href="https://ko-fi.com/codection" role="button" target="_blank">
						<div><span><?php _e( 'Buy me a coffee', 'import-users-from-csv-with-meta'); ?></span></div>
					</a>
				</div>

				<div class="sidebar_section vote_us">
					<a class="vote-us" color="primary" type="button" name="vote-us" data-tag="vote_us" href="https://wordpress.org/support/plugin/import-users-from-csv-with-meta/reviews/" role="button" target="_blank">
						<div><span><?php _e( 'If you like it', 'import-users-from-csv-with-meta'); ?> <?php _e( 'Please vote and support us', 'import-users-from-csv-with-meta'); ?></span></div>
					</a>
				</div>

				<div class="sidebar_section donate">
					<a class="donate-button" color="primary" type="button" name="donate-button" data-tag="donate" href="https://paypal.me/imalrod" role="button" target="_blank">
						<div><span><?php _e( 'If you want to help us to continue developing it and give the best support, you can donate', 'import-users-from-csv-with-meta'); ?></span></div>
					</a>
				</div>
				
				<div class="sidebar_section">
					<h3><?php _e( 'Having issues?', 'import-users-from-csv-with-meta'); ?></h3>
					<ul>
						<li><label><?php _e( 'You can create a ticket', 'import-users-from-csv-with-meta'); ?></label> <a target="_blank" href="http://wordpress.org/support/plugin/import-users-from-csv-with-meta"><label><?php _e( 'WordPress support forum', 'import-users-from-csv-with-meta'); ?></label></a></li>
						<li><label><?php _e( 'You can ask for premium support', 'import-users-from-csv-with-meta'); ?></label> <a target="_blank" href="mailto:contacto@codection.com"><label>contacto@codection.com</label></a></li>
					</ul>
				</div>
			</div>
		</div>

		
		<!--<div class="row">
			<div class="batch-importer">
				<h1><?php esc_html_e( 'Import Products', 'woocommerce' ); ?></h1>
				<div class="wc-progress-form-content woocommerce-importer woocommerce-importer__importing">
					<header>
						<span class="spinner is-active"></span>
						<h2><?php esc_html_e( 'Importing', 'woocommerce' ); ?></h2>
						<p><?php esc_html_e( 'Your users are now being imported...', 'woocommerce' ); ?></p>
					</header>
					<section>
						<progress class="acui-importer-progress" max="100" value="0"></progress>
					</section>
				</div>

				<div class="woocommerce-progress-form-wrapper">
					<ol class="wc-progress-steps">
						<?php /*foreach ( $this->steps as $step_key => $step ) : ?>
							<?php
							$step_class = '';
							if ( $step_key === $this->step ) {
								$step_class = 'active';
							} elseif ( array_search( $this->step, array_keys( $this->steps ), true ) > array_search( $step_key, array_keys( $this->steps ), true ) ) {
								$step_class = 'done';
							}
							?>
							<li class="<?php echo esc_attr( $step_class ); ?>">
								<?php echo esc_html( $step['name'] ); ?>
							</li>
						<?php endforeach;*/ ?>
					</ol>
				</div>
			</div>
		</div> -->
	</div>

	<script type="text/javascript">
	jQuery( document ).ready( function( $ ){
		check_delete_users_checked();

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

		$( '.toggle_upload_path' ).click( function( e ){
			e.preventDefault();
			$( '#upload_file,#introduce_path' ).toggle();
		} );

		$( '#vote_us' ).click( function(){
			var win=window.open( 'http://wordpress.org/support/view/plugin-reviews/import-users-from-csv-with-meta?free-counter?rate=5#postform', '_blank');
			win.focus();
		} );

		$( '#role' ).select2();

        $( '#change_role_not_present_role' ).select2();

        $( '#delete_users_assign_posts' ).select2({
            ajax: {
                url: '<?php echo admin_url( 'admin-ajax.php' ) ?>',
                cache: true,
                dataType: 'json',
                minimumInputLength: 3,
                allowClear: true,
                placeholder: { id: '', title: '<?php _e( 'Delete posts of deleted users without assigning to any user', 'import-users-from-csv-with-meta' )  ?>' },
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
