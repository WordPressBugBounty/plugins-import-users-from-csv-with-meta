<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if( !function_exists( 'UM' ) || defined( 'ACUI_UM_VERSION' ) ){
	return;
}

class ACUI_Ultimate_Member{
	const DISMISS_META = 'acui_dismissed_ultimate_member_card';
	const COLUMNS_TRANSIENT = 'acui_um_columns_';

	private $fields = null;
	private $checked_headers = false;

	function __construct(){
		add_action( 'acui_homepage_start', array( $this, 'card_import' ) );
		add_action( 'acui_export_start', array( $this, 'card_export' ) );
		add_action( 'acui_homepage_sidebar_info', array( $this, 'sidebar' ) );
		add_action( 'acui_documentation_after_plugins_activated', array( $this, 'documentation' ) );
		add_action( 'admin_post_acui_dismiss_ultimate_member_card', array( $this, 'dismiss' ) );
		add_filter( 'pre_acui_import_single_user_single_data', array( $this, 'radio_as_array' ), 10, 2 );
		add_action( 'acui_post_import_single_user', array( $this, 'remember_columns' ), 10, 1 );
		add_filter( 'acui_import_result_addon_notice', array( $this, 'import_result_notice' ), 10, 1 );
	}

	function fields(){
		if( $this->fields !== null )
			return $this->fields;

		$this->fields = array();

		if( !method_exists( UM()->builtin(), 'all_user_fields' ) )
			return $this->fields;

		foreach( (array) UM()->builtin()->all_user_fields( null, true ) as $key => $field ){
			if( !is_array( $field ) || empty( $field['type'] ) )
				continue;

			$metakey = !empty( $field['metakey'] ) ? $field['metakey'] : $key;
			$this->fields[ $metakey ] = array( 'type' => $field['type'], 'title' => !empty( $field['title'] ) ? wp_strip_all_tags( $field['title'] ) : $metakey );
		}

		return $this->fields;
	}

	function radio_as_array( $value, $header ){
		if( !is_string( $value ) || $value === '' )
			return $value;

		$fields = $this->fields();

		if( isset( $fields[ $header ] ) && $fields[ $header ]['type'] === 'radio' && !in_array( $header, array( 'role_radio', 'role' ), true ) )
			return array( $value );

		return $value;
	}

	function remember_columns( $headers ){
		if( $this->checked_headers )
			return;

		$this->checked_headers = true;
		$fields = $this->fields();
		$columns = array();

		foreach( (array) $headers as $header ){
			$header = trim( (string) $header );
			$lower = strtolower( $header );

			if( in_array( $lower, array( 'profile_photo', 'cover_photo', 'um_profile_photo', 'um_cover_photo' ), true ) ){
				$columns[ $header ] = $header;
				continue;
			}

			if( isset( $fields[ $header ] ) && in_array( $fields[ $header ]['type'], array( 'image', 'file', 'checkbox', 'multiselect', 'date', 'time' ), true ) )
				$columns[ $header ] = $fields[ $header ]['title'];
		}

		if( empty( $columns ) )
			return;

		$key = self::COLUMNS_TRANSIENT . get_current_user_id();
		$saved = get_transient( $key );
		set_transient( $key, array_merge( is_array( $saved ) ? $saved : array(), $columns ), HOUR_IN_SECONDS );
	}

	function import_result_notice( $notice ){
		$key = self::COLUMNS_TRANSIENT . get_current_user_id();
		$columns = get_transient( $key );
		delete_transient( $key );

		if( empty( $columns ) || !is_array( $columns ) )
			return $notice;

		$names = implode( ', ', array_map( function( $name ){ return '<code>' . esc_html( $name ) . '</code>'; }, array_slice( array_keys( $columns ), 0, 6 ) ) );

		return $notice . '<div class="notice notice-warning inline acui-addon-notice" style="margin:0 0 16px;"><p>'
			. sprintf( __( 'Ultimate Member saves photos, files, checkboxes and dates in its own way, and this import has saved the columns %s as plain text, so Ultimate Member may not show them.', 'import-users-from-csv-with-meta' ), $names ) . ' '
			. sprintf( __( 'The <a href="%s" target="_blank">Ultimate Member addon</a> imports them the way Ultimate Member does: photos with their thumbnails, files in the folder of each member and every field converted to its type.', 'import-users-from-csv-with-meta' ), esc_url( ACUIHelper()->premium_url( 'ultimate-member-addon', 'import-result-ultimate-member' ) ) )
			. '</p></div>';
	}

