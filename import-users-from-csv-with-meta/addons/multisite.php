<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if( !is_multisite() )
	return;

class ACUI_Multisite_Promo{
	const DISMISS_META = 'acui_dismissed_multisite_card';
	const SLUG = 'multisite-addon';

	function __construct(){
		add_action( 'acui_homepage_start', array( $this, 'card_import' ) );
		add_action( 'acui_export_start', array( $this, 'card_export' ) );
		add_action( 'acui_homepage_sidebar_info', array( $this, 'sidebar' ) );
		add_action( 'acui_documentation_after_plugins_activated', array( $this, 'documentation' ), 20 );
		add_filter( 'acui_import_result_addon_notice', array( $this, 'import_notice' ), 10, 3 );
		add_action( 'admin_post_acui_dismiss_multisite_card', array( $this, 'dismiss' ) );
	}

	static function addon_active(){
		return defined( 'ACUI_MS_VERSION' );
	}

	static function can_buy(){
		return is_super_admin();
	}

	static function show(){
		return !self::addon_active() && self::can_buy() && !get_user_meta( get_current_user_id(), self::DISMISS_META, true );
	}

	static function url( $campaign ){
		return ACUIHelper()->premium_url( self::SLUG, $campaign );
	}

	static function shared_columns(){
		$columns = get_option( 'acui_columns', array() );
		$shared = array();

		if( !is_array( $columns ) )
			return $shared;

		global $wpdb;

		foreach( $columns as $column ){
			$column = (string) $column;

			if( $column === '' || strpos( $column, $wpdb->base_prefix ) === 0 || in_array( strtolower( $column ), array( 'password', 'user_pass', 'role', 'blogs' ), true ) || in_array( strtolower( $column ), ACUIHelper()->get_wp_users_fields(), true ) )
				continue;

			$shared[] = $column;
		}

		return $shared;
	}

