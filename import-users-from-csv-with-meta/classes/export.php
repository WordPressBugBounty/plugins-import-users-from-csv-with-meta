<?php
if ( ! defined( 'ABSPATH' ) ) exit; 

class ACUI_Exporter{
	private $path_csv;

	function __construct(){
		$upload_dir = wp_upload_dir();
		$this->path_csv = $upload_dir['basedir'] . "/export-users.csv";

        add_action( 'init', array( $this, 'download_export_file' ), 11 );
        add_action( 'admin_init', array( $this, 'download_export_file' ) );
		add_action( 'wp_ajax_acui_export_users_csv', array( $this, 'export_users_csv' ) );
		add_action( 'wp_ajax_acui_export_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_acui_export_search_users', array( $this, 'ajax_search_users' ) );
	}

    static function enqueue(){
        wp_enqueue_script( 'acui_export_js', plugins_url( 'assets/export.js', dirname( __FILE__ ) ), array( 'jquery', 'jquery-ui-sortable' ), filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'assets/export.js' ), true );
        wp_localize_script( 'acui_export_js', 'acui_export_js_object', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'starting_process' => __( 'Starting process', 'import-users-from-csv-with-meta' ),
            'step' => __( 'Step', 'import-users-from-csv-with-meta' ),
            'of_approximately' => __( 'of approximately', 'import-users-from-csv-with-meta' ),
            'steps' => __( 'steps', 'import-users-from-csv-with-meta' ),
            'error_thrown' => __( 'Error thrown on the server, cannot continue. Please check console to see full details about the error.', 'import-users-from-csv-with-meta' ),
        ) );
    }

    static function styles(){
        ?>
        <style>
#acui_export_results{
	display: none;
	background-color: #dcdcde;
	padding: 20px;
}

.acui_exporter .user-exporter-progress-wrapper{
    padding: 5px;
    background-color: white;
    width: 80%;
    margin: 0 auto;
    text-align: center;
}

.acui_exporter .user-exporter-progress{
    width: 100%;
    height: 42px;
	border: 0;
	border-radius: 9px;
}
.user-exporter-progress::-webkit-progress-bar {
	background-color: #f3f3f3;
	border-radius: 9px;
}

.user-exporter-progress::-webkit-progress-value {
	background: #2271b1;
	border-radius: 9px;
}

.user-exporter-progress::-moz-progress-bar {
	background: #2271b1;
	border-radius: 9px;
}

.user-exporter-progress .progress-value {
	padding: 0px 5px;
	line-height: 20px;
	margin-left: 5px;
	font-size: .8em;
	color: #555;
	height: 18px;
	float: right;
}

.acui_exporter.user-exporter__exporting table,
.acui_exporter .user-exporter-progress-wrapper{
    display: none;
}