	static function members_out_of_directory(){
		global $wpdb;

		$count = get_transient( 'acui_um_out_of_directory' );

		if( $count !== false )
			return (int) $count;

		$sql = "SELECT COUNT(*) FROM {$wpdb->users} u WHERE ( NOT EXISTS ( SELECT 1 FROM {$wpdb->usermeta} d WHERE d.user_id = u.ID AND d.meta_key = 'um_member_directory_data' ) OR NOT EXISTS ( SELECT 1 FROM {$wpdb->usermeta} s WHERE s.user_id = u.ID AND s.meta_key = 'account_status' ) )";

		if( is_multisite() )
			$sql .= $wpdb->prepare( " AND EXISTS ( SELECT 1 FROM {$wpdb->usermeta} m WHERE m.user_id = u.ID AND m.meta_key = %s )", $wpdb->get_blog_prefix() . 'capabilities' );

		$count = (int) $wpdb->get_var( $sql );
		set_transient( 'acui_um_out_of_directory', $count, HOUR_IN_SECONDS );

		return $count;
	}

	function dismiss(){
		check_admin_referer( 'acui_dismiss_ultimate_member_card' );

		if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
			wp_die( __( 'You are not allowed to do this.', 'import-users-from-csv-with-meta' ) );

		update_user_meta( get_current_user_id(), self::DISMISS_META, time() );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'tools.php?page=acui' ) );
		exit;
	}

	function card_import(){
		$this->card( 'homepage-ultimate-member-card', __( 'Using Ultimate Member? Import complete profiles, photos included', 'import-users-from-csv-with-meta' ) );
	}

	function card_export(){
		$this->card( 'export-ultimate-member-card', __( 'Using Ultimate Member? Export profiles ready to import on another site', 'import-users-from-csv-with-meta' ) );
	}

	function card( $campaign, $title ){
		if( get_user_meta( get_current_user_id(), self::DISMISS_META, true ) )
			return;

		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=acui_dismiss_ultimate_member_card' ), 'acui_dismiss_ultimate_member_card' );
		$missing = self::members_out_of_directory();
		$features = array(
			__( '<strong>Every field of your forms</strong> converted to its type: dropdowns, checkboxes, dates…', 'import-users-from-csv-with-meta' ),
			__( '<strong>Profile and cover photos</strong> with the thumbnails of Ultimate Member', 'import-users-from-csv-with-meta' ),
			__( '<strong>Image and file fields</strong>: logos, documents and CVs from a URL', 'import-users-from-csv-with-meta' ),
			__( '<strong>Account status</strong> with or without emails, privacy and directory visibility', 'import-users-from-csv-with-meta' ),
		);
		?>
		<style>
		.acui-um-promo{ position:relative; display:flex; gap:18px; align-items:flex-start; background:#fff; border:1px solid #c3c4c7; border-left:4px solid #2b7fb8; border-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,.07); padding:18px 44px 18px 18px; margin:16px 0 20px; }
		.acui-um-promo-icon{ flex-shrink:0; width:48px; height:48px; border-radius:50%; background:#e7f2fa; color:#2b7fb8; display:flex; align-items:center; justify-content:center; }
		.acui-um-promo-icon .dashicons{ font-size:28px; width:28px; height:28px; }
		.acui-um-promo-body{ flex:1; min-width:0; }
		.acui-um-promo-body h3{ margin:0 0 4px; font-size:15px; }
		.acui-um-promo-body p{ margin:0 0 10px; color:#50575e; }
		.acui-um-promo-alert{ display:inline-block; background:#fcf0f1; color:#8a2424; border-radius:3px; padding:4px 8px; }
		.acui-um-promo-features{ display:grid; grid-template-columns:repeat( 2, minmax( 0, 1fr ) ); gap:4px 20px; margin:0 0 12px; }
		.acui-um-promo-features li{ margin:0; padding-left:22px; position:relative; }
		.acui-um-promo-features li .dashicons{ position:absolute; left:0; top:1px; color:#00a32a; font-size:18px; width:18px; height:18px; }
		.acui-um-promo-example{ display:block; background:#f6f7f7; border:1px solid #dcdcde; border-radius:3px; padding:6px 10px; margin:0 0 12px; font-size:12px; overflow-x:auto; white-space:nowrap; }
		.acui-um-promo-actions{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
		.acui-um-promo-actions .button-primary{ background:#2b7fb8; border-color:#2b7fb8; }
		.acui-um-promo-actions .button-primary:hover,
		.acui-um-promo-actions .button-primary:focus{ background:#226799; border-color:#226799; }
		.acui-um-promo-dismiss{ position:absolute; top:10px; right:10px; color:#787c82; text-decoration:none; }
		.acui-um-promo-dismiss:hover{ color:#d63638; }
		@media (max-width:782px){ .acui-um-promo{ flex-direction:column; } .acui-um-promo-features{ grid-template-columns:1fr; } }
		</style>
		<div class="acui-um-promo">
			<a class="acui-um-promo-dismiss" href="<?php echo esc_url( $dismiss_url ); ?>" title="<?php esc_attr_e( 'Do not show again', 'import-users-from-csv-with-meta' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text"><?php _e( 'Do not show again', 'import-users-from-csv-with-meta' ); ?></span></a>
			<div class="acui-um-promo-icon"><span class="dashicons dashicons-id-alt" aria-hidden="true"></span></div>
			<div class="acui-um-promo-body">
				<h3><?php echo esc_html( $title ); ?></h3>
				<?php if( $missing > 0 ): ?>
				<p class="acui-um-promo-alert"><?php printf( esc_html( _n( '%s member of this site does not appear in the Ultimate Member directories because it has no account status. The addon fixes it with one click.', '%s members of this site do not appear in the Ultimate Member directories because they have no account status. The addon fixes it with one click.', $missing, 'import-users-from-csv-with-meta' ) ), number_format_i18n( $missing ) ); ?></p>
				<?php endif; ?>
				<p><?php _e( 'Ultimate Member saves photos, files and many fields in its own way. The <strong>Ultimate Member addon</strong> imports them the way it does, from any CSV or Excel file:', 'import-users-from-csv-with-meta' ); ?></p>
				<ul class="acui-um-promo-features">
					<?php foreach( $features as $feature ): ?>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php echo wp_kses( $feature, array( 'strong' => array() ) ); ?></li>
					<?php endforeach; ?>
				</ul>
				<code class="acui-um-promo-example">Interests: Music | Travel &nbsp; · &nbsp; Profile photo: https://example.com/photos/emma.jpg &nbsp; · &nbsp; Status: approved</code>
				<div class="acui-um-promo-actions">
					<a class="button button-primary" href="<?php echo esc_url( ACUIHelper()->premium_url( 'ultimate-member-addon', $campaign ) ); ?>" target="_blank"><?php _e( 'Get the Ultimate Member addon', 'import-users-from-csv-with-meta' ); ?></a>
					<span class="description"><?php _e( 'Checks every row before importing and sends no emails to your members.', 'import-users-from-csv-with-meta' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	function sidebar(){
		?>
		<p><span class="dashicons dashicons-id-alt" style="color:#2b7fb8;" aria-hidden="true"></span> <?php printf( __( 'Using <strong>Ultimate Member</strong>? Import complete profiles with photos, files and every form field with the <a href="%s" target="_blank">Ultimate Member addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( ACUIHelper()->premium_url( 'ultimate-member-addon', 'homepage-sidebar-ultimate-member' ) ) ); ?></p>
		<?php
	}

	function documentation(){
		?>
		<tr valign="top">
			<th scope="row"><?php _e( 'Ultimate Member is activated', 'import-users-from-csv-with-meta' ); ?></th>
			<td>
				<?php _e( 'Text fields of your Ultimate Member forms can be imported using their meta key as the column header.', 'import-users-from-csv-with-meta' ); ?>
				<p><?php printf( __( 'Profile photos, cover photos, image and file fields, checkboxes, multi selects, dates and the account status need to be saved the way Ultimate Member does. To import them, use the <a href="%s" target="_blank">Ultimate Member addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( ACUIHelper()->premium_url( 'ultimate-member-addon', 'documentation-ultimate-member' ) ) ); ?></p>
			</td>
		</tr>
		<?php
	}
}

new ACUI_Ultimate_Member();
