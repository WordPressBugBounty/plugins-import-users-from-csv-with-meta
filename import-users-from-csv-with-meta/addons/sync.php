<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ACUI_Sync_Promo{
	const DISMISS_META = 'acui_dismissed_sync_card';
	const SLUG = 'sync-addon';

	function __construct(){
		add_filter( 'acui_tabs', array( $this, 'tab' ) );
		add_action( 'acui_tab_action_sync', array( $this, 'tab_page' ) );
		add_action( 'acui_export_start', array( $this, 'card_export' ), 20 );
		add_action( 'acui_cron_start', array( $this, 'card_cron' ) );
		add_action( 'acui_homepage_sidebar_info', array( $this, 'sidebar' ) );
		add_action( 'admin_post_acui_dismiss_sync_card', array( $this, 'dismiss' ) );
	}

	static function addon_active(){
		return defined( 'ACUI_SYNC_VERSION' );
	}

	static function can_buy(){
		return current_user_can( 'manage_options' );
	}

	function tab( $tabs ){
		if( self::addon_active() || !self::can_buy() || isset( $tabs['sync'] ) )
			return $tabs;

		$tabs['sync'] = __( 'Sync', 'import-users-from-csv-with-meta' ) . ' <span style="display:inline-block;margin-left:4px;padding:0 5px;border-radius:3px;background:#007c89;color:#fff;font-size:10px;line-height:16px;vertical-align:middle;">PRO</span>';

		return $tabs;
	}

	function dismiss(){
		check_admin_referer( 'acui_dismiss_sync_card' );

		if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
			wp_die( __( 'You are not allowed to do this.', 'import-users-from-csv-with-meta' ) );

		update_user_meta( get_current_user_id(), self::DISMISS_META, time() );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'tools.php?page=acui' ) );
		exit;
	}

	private function url( $campaign ){
		return ACUIHelper()->premium_url( self::SLUG, $campaign );
	}

	private function features(){
		return array(
			__( '<strong>New users, emails, passwords and roles</strong> reach your other sites in seconds', 'import-users-from-csv-with-meta' ),
			__( '<strong>Both directions</strong>: a change made in any site travels to all the others', 'import-users-from-csv-with-meta' ),
			__( '<strong>WooCommerce addresses</strong> and any user meta you choose', 'import-users-from-csv-with-meta' ),
			__( '<strong>Secure</strong>: signed requests, only password hashes travel, administrators are never synced', 'import-users-from-csv-with-meta' ),
		);
	}

	function card_export(){
		$this->card(
			'export-sync-card',
			__( 'Exporting users to import them in another site?', 'import-users-from-csv-with-meta' ),
			__( 'The <strong>Sync addon</strong> keeps users the same in all your sites, at the moment and without files:', 'import-users-from-csv-with-meta' )
		);
	}

	function card_cron(){
		if( !get_option( 'acui_cron_activated' ) )
			return;

		$path = (string) get_option( 'acui_cron_path_to_file' );
		$alert = '';

		if( preg_match( '#^https?://#i', $path ) ){
			$host = wp_parse_url( $path, PHP_URL_HOST );
			$alert = sprintf( __( 'This recurring import downloads a file from %s. With the Sync addon those users would arrive at the moment instead of every few hours, and the changes made here would travel back too.', 'import-users-from-csv-with-meta' ), '<strong>' . esc_html( $host ) . '</strong>' );
		}

		$this->card(
			'cron-tab-sync-card',
			__( 'Importing users from another site on a schedule?', 'import-users-from-csv-with-meta' ),
			__( 'Replace the file and the schedule with the <strong>Sync addon</strong>: users are kept the same in all your sites, at the moment.', 'import-users-from-csv-with-meta' ),
			$alert
		);
	}

	private function card( $campaign, $title, $intro, $alert = '' ){
		if( self::addon_active() || !self::can_buy() || get_user_meta( get_current_user_id(), self::DISMISS_META, true ) )
			return;

		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=acui_dismiss_sync_card' ), 'acui_dismiss_sync_card' );
		$this->styles();
		?>
		<div class="acui-sync-card">
			<a class="acui-sync-card-dismiss" href="<?php echo esc_url( $dismiss_url ); ?>" title="<?php esc_attr_e( 'Do not show again', 'import-users-from-csv-with-meta' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text"><?php _e( 'Do not show again', 'import-users-from-csv-with-meta' ); ?></span></a>
			<div class="acui-sync-card-icon"><span class="dashicons dashicons-update" aria-hidden="true"></span></div>
			<div class="acui-sync-card-body">
				<h3><?php echo esc_html( $title ); ?></h3>
				<p><?php echo wp_kses( $intro, array( 'strong' => array() ) ); ?></p>
				<?php if( $alert ): ?>
				<p class="acui-sync-card-alert"><?php echo wp_kses( $alert, array( 'strong' => array() ) ); ?></p>
				<?php endif; ?>
				<ul class="acui-sync-card-features">
					<?php foreach( $this->features() as $feature ): ?>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php echo wp_kses( $feature, array( 'strong' => array() ) ); ?></li>
					<?php endforeach; ?>
				</ul>
				<div class="acui-sync-card-actions">
					<a class="button button-primary" href="<?php echo esc_url( $this->url( $campaign ) ); ?>" target="_blank"><?php _e( 'Get the Sync addon', 'import-users-from-csv-with-meta' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'tools.php?page=acui&tab=sync' ) ); ?>"><?php _e( 'How it works', 'import-users-from-csv-with-meta' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}

	function sidebar(){
		if( self::addon_active() || !self::can_buy() )
			return;
		?>
		<p><span class="dashicons dashicons-update" style="color:#007c89;" aria-hidden="true"></span> <?php printf( __( 'Moving users between your sites again and again? Keep them in sync in real time with the <a href="%s" target="_blank">Sync addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( $this->url( 'homepage-sidebar-sync' ) ) ); ?></p>
		<?php
	}

	function tab_page(){
		if( self::addon_active() )
			return;

		$this->styles();
		$rows = array(
			array( __( 'When users arrive', 'import-users-from-csv-with-meta' ), __( 'When someone exports and imports, or every few hours with a recurring import', 'import-users-from-csv-with-meta' ), __( 'In seconds, as soon as they register or change their profile', 'import-users-from-csv-with-meta' ) ),
			array( __( 'Direction', 'import-users-from-csv-with-meta' ), __( 'From one site to another', 'import-users-from-csv-with-meta' ), __( 'Every site sends and receives', 'import-users-from-csv-with-meta' ) ),
			array( __( 'Changes in both sites', 'import-users-from-csv-with-meta' ), __( 'The last file overwrites everything', 'import-users-from-csv-with-meta' ), __( 'Each field keeps its most recent value', 'import-users-from-csv-with-meta' ) ),
			array( __( 'Deleted users and role changes', 'import-users-from-csv-with-meta' ), __( 'Hard to carry over', 'import-users-from-csv-with-meta' ), __( 'Carried over, and you decide what each site does with deletions', 'import-users-from-csv-with-meta' ) ),
			array( __( 'Personal data', 'import-users-from-csv-with-meta' ), __( 'In files that stay on servers or travel by email', 'import-users-from-csv-with-meta' ), __( 'Only between your sites, in signed requests', 'import-users-from-csv-with-meta' ) ),
			array( __( 'If a site is down', 'import-users-from-csv-with-meta' ), __( 'The import fails until someone notices', 'import-users-from-csv-with-meta' ), __( 'Changes wait in a queue and are retried for hours', 'import-users-from-csv-with-meta' ) ),
		);
		?>
		<div class="acui-sync-page">
			<div class="acui-sync-hero">
				<div class="acui-sync-card-icon"><span class="dashicons dashicons-update" aria-hidden="true"></span></div>
				<div>
					<h2><?php _e( 'Keep users in sync between your WordPress sites', 'import-users-from-csv-with-meta' ); ?></h2>
					<p><?php _e( 'An online store and a course platform, a main site and its local sites, a membership site and its forum... If the same people use several of your sites, the <strong>Sync addon</strong> keeps their accounts the same everywhere, in real time and in both directions. No more exporting and importing files.', 'import-users-from-csv-with-meta' ); ?></p>
					<p><a class="button button-primary button-hero" href="<?php echo esc_url( $this->url( 'sync-tab' ) ); ?>" target="_blank"><?php _e( 'Get the Sync addon', 'import-users-from-csv-with-meta' ); ?></a></p>
				</div>
			</div>

			<div class="acui-sync-columns">
				<div class="acui-sync-box">
					<h3><?php _e( 'What is synced', 'import-users-from-csv-with-meta' ); ?></h3>
					<ul class="acui-sync-card-features acui-sync-one-column">
						<?php foreach( $this->features() as $feature ): ?>
						<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php echo wp_kses( $feature, array( 'strong' => array() ) ); ?></li>
						<?php endforeach; ?>
						<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php _e( '<strong>Users that already exist</strong> are matched by email with the initial sync', 'import-users-from-csv-with-meta' ); ?></li>
						<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php _e( '<strong>Log</strong> of every change sent and received', 'import-users-from-csv-with-meta' ); ?></li>
					</ul>
				</div>
				<div class="acui-sync-box">
					<h3><?php _e( 'Ready in three steps', 'import-users-from-csv-with-meta' ); ?></h3>
					<ol class="acui-sync-steps">
						<li><?php _e( 'Install the addon in every site and choose one as the main site.', 'import-users-from-csv-with-meta' ); ?></li>
						<li><?php _e( 'Add the other sites in the main site and paste the key it gives you in each of them.', 'import-users-from-csv-with-meta' ); ?></li>
						<li><?php _e( 'Choose what to sync and, if they already have users, run the initial sync.', 'import-users-from-csv-with-meta' ); ?></li>
					</ol>
				</div>
			</div>

			<div class="acui-sync-box">
				<h3><?php _e( 'CSV files or Sync addon', 'import-users-from-csv-with-meta' ); ?></h3>
				<table class="widefat striped acui-sync-compare">
					<thead><tr><th></th><th><?php _e( 'Exporting and importing files', 'import-users-from-csv-with-meta' ); ?></th><th><?php _e( 'Sync addon', 'import-users-from-csv-with-meta' ); ?></th></tr></thead>
					<tbody>
					<?php foreach( $rows as $row ): ?>
						<tr><th scope="row"><?php echo esc_html( $row[0] ); ?></th><td><?php echo esc_html( $row[1] ); ?></td><td><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php echo esc_html( $row[2] ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php _e( 'Importing files is still the best way to load users once or to move them to a new site: keep using this plugin for that.', 'import-users-from-csv-with-meta' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( $this->url( 'sync-tab-compare' ) ); ?>" target="_blank"><?php _e( 'Get the Sync addon', 'import-users-from-csv-with-meta' ); ?></a></p>
			</div>
		</div>
		<?php
	}

	private function styles(){
		static $printed = false;

		if( $printed )
			return;

		$printed = true;
		?>
		<style>
		.acui-sync-card{ position:relative; display:flex; gap:18px; align-items:flex-start; background:#fff; border:1px solid #c3c4c7; border-left:4px solid #007c89; border-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,.07); padding:18px 44px 18px 18px; margin:16px 0 20px; }
		.acui-sync-card-icon{ flex-shrink:0; width:48px; height:48px; border-radius:50%; background:#e3f4f6; color:#007c89; display:flex; align-items:center; justify-content:center; }
		.acui-sync-card-icon .dashicons{ font-size:28px; width:28px; height:28px; }
		.acui-sync-card-body{ flex:1; min-width:0; }
		.acui-sync-card-body h3{ margin:0 0 4px; font-size:15px; }
		.acui-sync-card-body p{ margin:0 0 10px; color:#50575e; }
		.acui-sync-card-alert{ display:inline-block; background:#fcf9e8; color:#614200; border-radius:3px; padding:4px 8px; }
		.acui-sync-card-features{ display:grid; grid-template-columns:repeat( 2, minmax( 0, 1fr ) ); gap:4px 20px; margin:0 0 12px; }
		.acui-sync-card-features li{ margin:0; padding-left:22px; position:relative; }
		.acui-sync-card-features li .dashicons{ position:absolute; left:0; top:1px; color:#00a32a; font-size:18px; width:18px; height:18px; }
		.acui-sync-card-features.acui-sync-one-column{ grid-template-columns:1fr; }
		.acui-sync-card-actions{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
		.acui-sync-card .button-primary, .acui-sync-page .button-primary{ background:#007c89; border-color:#007c89; }
		.acui-sync-card .button-primary:hover, .acui-sync-card .button-primary:focus, .acui-sync-page .button-primary:hover, .acui-sync-page .button-primary:focus{ background:#00626c; border-color:#00626c; }
		.acui-sync-card-dismiss{ position:absolute; top:10px; right:10px; color:#787c82; text-decoration:none; }
		.acui-sync-card-dismiss:hover{ color:#d63638; }
		.acui-sync-page{ max-width:1100px; }
		.acui-sync-hero{ display:flex; gap:20px; align-items:flex-start; background:#fff; border:1px solid #c3c4c7; border-left:4px solid #007c89; border-radius:4px; padding:20px 24px; margin:16px 0; }
		.acui-sync-hero h2{ margin:4px 0 8px; font-size:20px; }
		.acui-sync-hero p{ font-size:14px; max-width:760px; }
		.acui-sync-columns{ display:grid; grid-template-columns:1fr 1fr; gap:16px; }
		.acui-sync-box{ background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:4px 20px 16px; margin:0 0 16px; }
		.acui-sync-steps li{ margin-bottom:8px; }
		.acui-sync-compare td .dashicons{ color:#00a32a; }
		.acui-sync-compare th[scope="row"]{ font-weight:600; }
		@media (max-width:782px){ .acui-sync-card, .acui-sync-hero{ flex-direction:column; } .acui-sync-card-features, .acui-sync-columns{ grid-template-columns:1fr; } }
		</style>
		<?php
	}
}

new ACUI_Sync_Promo();