	function dismiss(){
		check_admin_referer( 'acui_dismiss_multisite_card' );

		if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
			wp_die( __( 'You are not allowed to do this.', 'import-users-from-csv-with-meta' ) );

		update_user_meta( get_current_user_id(), self::DISMISS_META, time() );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'tools.php?page=acui' ) );
		exit;
	}

	function card_import(){
		$shared = self::shared_columns();
		$alert = '';

		if( !empty( $shared ) ){
			$names = array_slice( $shared, 0, 4 );
			$alert = sprintf(
				_n( 'In your last import, %1$s column (%2$s) was saved for the whole network: every site of the network sees and overwrites the same value.', 'In your last import, %1$s columns (%2$s) were saved for the whole network: every site of the network sees and overwrites the same value.', count( $shared ), 'import-users-from-csv-with-meta' ),
				'<strong>' . number_format_i18n( count( $shared ) ) . '</strong>',
				esc_html( implode( ', ', $names ) . ( count( $shared ) > 4 ? '…' : '' ) )
			);
		}

		$this->card(
			'homepage-multisite-card',
			__( 'Importing users into a Multisite network?', 'import-users-from-csv-with-meta' ),
			__( 'The <strong>Multisite addon</strong> lets each site keep its own data and gives each user the right role on each site, from the same CSV:', 'import-users-from-csv-with-meta' ),
			$alert,
			'corecrew@site · team@3 · blogs = 2:editor,3:author|subscriber,-4'
		);
	}

	function card_export(){
		$this->card(
			'export-multisite-card',
			__( 'Export every user of your network in one file', 'import-users-from-csv-with-meta' ),
			sprintf( __( 'This screen exports the users of this site. The <strong>Multisite addon</strong> exports the %1$s users of your %2$s sites, with the sites and roles of each one and the data of every site, ready to be imported in another network:', 'import-users-from-csv-with-meta' ), number_format_i18n( get_user_count() ), number_format_i18n( get_sites( array( 'count' => true ) ) ) ),
			'',
			'blogs = 1:administrator,2:editor,3:author · corecrew@2 · corecrew@3'
		);
	}

	private function features(){
		return array(
			__( '<strong>Per site or for the whole network</strong>, column by column', 'import-users-from-csv-with-meta' ),
			__( '<strong>The same template on every site</strong>, also with the frontend form', 'import-users-from-csv-with-meta' ),
			__( '<strong>A different role on each site</strong> and users removed from sites', 'import-users-from-csv-with-meta' ),
			__( '<strong>The whole network in one file</strong>, from the Network Admin', 'import-users-from-csv-with-meta' ),
		);
	}

	private function card( $campaign, $title, $intro, $alert, $example ){
		if( !self::show() )
			return;

		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=acui_dismiss_multisite_card' ), 'acui_dismiss_multisite_card' );
		$this->styles();
		?>
		<div class="acui-ms-promo">
			<a class="acui-ms-promo-dismiss" href="<?php echo esc_url( $dismiss_url ); ?>" title="<?php esc_attr_e( 'Do not show again', 'import-users-from-csv-with-meta' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text"><?php _e( 'Do not show again', 'import-users-from-csv-with-meta' ); ?></span></a>
			<div class="acui-ms-promo-icon"><span class="dashicons dashicons-networking" aria-hidden="true"></span></div>
			<div class="acui-ms-promo-body">
				<h3><?php echo esc_html( $title ); ?></h3>
				<?php if( $alert ): ?>
				<p class="acui-ms-promo-alert"><?php echo wp_kses( $alert, array( 'strong' => array() ) ); ?></p>
				<?php endif; ?>
				<p><?php echo wp_kses( $intro, array( 'strong' => array() ) ); ?></p>
				<ul class="acui-ms-promo-features">
					<?php foreach( $this->features() as $feature ): ?>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php echo wp_kses( $feature, array( 'strong' => array() ) ); ?></li>
					<?php endforeach; ?>
				</ul>
				<code class="acui-ms-promo-example"><?php echo esc_html( $example ); ?></code>
				<div class="acui-ms-promo-actions">
					<a class="button button-primary" href="<?php echo esc_url( self::url( $campaign ) ); ?>" target="_blank"><?php _e( 'Get the Multisite addon', 'import-users-from-csv-with-meta' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}

	function sidebar(){
		if( self::addon_active() || !self::can_buy() )
			return;
		?>
		<p><span class="dashicons dashicons-networking" style="color:#5b3cc4;" aria-hidden="true"></span> <?php printf( __( 'Running a <strong>Multisite network</strong>? Keep the data of each site apart and give a role per site with the <a href="%s" target="_blank">Multisite addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( self::url( 'homepage-sidebar-multisite' ) ) ); ?></p>
		<?php
	}

	function documentation(){
		if( self::addon_active() )
			return;
		?>
		<tr valign="top">
			<th scope="row"><?php _e( 'Multisite addon', 'import-users-from-csv-with-meta' ); ?></th>
			<td><?php printf( __( 'To decide column by column if the data is saved for one site or for the whole network, give a different role on each site (2:editor,3:author), remove users from sites and export the whole network in one file, use the <a href="%s" target="_blank">Multisite addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( self::url( 'documentation-multisite' ) ) ); ?></td>
		</tr>
		<?php
	}

	function import_notice( $html, $results, $errors ){
		if( !self::show() || get_sites( array( 'count' => true ) ) < 2 )
			return $html;

		$shared = self::shared_columns();

		if( empty( $shared ) )
			return $html;

		$names = array_slice( $shared, 0, 4 );

		return $html . '<div class="notice notice-info inline"><p>'
			. sprintf(
				_n( '%1$s was saved for the whole network: every site sees the same value and the last import overwrites it.', '%1$s were saved for the whole network: every site sees the same value and the last import overwrites it.', count( $shared ), 'import-users-from-csv-with-meta' ),
				'<strong>' . esc_html( implode( ', ', $names ) . ( count( $shared ) > 4 ? '…' : '' ) ) . '</strong>'
			)
			. ' ' . sprintf( __( 'With the <a href="%s" target="_blank">Multisite addon</a> each site can keep its own value.', 'import-users-from-csv-with-meta' ), esc_url( self::url( 'import-result-multisite' ) ) )
			. '</p></div>';
	}

	private function styles(){
		static $printed = false;

		if( $printed )
			return;

		$printed = true;
		?>
		<style>
		.acui-ms-promo{ position:relative; display:flex; gap:18px; align-items:flex-start; background:#fff; border:1px solid #c3c4c7; border-left:4px solid #5b3cc4; border-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,.07); padding:18px 44px 18px 18px; margin:16px 0 20px; }
		.acui-ms-promo-icon{ flex-shrink:0; width:48px; height:48px; border-radius:50%; background:#efebfb; color:#5b3cc4; display:flex; align-items:center; justify-content:center; }
		.acui-ms-promo-icon .dashicons{ font-size:28px; width:28px; height:28px; }
		.acui-ms-promo-body{ flex:1; min-width:0; }
		.acui-ms-promo-body h3{ margin:0 0 4px; font-size:15px; }
		.acui-ms-promo-body p{ margin:0 0 10px; color:#50575e; }
		.acui-ms-promo-alert{ display:inline-block; background:#fcf9e8; color:#614200; border-radius:3px; padding:4px 8px; }
		.acui-ms-promo-features{ display:grid; grid-template-columns:repeat( 2, minmax( 0, 1fr ) ); gap:4px 20px; margin:0 0 12px; }
		.acui-ms-promo-features li{ margin:0; padding-left:22px; position:relative; }
		.acui-ms-promo-features li .dashicons{ position:absolute; left:0; top:1px; color:#00a32a; font-size:18px; width:18px; height:18px; }
		.acui-ms-promo-example{ display:block; background:#f6f7f7; border:1px solid #dcdcde; border-radius:3px; padding:6px 10px; margin:0 0 12px; font-size:12px; overflow-x:auto; white-space:nowrap; }
		.acui-ms-promo-actions{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
		.acui-ms-promo-actions .button-primary{ background:#5b3cc4; border-color:#5b3cc4; }
		.acui-ms-promo-actions .button-primary:hover,
		.acui-ms-promo-actions .button-primary:focus{ background:#4a2fa6; border-color:#4a2fa6; }
		.acui-ms-promo-dismiss{ position:absolute; top:10px; right:10px; color:#787c82; text-decoration:none; }
		.acui-ms-promo-dismiss:hover{ color:#d63638; }
		@media (max-width:782px){ .acui-ms-promo{ flex-direction:column; } .acui-ms-promo-features{ grid-template-columns:1fr; } }
		</style>
		<?php
	}
}

new ACUI_Multisite_Promo();
