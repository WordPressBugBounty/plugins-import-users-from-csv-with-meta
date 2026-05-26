<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ACUI_Exclude_From_Export {
	const META_KEY = '_acui_exclude_from_export';

	function __construct() {
		add_action( 'show_user_profile',        array( $this, 'render_field' ) );
		add_action( 'edit_user_profile',        array( $this, 'render_field' ) );
		add_action( 'personal_options_update',  array( $this, 'save_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_field' ) );
	}

	function render_field( $user ) {
		if ( ! current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
			return;

		$excluded = (bool) get_user_meta( $user->ID, self::META_KEY, true );
		?>
		<h2><?php esc_html_e( 'Import and export users', 'import-users-from-csv-with-meta' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="acui_exclude_from_export"><?php esc_html_e( 'Exclude from exports', 'import-users-from-csv-with-meta' ); ?></label></th>
				<td>
					<input type="checkbox" name="acui_exclude_from_export" id="acui_exclude_from_export" value="1" <?php checked( $excluded ); ?> />
					<span class="description"><?php esc_html_e( 'If checked, this user will never appear in any export regardless of filters applied.', 'import-users-from-csv-with-meta' ); ?></span>
				</td>
			</tr>
		</table>
		<?php
	}

	function save_field( $user_id ) {
		if ( ! current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
			return;

		if ( ! empty( $_POST['acui_exclude_from_export'] ) )
			update_user_meta( $user_id, self::META_KEY, 1 );
		else
			delete_user_meta( $user_id, self::META_KEY );
	}
}

new ACUI_Exclude_From_Export();