.acui_exporter.user-exporter__exporting .user-exporter-progress-wrapper{
    display: block;
}
        </style>
        <?php
    }

	static function admin_gui(){
		$settings = new ACUI_Settings( 'export_backend' );
	?>
	<style>
	.acui-export-layout {
		display: flex;
		gap: 24px;
		align-items: flex-start;
	}
	.acui-export-main { flex: 1; min-width: 0; }
	.acui-export-sidebar {
		width: 280px;
		flex-shrink: 0;
		position: sticky;
		top: 32px;
		align-self: flex-start;
	}
	.acui-export-card {
		background: #fff;
		border: 1px solid #c3c4c7;
		border-radius: 4px;
		box-shadow: 0 1px 3px rgba(0,0,0,.07);
		overflow: hidden;
	}
	.acui-export-card h3 {
		margin: 0;
		padding: 12px 16px;
		font-size: 13px;
		font-weight: 600;
		border-bottom: 1px solid #c3c4c7;
		background: #f6f7f7;
	}
	.acui-columns-modes label { margin-right: 16px; }
	.acui-columns-box { margin-top: 8px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; max-width: 640px; }
	.acui-columns-toolbar { display: flex; gap: 8px; align-items: center; padding: 8px; border-bottom: 1px solid #dcdcde; flex-wrap: wrap; }
	.acui-columns-toolbar input[type=search] { flex: 1; min-width: 160px; }
	.acui-columns-count { color: #646970; }
	.acui-columns-list { margin: 0; max-height: 360px; overflow-y: auto; }
	.acui-columns-list li { display: flex; align-items: center; gap: 8px; margin: 0; padding: 4px 8px; border-bottom: 1px solid #f0f0f1; }
	.acui-columns-list li.is-checked { background: #f6f7f7; }
	.acui-columns-list li.is-hidden { display: none; }
	.acui-columns-list .acui-col-handle { cursor: move; color: #8c8f94; width: 16px; text-align: center; }
	.acui-columns-list li:not(.is-checked) .acui-col-handle { visibility: hidden; }
	.acui-columns-list label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	.acui-columns-list .acui-col-label { width: 200px; }
	.acui-columns-list li:not(.is-checked) .acui-col-label { visibility: hidden; }
	.acui-columns-list .acui-col-placeholder { background: #f0f6fc; height: 34px; border: 1px dashed #72aee6; }
	.acui-columns-text { margin-top: 8px; }
	@media (max-width: 600px) { .acui-columns-list .acui-col-label { width: 110px; } }
	.acui-export-card-body {
		padding: 12px 16px;
		display: flex;
		flex-direction: column;
		gap: 8px;
	}
	@media (max-width: 960px) {
		.acui-export-layout { flex-direction: column; }
		.acui-export-sidebar { width: 100%; position: static; }
	}
	</style>

	<div class="acui-export-layout">
	<div class="acui-export-main">

	<div id="acui_export_results"></div>

	<form id="acui-export-form" class="acui_exporter">
		<table class="form-table">
			<tbody>
				<tr id="acui_role_wrapper" valign="top">
					<th scope="row"><?php _e( 'Role', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
                        <?php 
						ACUIHTML()->select( array(
                            'options' => ACUI_Helper::get_editable_roles( false ),
                            'name' => 'role[]',
                            'show_option_all' => false,
                            'show_option_none' => false,
							'multiple' => true,
							'selected' => is_array( $settings->get( 'role' ) ) ? $settings->get( 'role' ) : array( $settings->get( 'role' ) ),
							'style' => 'width:100%;'
                        )); ?>
					</td>
				</tr>
				<tr id="acui_users_wrapper" valign="top">
					<th scope="row"><?php _e( 'Specific users', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
						<select id="acui_export_users" name="users[]" multiple="multiple" style="width:100%;">
							<?php foreach( self::get_saved_users( $settings->get( 'users' ) ) as $user ): ?>
							<option value="<?php echo esc_attr( $user->ID ); ?>" selected="selected"><?php echo esc_html( self::user_label( $user ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="description"><?php _e( 'Search by email, username or name. If you choose users here, only these users are exported and the role and date filters are ignored. Leave it empty to export all the users that match the other filters.', 'import-users-from-csv-with-meta' ); ?></span>
					</td>
				</tr>
				<?php if( class_exists( 'WooCommerce' ) ): ?>
				<tr id="acui_include_guests_wrapper" valign="top">
					<th scope="row"><?php _e( 'WooCommerce guest customers', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
						<?php ACUIHTML()->checkbox( array( 'name' => 'include_guests', 'current' => 'yes', 'compare_value' => $settings->get( 'include_guests' ) ) ); ?>
						<span class="description"><?php _e( 'Also export the customers who bought without creating an account, one row per billing email, with the billing and shipping details of their last order. Emails that already belong to a registered user are skipped, and the date filter is applied to the date of their first order. You can import the file later to create an account for each of them.', 'import-users-from-csv-with-meta' ); ?></span>
					</td>
				</tr>
				<?php endif; ?>
				<tr id="acui_columns" valign="top">
					<th scope="row"><?php _e( 'Columns', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
						<div class="acui-columns-modes">
							<label><input type="radio" name="acui_columns_mode" value="all" <?php checked( '' === trim( (string) $settings->get( 'columns' ) ) ); ?>> <?php _e( 'All columns', 'import-users-from-csv-with-meta' ); ?></label>
							<label><input type="radio" name="acui_columns_mode" value="choose" <?php checked( '' !== trim( (string) $settings->get( 'columns' ) ) ); ?>> <?php _e( 'Choose columns', 'import-users-from-csv-with-meta' ); ?></label>
						</div>
						<div class="acui-columns-box" hidden>
							<div class="acui-columns-toolbar">
								<input type="search" class="acui-columns-search" placeholder="<?php esc_attr_e( 'Search columns…', 'import-users-from-csv-with-meta' ); ?>">
								<button type="button" class="button acui-columns-all"><?php _e( 'Select all', 'import-users-from-csv-with-meta' ); ?></button>
								<button type="button" class="button acui-columns-none"><?php _e( 'Select none', 'import-users-from-csv-with-meta' ); ?></button>
								<span class="acui-columns-count"></span>
							</div>
							<ul class="acui-columns-list">
								<?php foreach( self::get_available_columns() as $column ): ?>
								<li data-key="<?php echo esc_attr( $column ); ?>">
									<span class="acui-col-handle dashicons dashicons-menu" aria-hidden="true"></span>
									<label><input type="checkbox" class="acui-col-check"> <code><?php echo esc_html( $column ); ?></code></label>
									<input type="text" class="acui-col-label" placeholder="<?php esc_attr_e( 'Column name in the file', 'import-users-from-csv-with-meta' ); ?>">
								</li>
								<?php endforeach; ?>
							</ul>
						</div>
						<p class="description"><?php _e( 'Check the columns to export, drag them to change their order and, if you want, write the name the column will have in the file. Columns not listed here can be added with the text option below.', 'import-users-from-csv-with-meta' ); ?> <a href="#" class="acui-columns-text-toggle"><?php _e( 'Edit as text', 'import-users-from-csv-with-meta' ); ?></a></p>
						<div class="acui-columns-text" hidden>
							<?php ACUIHTML()->textarea( array( 'name' => 'columns', 'value' => $settings->get( 'columns' ), 'style' => 'width:100%;' ) ); ?>
							<span class="description">
								<?php _e( 'You can use this field to set which columns must be exported and in which order. If you leave it empty, all columns will be exported. Use a list of fields separated by commas, for example', 'import-users-from-csv-with-meta' ); ?>: user_email,first_name,last_name<br/>
								<?php _e( 'You can also name each column with a different name to the data following this method', 'import-users-from-csv-with-meta' ); ?>: user_email=>Email,first_name=>First name,last_name=>Last name<br/>
							</span>
						</div>
					</td>
				</tr>
				<tr id="acui_user_created_wrapper" valign="top">
					<th scope="row"><?php _e( 'User created', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
						<label for="from">from <?php ACUIHTML()->text( array( 'type' => 'date', 'name' => 'from', 'class' => '', 'value' => $settings->get( 'from' ) ) ); ?></label>
						<label for="to">to <?php ACUIHTML()->text( array( 'type' => 'date', 'name' => 'to', 'class' => '', 'value' => $settings->get( 'to' ) ) ); ?></label>
					</td>
				</tr>
				<tr id="acui_order_wrapper" valign="top">
					<th scope="row"><?php _e( 'Order', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
						<?php ACUIHTML()->select( array(
							'options' => array(
								'' => __( 'Username', 'import-users-from-csv-with-meta' ),
								'ID' => __( 'User ID', 'import-users-from-csv-with-meta' ),
								'user_email' => __( 'Email', 'import-users-from-csv-with-meta' ),
								'display_name' => __( 'Display name', 'import-users-from-csv-with-meta' ),
								'user_registered' => __( 'Registration date', 'import-users-from-csv-with-meta' ),
							),
							'name' => 'orderby',
							'show_option_all' => false,
							'show_option_none' => false,
							'selected' => $settings->get( 'orderby' ),
						) ); ?>
						<?php ACUIHTML()->select( array(
							'options' => array(
								'ASC' => __( 'Ascending', 'import-users-from-csv-with-meta' ),
								'DESC' => __( 'Descending', 'import-users-from-csv-with-meta' ),
							),
							'name' => 'order',
							'show_option_all' => false,
							'show_option_none' => false,
							'selected' => $settings->get( 'order' ),
						) ); ?>
						<span class="description"><?php _e( 'Order of the registered users in the file. Guest customers always go after them, sorted by the date of their first order.', 'import-users-from-csv-with-meta' ); ?></span>
					</td>
				</tr>
				<tr id="acui_rows_wrapper" valign="top">
					<th scope="row"><?php _e( 'Rows', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
						<label for="offset_rows"><?php _e( 'Skip the first', 'import-users-from-csv-with-meta' ); ?> <?php ACUIHTML()->text( array( 'type' => 'number', 'name' => 'offset_rows', 'class' => 'small-text', 'value' => $settings->get( 'offset_rows' ), 'placeholder' => '0', 'attributes' => array( 'min' => 0 ) ) ); ?></label>
						<label for="max_rows"><?php _e( 'and export at most', 'import-users-from-csv-with-meta' ); ?> <?php ACUIHTML()->text( array( 'type' => 'number', 'name' => 'max_rows', 'class' => 'small-text', 'value' => $settings->get( 'max_rows' ), 'attributes' => array( 'min' => 0 ) ) ); ?> <?php _e( 'rows', 'import-users-from-csv-with-meta' ); ?></label>
						<span class="description"><?php _e( 'Leave them empty to export all the rows. Useful to split a big export into several files: for example, skip 0 and export 5000, then skip 5000 and export 5000, and so on.', 'import-users-from-csv-with-meta' ); ?></span>
					</td>
				</tr>
				<tr id="acui_format_wrapper" valign="top">
					<th scope="row"><label for="format"><?php _e( 'File format', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<?php $acui_formats = ACUI_Batch_Exporter::get_formats(); ?>
						<?php ACUIHTML()->select( array(
							'options' => $acui_formats,
							'name' => 'format',
							'show_option_all' => false,
							'show_option_none' => false,
							'selected' => array_key_exists( $settings->get( 'format' ), $acui_formats ) ? $settings->get( 'format' ) : 'csv',
						) ); ?>
						<?php if( count( $acui_formats ) < 2 ): ?>
						<span class="description"><?php printf( __( 'You can also export to <strong>Excel (XLSX), OpenDocument (ODS) or XML</strong> with the <a href="%s" target="_blank">File Formats addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( ACUIHelper()->premium_url( 'file-formats-addon', 'export-format-field' ) ) ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr id="acui_delimiter_wrapper" valign="top">
					<th scope="row"><?php _e( 'Delimiter', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
                        <?php ACUIHTML()->select( array(
                            'options' => ACUI_Helper::get_csv_delimiters_titles(),
                            'name' => 'delimiter',
                            'show_option_all' => false,
                            'show_option_none' => false,
							'selected' => $settings->get( 'delimiter' )
                        )); ?>
					</td>
				</tr>
				<tr id="acui_timestamp_wrapper" valign="top">
					<th scope="row"><?php _e( 'Convert timestamp data to date format', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
                        <?php ACUIHTML()->checkbox( array( 'name' => 'convert_timestamp', 'current' => 'yes', 'compare_value' => $settings->get( 'convert_timestamp' ) ) ); ?>
						<?php ACUIHTML()->text( array( 'name' => 'datetime_format', 'value' => 'Y-m-d H:i:s', 'class' => '', 'value' => $settings->get( 'datetime_format' ) ) ); ?>
                        <span class="description"><a href="https://www.php.net/manual/en/datetime.formats.php"><?php _e( 'accepted formats', 'import-users-from-csv-with-meta' ); ?></a> <?php _e( 'If you have problems and you get some value exported as a date that should not be converted to a date, please deactivate this option. If this option is not activated, datetime format will be ignored.', 'import-users-from-csv-with-meta' ); ?></span>
					</td>
				</tr>
				<tr id="acui_order_fields_alphabetically_wrapper" valign="top">
					<th scope="row"><?php _e( 'Order fields alphabetically', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
                        <?php ACUIHTML()->checkbox( array( 'name' => 'order_fields_alphabetically', 'current' => 'yes', 'compare_value' => $settings->get( 'order_fields_alphabetically' ) ) ); ?>
						<span class="description"><?php _e( "Order all columns alphabetically to check your data more easily.", 'import-users-from-csv-with-meta' ); ?></span>
					</td>
				</tr>
                <tr id="acui_order_fields_double_encapsulate_serialized_values" valign="top">
					<th scope="row"><?php _e( 'Double encapsulate serialized values', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
                        <?php ACUIHTML()->checkbox( array( 'name' => 'double_encapsulate_serialized_values', 'current' => 'yes', 'compare_value' => $settings->get( 'double_encapsulate_serialized_values' ) ) ); ?>                    
						<span class="description"><?php _e( "Serialized values can sometimes have problems being displayed in Microsoft Excel or LibreOffice, we can double encapsulate this kind of data, but you would not be able to import this data because instead of serialized data it would be managed as strings", 'import-users-from-csv-with-meta' ); ?></span>
					</td>
				</tr>
				<tr id="acui_order_fields_display_arrays_as_comma_separated_list_of_values" valign="top">
					<th scope="row"><?php _e( 'Display serialized arrays as comma-separated lists of values', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
                        <?php ACUIHTML()->checkbox( array( 'name' => 'display_arrays_as_comma_separated_list_of_values', 'current' => 'yes', 'compare_value' => $settings->get( 'display_arrays_as_comma_separated_list_of_values' ) ) ); ?>                    
						<span class="description"><?php _e( "This data cannot then be imported back into the database as an array if the exported file is imported.", 'import-users-from-csv-with-meta' ); ?></span>
					</td>
				</tr>
				<tr id="acui_export_password_hashes_wrapper" valign="top">
					<th scope="row"><?php _e( 'Export password hashes', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
                        <?php ACUIHTML()->checkbox( array( 'name' => 'export_password_hashes', 'current' => 'yes', 'compare_value' => $settings->get( 'export_password_hashes' ) ) ); ?>
						<span class="description"><?php _e( "Include the user_pass column with the hashed passwords, so users can keep their passwords if you import the file in another site. Uncheck it if you do not need it: the file will not contain any password information, even if user_pass is included in the Columns field.", 'import-users-from-csv-with-meta' ); ?></span>
					</td>
				</tr>
			</tbody>
		</table>

		<?php wp_nonce_field( 'codection-security', 'security' ); ?>

        <div class="user-exporter-progress-wrapper">
            <progress class="user-exporter-progress" value="0" max="100"></progress>
            <span class="user-exporter-progress-value">0%</span>
        </div>
	</form>
	</div><!-- .acui-export-main -->

	<div class="acui-export-sidebar">
		<div class="acui-export-card">
			<h3><?php _e( 'Actions', 'import-users-from-csv-with-meta' ); ?></h3>
			<div class="acui-export-card-body">
				<button type="submit" form="acui-export-form" class="button button-primary" style="width:100%;">
					<?php _e( 'Download', 'import-users-from-csv-with-meta' ); ?>
				</button>
				<button type="button" id="save-without-exporting" class="button button-secondary" style="width:100%;">
					<?php _e( 'Save Settings', 'import-users-from-csv-with-meta' ); ?>
				</button>
			</div>
		</div>
	</div><!-- .acui-export-sidebar -->

	</div><!-- .acui-export-layout -->

	<script type="text/javascript">
	jQuery( document ).ready( function( $ ){
		$( "input[name='from']" ).change( function() {
			$( "input[name='to']" ).attr( 'min', $( this ).val() );
		})

		$( '#role' ).select2();

		$( '#acui_export_users' ).select2( {
			minimumInputLength: 2,
			placeholder: '<?php echo esc_js( __( 'All users', 'import-users-from-csv-with-meta' ) ); ?>',
			ajax: {
				url: ajaxurl,
				dataType: 'json',
				delay: 250,
				data: function( params ){
					return { action: 'acui_export_search_users', q: params.term, security: '<?php echo wp_create_nonce( 'acui-export-search-users' ); ?>' };
				},
				processResults: function( response ){
					return { results: response.success ? response.data : [] };
				}
			}
		} );

		$( '#convert_timestamp' ).on( 'click', function() {
			if( $('#convert_timestamp').is(':checked') ){
				$( '#datetime_format' ).prop( 'disabled', false );
			} else {
				$( '#datetime_format' ).prop( 'disabled', true );
			}
		});

		$( '#save-without-exporting' ).click( function(){
			var data = {
				'action': 'acui_export_save_settings',
				'settings': $( '.acui_exporter' ).serialize(),
				'security': '<?php echo wp_create_nonce( "codection-security" ); ?>'
			};

			$.post( ajaxurl, data, function( response ) {
				alert( response.data.message );
			} );
		} );
	} )
	</script>
	<?php
	}

    function download_export_file() {
		if( current_user_can( apply_filters( 'acui_export_capability', 'edit_users' ) ) && isset( $_GET['action'], $_GET['nonce'] ) && wp_verify_nonce( wp_unslash( $_GET['nonce'] ), 'codection-security' ) && 'download_user_csv' === wp_unslash( $_GET['action'] ) ) {
            $exporter = new ACUI_Batch_Exporter();

			if ( !empty( $_GET['filename'] ) ){
				$exporter->set_filename( wp_unslash( $_GET['filename'] ) );
			}

			$exporter->set_format( isset( $_GET['format'] ) ? wp_unslash( $_GET['format'] ) : 'csv' );

			$exporter->export();
		}
	}

	function get_results(){
		$bad_character_formulas_values_cleaned = get_transient( 'acui_export_bad_character_formulas_values_cleaned' );
		delete_transient( 'acui_export_bad_character_formulas_values_cleaned' );
		
		if( empty( $bad_character_formulas_values_cleaned ) ){
			return '';
		}
		
		$results = array();

		foreach( $bad_character_formulas_values_cleaned as $info ){
			$results[] = sprintf( __( 'User with id: %s. The cell in column %s has been edited because its content may contain formulas that are automatically run in certain spreadsheet apps. The new value is: %s', 'import-users-from-csv-with-meta' ), esc_html( $info['user_id'] ), esc_html( $info['key'] ), esc_html( $info['value'] ) );
		}

		$ret = '<h3>' . __( 'Export results','import-users-from-csv-with-meta' ) . '</h3>';
		$ret .= '<h4>' . __( 'Some values have been altered','import-users-from-csv-with-meta' ) . '</h4>';
		$ret .= '<ul>';
		foreach( $results as $result ){
			$ret .= '<li>' . $result . '</li>';
		}

		$ret .= '</ul>';

		return $ret;
	}

    function export_users_csv(){
        check_ajax_referer( 'codection-security', 'security' );

		if( !current_user_can( apply_filters( 'acui_export_capability', 'edit_users' ) ) )
			wp_die( __( 'Only users who are allowed to list users can export them.', 'import-users-from-csv-with-meta' ) );
    
        $step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;
		        
        $exporter = new ACUI_Batch_Exporter();

		if( isset( $_POST['role'] ) ){
			if( !is_array( $_POST['role'] ) )
				$_POST['role'] = explode( ',', $_POST['role'] );

			if( !array_filter( $_POST['role'] ) )
				unset( $_POST['role'] );
		}

		$role = isset($_POST['role']) ? $_POST['role'] : array();
		if( !is_array( $role ) ){
			$role = array( $role );
		}

		$exporter->set_role( isset( $_POST['role'] ) ? array_map( 'sanitize_text_field', $role ) : '' );
        $exporter->set_from( isset( $_POST['from'] ) ? sanitize_text_field( $_POST['from'] ) : '' );
        $exporter->set_to( isset( $_POST['to'] ) ? sanitize_text_field( $_POST['to'] ) : '' );
		$exporter->set_users( isset( $_POST['users'] ) ? wp_unslash( $_POST['users'] ) : array() );
		$exporter->set_include_guests( isset( $_POST['include_guests'] ) ? $_POST['include_guests'] : false );
		$exporter->set_offset_rows( isset( $_POST['offset_rows'] ) ? absint( $_POST['offset_rows'] ) : 0 );
		$exporter->set_max_rows( isset( $_POST['max_rows'] ) ? absint( $_POST['max_rows'] ) : 0 );
		$exporter->fill_total_rows( $step == 1 );	

		if( $step == 1 ){
			delete_transient( 'acui_export_bad_character_formulas_values_cleaned' );
			$this->save_settings();
		}

		if( isset( $_POST['filename'] ) && !empty( $_POST['filename'] ) )
			$exporter->set_filename( sanitize_file_name( $_POST['filename'] ) );

		$exporter->set_page( $step );
		$exporter->set_format( isset( $_POST['format'] ) ? wp_unslash( $_POST['format'] ) : 'csv' );
		$exporter->set_delimiter( ( 'csv' === $exporter->get_format() && isset( $_POST['delimiter'] ) ) ? sanitize_text_field( $_POST['delimiter'] ) : '' );
        $exporter->set_convert_timestamp( isset( $_POST['convert_timestamp'] ) ? $_POST['convert_timestamp'] : '' );
        $exporter->set_datetime_format( isset( $_POST['datetime_format'] ) ? sanitize_text_field( $_POST['datetime_format'] ) : '' );
        $exporter->set_order_fields_alphabetically( isset( $_POST['order_fields_alphabetically'] ) ? $_POST['order_fields_alphabetically'] : '' );
        $exporter->set_double_encapsulate_serialized_values( isset( $_POST['double_encapsulate_serialized_values'] ) ? $_POST['double_encapsulate_serialized_values'] : '' );
		$exporter->set_display_arrays_as_comma_separated_list_of_values( isset( $_POST['display_arrays_as_comma_separated_list_of_values'] ) ? $_POST['display_arrays_as_comma_separated_list_of_values'] : '' );
		$exporter->set_export_password_hashes( isset( $_POST['export_password_hashes'] ) ? $_POST['export_password_hashes'] : true );
        $exporter->set_filtered_columns( ( isset( $_POST['columns'] ) && !empty( $_POST['columns'] ) ) ? $_POST['columns'] : array() );
        $exporter->set_orderby( ( isset( $_POST['orderby'] ) && !empty( $_POST['orderby'] ) ) ? sanitize_text_field( $_POST['orderby'] ) : '' );
        $exporter->set_order( ( isset( $_POST['order'] ) && !empty( $_POST['order'] ) ) ? sanitize_text_field( $_POST['order'] ) : 'ASC' );
        $exporter->load_columns();
    
        $exporter->generate_file();

        if ( 100 <= $exporter->get_percent_complete() ) {
			$this->add_to_history( $exporter );

            $query_args = array(
                'nonce' => wp_create_nonce( 'codection-security' ),
                'action' => 'download_user_csv',
                'filename' => $exporter->get_filename(),
                'format' => $exporter->get_format(),
            );

			$current_url = isset( $_POST['current_url'] ) ? sanitize_text_field( $_POST['current_url'] ) : admin_url( 'tools.php?page=acui&tab=export' );

            wp_send_json_success(
                array(
                    'step' => 'done',
                    'percentage' => 100,
                    'url' => add_query_arg( $query_args, $current_url ),
					'results' => $this->get_results(),
					'filename' => $exporter->get_filename()
                )
            );
        } else {
            wp_send_json_success(
                array(
                    'step' => ++$step,
                    'total_steps' => $exporter->get_total_steps(),
                    'percentage' => $exporter->get_percent_complete(),
					'filename' => $exporter->get_filename()
                )
            );
        }
    }

	static function get_available_columns(){
		$exporter = new ACUI_Batch_Exporter();
		$columns = array( 'user_login', 'user_email', 'source_user_id', 'user_pass', 'user_nicename', 'user_url', 'user_registered', 'display_name', 'role' );
		$columns = array_merge( $columns, $exporter->get_user_meta_keys() );

		if( class_exists( 'WooCommerce' ) )
			$columns = array_merge( $columns, $exporter->get_guest_keys() );

		return apply_filters( 'acui_export_available_columns', array_values( array_unique( array_filter( array_map( 'strval', $columns ), 'strlen' ) ) ) );
	}

	function add_to_history( $exporter ){
		$details = array();
		$role = $exporter->get_role();

		if( !empty( $exporter->get_users_filter() ) )
			$details[ __( 'Specific users', 'import-users-from-csv-with-meta' ) ] = number_format_i18n( count( $exporter->get_users_filter() ) );
		elseif( !empty( $role ) )
			$details[ __( 'Role', 'import-users-from-csv-with-meta' ) ] = implode( ', ', (array) $role );

		if( $exporter->get_from() || $exporter->get_to() )
			$details[ __( 'User created', 'import-users-from-csv-with-meta' ) ] = trim( $exporter->get_from() . ' - ' . $exporter->get_to(), ' -' );

		if( $exporter->get_include_guests() )
			$details[ __( 'WooCommerce guest customers', 'import-users-from-csv-with-meta' ) ] = __( 'Included', 'import-users-from-csv-with-meta' );

		if( $exporter->get_offset_rows() || $exporter->get_max_rows() )
			$details[ __( 'Rows', 'import-users-from-csv-with-meta' ) ] = sprintf( __( 'skip %1$s, at most %2$s', 'import-users-from-csv-with-meta' ), number_format_i18n( $exporter->get_offset_rows() ), $exporter->get_max_rows() ? number_format_i18n( $exporter->get_max_rows() ) : '∞' );

		$formats = ACUI_Batch_Exporter::get_formats();
		$details[ __( 'Format', 'import-users-from-csv-with-meta' ) ] = $formats[ $exporter->get_format() ];

		$columns = $exporter->get_filtered_columns();
		$details[ __( 'Columns', 'import-users-from-csv-with-meta' ) ] = empty( $columns ) ? __( 'All', 'import-users-from-csv-with-meta' ) : implode( ', ', $columns );

		ACUI_History::add( array(
			'type' => 'export',
			'source' => ( isset( $_POST['form'] ) && false !== strpos( (string) $_POST['form'], 'acui_frontend_export=1' ) ) ? 'frontend' : 'manual',
			'file' => $exporter->get_download_filename(),
			'rows' => $exporter->get_total_rows(),
			'details' => $details,
		) );
	}

	static function user_label( $user ){
		return sprintf( '%s (%s)', $user->user_login, $user->user_email );
	}

	static function get_saved_users( $ids ){
		if( empty( $ids ) )
			return array();

		if( !is_array( $ids ) )
			$ids = explode( ',', $ids );

		$ids = array_filter( array_map( 'absint', $ids ) );

		return empty( $ids ) ? array() : get_users( array( 'include' => $ids, 'orderby' => 'include' ) );
	}

	function ajax_search_users(){
		check_ajax_referer( 'acui-export-search-users', 'security' );

		if( !current_user_can( apply_filters( 'acui_export_capability', 'edit_users' ) ) )
			wp_send_json_error();

		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		if( strlen( $term ) < 2 )
			wp_send_json_success( array() );

		$users = get_users( array(
			'search' => '*' . $term . '*',
			'search_columns' => array( 'user_login', 'user_email', 'user_nicename', 'display_name' ),
			'number' => 20,
			'orderby' => 'user_login',
		) );

		$results = array();
		foreach( $users as $user ){
			$results[] = array( 'id' => $user->ID, 'text' => self::user_label( $user ) );
		}

		wp_send_json_success( $results );
	}

	function ajax_save_settings(){
		check_ajax_referer( 'codection-security', 'security' );

		if( !current_user_can( apply_filters( 'acui_export_capability', 'edit_users' ) ) )
			wp_die( __( 'Only users who are allowed to list users can save settings about exporting them.', 'import-users-from-csv-with-meta' ) );

		$this->save_settings();

		wp_send_json_success( array( 'message' => __( 'Settings saved', 'import-users-from-csv-with-meta' ), ) );
	}

	function save_settings(){
		$settings = array();

		isset( $_POST['settings'] ) ? parse_str( $_POST['settings'], $settings) : parse_str( $_POST['form'], $settings );
		
		ACUISettings()->save_multiple( 'export_backend', $settings );
	}
}
global $acui_exporter;
$acui_exporter = new ACUI_Exporter();