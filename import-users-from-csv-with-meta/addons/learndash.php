<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'SFWD_LMS' ) ){
	return;
}

class ACUI_LearnDash{
	const DISMISS_META = 'acui_dismissed_learndash_card';

	function __construct(){
		add_filter( 'acui_restricted_fields', array( $this, 'restricted_fields' ), 10, 1 );
		add_action( 'acui_documentation_after_plugins_activated', array( $this, 'documentation' ) );
		add_action( 'acui_homepage_start', array( $this, 'card_import' ) );
		add_action( 'acui_export_start', array( $this, 'card_export' ) );
		add_action( 'acui_homepage_sidebar_info', array( $this, 'sidebar' ) );
		add_action( 'admin_post_acui_dismiss_learndash_card', array( $this, 'dismiss' ) );
	}

	static function addon_active(){
		return defined( 'ACUI_LD_VERSION' );
	}

	function restricted_fields( $acui_restricted_fields ){
		return array_merge( $acui_restricted_fields, array( 'lms_courses' ) );
	}

	function dismiss(){
		check_admin_referer( 'acui_dismiss_learndash_card' );

		if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
			wp_die( __( 'You are not allowed to do this.', 'import-users-from-csv-with-meta' ) );

		update_user_meta( get_current_user_id(), self::DISMISS_META, time() );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'tools.php?page=acui' ) );
		exit;
	}

	function card_import(){
		$this->card( 'homepage-learndash-card', __( 'Using LearnDash? Import your students with all their progress', 'import-users-from-csv-with-meta' ) );
	}

	function card_export(){
		$this->card( 'export-learndash-card', __( 'Using LearnDash? Export your students with all their progress', 'import-users-from-csv-with-meta' ) );
	}

	function card( $campaign, $title ){
		if( self::addon_active() || get_user_meta( get_current_user_id(), self::DISMISS_META, true ) )
			return;

		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=acui_dismiss_learndash_card' ), 'acui_dismiss_learndash_card' );
		$features = array(
			__( '<strong>Groups and courses</strong> of each student, with the enrollment date', 'import-users-from-csv-with-meta' ),
			__( '<strong>Lessons and topics completed</strong>, each one with its date', 'import-users-from-csv-with-meta' ),
			__( '<strong>Quiz attempts</strong> with score, percentage and passed or failed', 'import-users-from-csv-with-meta' ),
			__( '<strong>Completed courses</strong> with their date, for certificates and reports', 'import-users-from-csv-with-meta' ),
		);
		?>
		<style>
		.acui-addon-card{ position:relative; display:flex; gap:18px; align-items:flex-start; background:#fff; border:1px solid #c3c4c7; border-left:4px solid #8c3aa9; border-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,.07); padding:18px 44px 18px 18px; margin:16px 0 20px; }
		.acui-addon-card-icon{ flex-shrink:0; width:48px; height:48px; border-radius:50%; background:#f3e8f7; color:#8c3aa9; display:flex; align-items:center; justify-content:center; }
		.acui-addon-card-icon .dashicons{ font-size:28px; width:28px; height:28px; }
		.acui-addon-card-body{ flex:1; min-width:0; }
		.acui-addon-card-body h3{ margin:0 0 4px; font-size:15px; }
		.acui-addon-card-body p{ margin:0 0 10px; color:#50575e; }
		.acui-addon-card-features{ display:grid; grid-template-columns:repeat( 2, minmax( 0, 1fr ) ); gap:4px 20px; margin:0 0 12px; }
		.acui-addon-card-features li{ margin:0; padding-left:22px; position:relative; }
		.acui-addon-card-features li .dashicons{ position:absolute; left:0; top:1px; color:#00a32a; font-size:18px; width:18px; height:18px; }
		.acui-addon-card-example{ display:block; background:#f6f7f7; border:1px solid #dcdcde; border-radius:3px; padding:6px 10px; margin:0 0 12px; font-size:12px; overflow-x:auto; white-space:nowrap; }
		.acui-addon-card-actions{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
		.acui-addon-card-actions .button-primary{ background:#8c3aa9; border-color:#8c3aa9; }
		.acui-addon-card-actions .button-primary:hover,
		.acui-addon-card-actions .button-primary:focus{ background:#74308c; border-color:#74308c; }
		.acui-addon-card-dismiss{ position:absolute; top:10px; right:10px; color:#787c82; text-decoration:none; }
		.acui-addon-card-dismiss:hover{ color:#d63638; }
		@media (max-width:782px){ .acui-addon-card{ flex-direction:column; } .acui-addon-card-features{ grid-template-columns:1fr; } }
		</style>
		<div class="acui-addon-card">
			<a class="acui-addon-card-dismiss" href="<?php echo esc_url( $dismiss_url ); ?>" title="<?php esc_attr_e( 'Do not show again', 'import-users-from-csv-with-meta' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text"><?php _e( 'Do not show again', 'import-users-from-csv-with-meta' ); ?></span></a>
			<div class="acui-addon-card-icon"><span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span></div>
			<div class="acui-addon-card-body">
				<h3><?php echo esc_html( $title ); ?></h3>
				<p><?php _e( 'The <strong>LearnDash addon</strong> moves students between sites, or lets you fill their data by hand in a spreadsheet, using course, lesson and quiz titles instead of IDs:', 'import-users-from-csv-with-meta' ); ?></p>
				<ul class="acui-addon-card-features">
					<?php foreach( $features as $feature ): ?>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php echo wp_kses( $feature, array( 'strong' => array() ) ); ?></li>
					<?php endforeach; ?>
				</ul>
				<code class="acui-addon-card-example">Introduction to PHP &gt; Final exam = 9/10, 90%, passed [2025-09-12 17:00]</code>
				<div class="acui-addon-card-actions">
					<a class="button button-primary" href="<?php echo esc_url( ACUIHelper()->premium_url( 'learndash-addon', $campaign ) ); ?>" target="_blank"><?php _e( 'Get the LearnDash addon', 'import-users-from-csv-with-meta' ); ?></a>
					<span class="description"><?php _e( 'Old progress is imported with its dates and without emailing your students.', 'import-users-from-csv-with-meta' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	function sidebar(){
		if( self::addon_active() )
			return;
		?>
		<p><span class="dashicons dashicons-welcome-learn-more" style="color:#8c3aa9;" aria-hidden="true"></span> <?php printf( __( 'Using <strong>LearnDash</strong>? Move your students with their courses, progress and quiz results with the <a href="%s" target="_blank">LearnDash addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( ACUIHelper()->premium_url( 'learndash-addon', 'homepage-sidebar-learndash' ) ) ); ?></p>
		<?php
	}

	function documentation(){
		if( self::addon_active() )
			return;
		?>
		<tr valign="top">
			<th scope="row"><?php _e( "LearnDash is activated", 'import-users-from-csv-with-meta' ); ?></th>
			<td>
				<?php _e( "You can enroll users in LearnDash courses importing, for each course, a column named course_courseID_access_from (replace courseID with the ID of the course) with the enrollment date as a timestamp.", 'import-users-from-csv-with-meta' ); ?>
				<p><?php printf( __( 'To move students between sites with their groups, courses, lessons and topics completed, quiz results and completion dates, or to fill them by hand with course titles instead of IDs, use the <a href="%s" target="_blank">LearnDash addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( ACUIHelper()->premium_url( 'learndash-addon', 'documentation-learndash' ) ) ); ?></p>
			</td>
		</tr>
		<?php
	}
}

new ACUI_LearnDash();
