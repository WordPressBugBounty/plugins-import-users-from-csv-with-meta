<?php

if ( ! defined( 'ABSPATH' ) ) exit; 

if( !function_exists( 'bp_is_active' ) || defined( 'ACUI_BP_VERSION' ) ){
	return;
}

class ACUI_Buddypress{
	var $profile_groups = null;

	function is_xprofile_active(){
		return bp_is_active( 'xprofile' ) && class_exists( 'BP_XProfile_Group' ) && function_exists( 'xprofile_set_field_data' );
	}

	function is_groups_active(){
		return bp_is_active( 'groups' ) && class_exists( 'BP_Groups_Member' ) && function_exists( 'groups_join_group' );
	}

	function hooks(){
		add_filter( 'acui_restricted_fields', array( $this, 'restricted_fields' ), 10, 1 );
		add_action( 'acui_documentation_after_plugins_activated', array( $this, 'documentation' ) );
		add_filter( 'acui_export_columns', array( $this, 'export_columns' ), 10, 1 );
		add_filter( 'acui_export_data', array( $this, 'export_data' ), 10, 3 );
		add_action( 'acui_post_import_single_user', array( $this, 'import' ), 10, 10 );	
		add_action( 'acui_post_import_single_user', array( $this, 'import_avatar' ), 10, 3 );
		add_filter( 'acui_force_reset_password_edit_profile_url', array( $this, 'force_reset_password_edit_profile_url' ), 9 );
		add_action( 'bp_before_member_settings_template', array( $this, 'force_reset_password_notice' ) );
		add_action( 'acui_homepage_start', array( $this, 'card_import' ) );
		add_action( 'acui_export_start', array( $this, 'card_export' ) );
		add_action( 'acui_homepage_sidebar_info', array( $this, 'sidebar' ) );
		add_action( 'admin_post_acui_dismiss_buddypress_card', array( $this, 'dismiss' ) );
	}

	static function platform_name(){
		return defined( 'BP_PLATFORM_VERSION' ) ? 'BuddyBoss' : 'BuddyPress';
	}

	static function members_out_of_directory(){
		global $wpdb;

		$bp = buddypress();

		if( empty( $bp->members->table_name_last_activity ) )
			return 0;

		$count = get_transient( 'acui_bp_out_of_directory' );

		if( $count === false ){
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} u LEFT JOIN {$bp->members->table_name_last_activity} a ON a.user_id = u.ID AND a.component = 'members' AND a.type = 'last_activity' WHERE a.id IS NULL AND u.user_status = 0" );
			set_transient( 'acui_bp_out_of_directory', $count, HOUR_IN_SECONDS );
		}

		return (int) $count;
	}

	function dismiss(){
		check_admin_referer( 'acui_dismiss_buddypress_card' );

		if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
			wp_die( __( 'You are not allowed to do this.', 'import-users-from-csv-with-meta' ) );

		update_user_meta( get_current_user_id(), 'acui_dismissed_buddypress_card', time() );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'tools.php?page=acui' ) );
		exit;
	}

	function card_import(){
		$this->card( 'homepage-buddypress-card', sprintf( __( 'Using %s? Import your members with their whole community life', 'import-users-from-csv-with-meta' ), self::platform_name() ) );
	}

	function card_export(){
		$this->card( 'export-buddypress-card', sprintf( __( 'Moving your %s community? Take everything in one package', 'import-users-from-csv-with-meta' ), self::platform_name() ) );
	}

	function card( $campaign, $title ){
		if( get_user_meta( get_current_user_id(), 'acui_dismissed_buddypress_card', true ) )
			return;

		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=acui_dismiss_buddypress_card' ), 'acui_dismiss_buddypress_card' );
		$missing = self::members_out_of_directory();
		$features = array(
			__( '<strong>Complete profiles</strong> with field visibility, photos and cover images', 'import-users-from-csv-with-meta' ),
			__( '<strong>Groups</strong> with roles and join dates, created if they are missing', 'import-users-from-csv-with-meta' ),
			__( '<strong>Connections, followers and forums</strong>: subscriptions, favorites and roles', 'import-users-from-csv-with-meta' ),
			__( '<strong>The whole community in one package</strong>, even from BuddyPress to BuddyBoss', 'import-users-from-csv-with-meta' ),
		);
		?>
		<style>
		.acui-bp-promo{ position:relative; display:flex; gap:18px; align-items:flex-start; background:#fff; border:1px solid #c3c4c7; border-left:4px solid #d84800; border-radius:4px; box-shadow:0 1px 3px rgba(0,0,0,.07); padding:18px 44px 18px 18px; margin:16px 0 20px; }
		.acui-bp-promo-icon{ flex-shrink:0; width:48px; height:48px; border-radius:50%; background:#fdeee6; color:#d84800; display:flex; align-items:center; justify-content:center; }
		.acui-bp-promo-icon .dashicons{ font-size:28px; width:28px; height:28px; }
		.acui-bp-promo-body{ flex:1; min-width:0; }
		.acui-bp-promo-body h3{ margin:0 0 4px; font-size:15px; }
		.acui-bp-promo-body p{ margin:0 0 10px; color:#50575e; }
		.acui-bp-promo-alert{ display:inline-block; background:#fcf0f1; color:#8a2424; border-radius:3px; padding:4px 8px; }
		.acui-bp-promo-features{ display:grid; grid-template-columns:repeat( 2, minmax( 0, 1fr ) ); gap:4px 20px; margin:0 0 12px; }
		.acui-bp-promo-features li{ margin:0; padding-left:22px; position:relative; }
		.acui-bp-promo-features li .dashicons{ position:absolute; left:0; top:1px; color:#00a32a; font-size:18px; width:18px; height:18px; }
		.acui-bp-promo-example{ display:block; background:#f6f7f7; border:1px solid #dcdcde; border-radius:3px; padding:6px 10px; margin:0 0 12px; font-size:12px; overflow-x:auto; white-space:nowrap; }
		.acui-bp-promo-actions{ display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
		.acui-bp-promo-actions .button-primary{ background:#d84800; border-color:#d84800; }
		.acui-bp-promo-actions .button-primary:hover,
		.acui-bp-promo-actions .button-primary:focus{ background:#b83d00; border-color:#b83d00; }
		.acui-bp-promo-dismiss{ position:absolute; top:10px; right:10px; color:#787c82; text-decoration:none; }
		.acui-bp-promo-dismiss:hover{ color:#d63638; }
		@media (max-width:782px){ .acui-bp-promo{ flex-direction:column; } .acui-bp-promo-features{ grid-template-columns:1fr; } }
		</style>
		<div class="acui-bp-promo">
			<a class="acui-bp-promo-dismiss" href="<?php echo esc_url( $dismiss_url ); ?>" title="<?php esc_attr_e( 'Do not show again', 'import-users-from-csv-with-meta' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><span class="screen-reader-text"><?php _e( 'Do not show again', 'import-users-from-csv-with-meta' ); ?></span></a>
			<div class="acui-bp-promo-icon"><span class="dashicons dashicons-groups" aria-hidden="true"></span></div>
			<div class="acui-bp-promo-body">
				<h3><?php echo esc_html( $title ); ?></h3>
				<?php if( $missing > 0 ): ?>
				<p class="acui-bp-promo-alert"><?php printf( esc_html( _n( '%1$s member of this site does not appear in the %2$s members directory because it has no last activity. The addon fixes it with one click.', '%1$s members of this site do not appear in the %2$s members directory because they have no last activity. The addon fixes it with one click.', $missing, 'import-users-from-csv-with-meta' ) ), number_format_i18n( $missing ), esc_html( self::platform_name() ) ); ?></p>
				<?php endif; ?>
				<p><?php printf( __( 'The <strong>%s addon</strong> moves members between sites with everything that makes a community, using names instead of IDs:', 'import-users-from-csv-with-meta' ), 'BuddyPress &amp; BuddyBoss' ); ?></p>
				<ul class="acui-bp-promo-features">
					<?php foreach( $features as $feature ): ?>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php echo wp_kses( $feature, array( 'strong' => array() ) ); ?></li>
					<?php endforeach; ?>
				</ul>
				<code class="acui-bp-promo-example">Book club = admin [2025-03-01] | Photography = member</code>
				<div class="acui-bp-promo-actions">
					<a class="button button-primary" href="<?php echo esc_url( ACUIHelper()->premium_url( 'buddypress-addon', $campaign ) ); ?>" target="_blank"><?php _e( 'Get the BuddyPress & BuddyBoss addon', 'import-users-from-csv-with-meta' ); ?></a>
					<span class="description"><?php _e( 'Imported silently: no activity flood and no emails to your members.', 'import-users-from-csv-with-meta' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	function sidebar(){
		?>
		<p><span class="dashicons dashicons-groups" style="color:#d84800;" aria-hidden="true"></span> <?php printf( __( 'Using <strong>%1$s</strong>? Move your members with their groups, connections, photos and forums with the <a href="%2$s" target="_blank">BuddyPress &amp; BuddyBoss addon</a>.', 'import-users-from-csv-with-meta' ), esc_html( self::platform_name() ), esc_url( ACUIHelper()->premium_url( 'buddypress-addon', 'homepage-sidebar-buddypress' ) ) ); ?></p>
		<?php
	}

	function force_reset_password_edit_profile_url( $url ){
		if( $url != admin_url( 'profile.php' ) || !bp_is_active( 'settings' ) || !function_exists( 'bp_get_settings_slug' ) )
			return $url;

		$user_id = get_current_user_id();

		if( function_exists( 'bp_members_get_user_url' ) && function_exists( 'bp_members_get_path_chunks' ) )
			return bp_members_get_user_url( $user_id, bp_members_get_path_chunks( array( bp_get_settings_slug() ) ) );

		if( function_exists( 'bp_core_get_user_domain' ) )
			return trailingslashit( bp_core_get_user_domain( $user_id ) . bp_get_settings_slug() );

		return $url;
	}

	function force_reset_password_notice(){
		if( get_user_meta( get_current_user_id(), 'acui_force_reset_password', true ) )
			printf( '<div class="error"><p>%s</p></div>', apply_filters( 'acui_force_reset_password_message', __( 'Please change your password', 'import-users-from-csv-with-meta' ) ) );
	}

	function restricted_fields( $acui_restricted_fields ){
		return array_merge( $acui_restricted_fields, array( 'bp_group', 'bp_group_id', 'bp_group_role', 'bp_avatar', 'bp_member_type' ), $this->get_fields_name() );
	}

	function get_profile_groups(){
		if( $this->profile_groups === null )
			$this->profile_groups = $this->is_xprofile_active() ? BP_XProfile_Group::get( array( 'fetch_fields' => true ) ) : array();

		return $this->profile_groups;
	}

	function get_fields_name(){
		$buddypress_fields = array();
		$profile_groups = $this->get_profile_groups();

		if ( !empty( $profile_groups ) ) {
			 foreach ( $profile_groups as $profile_group ) {
				if ( !empty( $profile_group->fields ) ) {				
					foreach ( $profile_group->fields as $field ) {
						$buddypress_fields[] = $field->name;
					}
				}
			}
		}

		return $buddypress_fields;
	}

	function get_fields_id(){
		$ids = array();
		$profile_groups = $this->get_profile_groups();

		if ( !empty( $profile_groups ) ) {
			 foreach ( $profile_groups as $profile_group ) {
				if ( !empty( $profile_group->fields ) ) {				
					foreach ( $profile_group->fields as $field ) {
						$ids[] = $field->id;
					}
				}
			}
		}

		return $ids;
	}

	function get_field_type( $field_name ){
		$profile_groups = $this->get_profile_groups();

		if ( !empty( $profile_groups ) ) {
			 foreach ( $profile_groups as $profile_group ) {
				if ( !empty( $profile_group->fields ) ) {				
					foreach ( $profile_group->fields as $field ) {
						if( $field_name == $field->name )
							return $field->type;
					}
				}
			}
		}
	}

	function get_type_import_help( $type ){
		switch( $type ){
			case 'datebox':
				$help = __( sprintf( 'Format should be like this: %s-01-01 00:00:00', date( 'Y' ) ), 'import-users-from-csv-with-meta' );
				break;
				
			case 'checkbox':
				$help = __( 'If you use more than one value, please use ## to separate each item', 'import-users-from-csv-with-meta' );
				break;
		}

		return empty( $help ) ? '' : " <em>($help)</em>";
	}

	function get_groups( $user_id ){
		if( !$this->is_groups_active() )
			return '';

		$groups = BP_Groups_Member::get_group_ids( $user_id );
		return implode( ",", $groups['groups'] );
	}

	function get_member_type( $user_id ){
		$member_types = bp_get_member_type( $user_id, false );
		return ( is_array( $member_types ) ) ? implode( ",", $member_types ) : $member_types;
	}

	function documentation(){
		?>
		<tr valign="top">
			<th scope="row"><?php _e( 'BuddyPress/BuddyBoss fields', 'import-users-from-csv-with-meta'); ?></th>
			<td>
				<?php if( !$this->is_xprofile_active() ): ?>
				<?php _e( 'Enable the <strong>Extended Profiles</strong> component of BuddyPress/BuddyBoss to import and export profile fields.', 'import-users-from-csv-with-meta' ); ?>
				<?php else: ?>
				<?php _e( 'You can insert any profile from BuddyPress using its name as header. The plugin will check, before importing, which fields are defined in BuddyPress and will assign them in the update. You can use these fields:', 'import-users-from-csv-with-meta' ); ?>
				<ul style="list-style:disc outside none;margin-left:2em;">
					<?php foreach ( $this->get_fields_name() as $buddypress_field ):
						$type = $this->get_field_type( $buddypress_field );
					?>
					<li><?php echo esc_html( $buddypress_field ); ?> - <?php echo esc_html( $type ) . $this->get_type_import_help( $type ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php _e( 'The visibility options of each field when importing will be the default ones defined by BuddyPress/BuddyBoss.', 'import-users-from-csv-with-meta' ); ?>
				<?php endif; ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'BuddyPress/BuddyBoss avatar', 'import-users-from-csv-with-meta' ); ?></th>
			<td><?php _e( 'You can import user avatars using a column called <strong>bp_avatar</strong>. In this field you can place:', 'import-users-from-csv-with-meta' ); ?>
			<ul style="list-style:disc outside none;margin-left:2em;">
					<li>An integer which identify the ID of an attachment uploaded to your media library</li>
					<li>A string that contain a path or an URL to the image</li>
				</ul>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( "BuddyPress/BuddyBoss groups and roles", 'import-users-from-csv-with-meta' ); ?></th>
			<td>
				<?php if( !$this->is_groups_active() ): ?>
				<?php _e( 'Enable the <strong>Groups</strong> component of BuddyPress/BuddyBoss to assign groups to users.', 'import-users-from-csv-with-meta' ); ?>
				<?php else: ?>
				<?php _e( "You can use the <strong>profile fields</strong> you have created and also can set one or more groups for each user. For example:", 'import-users-from-csv-with-meta' ); ?>
				<ul style="list-style:disc outside none; margin-left:2em;">
					<li><?php _e( "If you want to assign a user to a group, you have to create a column 'bp_group' and a column 'bp_group_role'", 'import-users-from-csv-with-meta' ); ?></li>
					<li><?php _e( "Then in each cell you have to fill in the BuddyPress <strong>group slug</strong>", 'import-users-from-csv-with-meta' ); ?></li>
                    <li><?php _e( "And the role assigned in this group:", 'import-users-from-csv-with-meta' ); ?>  <em>Administrator, Moderator or Member</em></li>
					<li><?php _e( "You can also use group IDs if you know them, using a column 'bp_group_id' instead of 'bp_group'", 'import-users-from-csv-with-meta' ); ?></li>
					<li><?php _e( "You can do it with multiple groups at the same time using commas to separate different groups, in bp_group column, i.e.: <em>group_1, group_2, group_3</em>", 'import-users-from-csv-with-meta' ); ?></li>
					<li><?php _e( "But you will have to assign a role for each group:", 'import-users-from-csv-with-meta' ); ?> <em>Moderator,Moderator,Member,Member</em></li>
                    <li><?php _e( "If you choose to update roles and group role is empty, the user will be removed from the group", 'import-users-from-csv-with-meta' ); ?></li>
				</ul>
				<?php endif; ?>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'BuddyPress/BuddyBoss Profile Type', 'import-users-from-csv-with-meta' ); ?></th>
			<td><?php _e( 'You can assign a BuddyPress/BuddyBoss <strong>Profile Type</strong> (Member Type) to a user using a column called <strong>bp_member_type</strong>. The value must exactly match the type\'s slug (not its display label). Only one type can be set per user.', 'import-users-from-csv-with-meta' ); ?></td>
		</tr>
		<tr valign="top">
			<th scope="row"><?php _e( 'BuddyPress/BuddyBoss addon', 'import-users-from-csv-with-meta' ); ?></th>
			<td><?php printf( __( 'To import and export field visibility, cover images, groups by name with join dates, connections, followers, last activity, account status and forum subscriptions, to import groups themselves, or to move a whole community to another site in one package, use the <a href="%s" target="_blank">BuddyPress &amp; BuddyBoss addon</a>.', 'import-users-from-csv-with-meta' ), esc_url( ACUIHelper()->premium_url( 'buddypress-addon', 'documentation-buddypress' ) ) ); ?></td>
		</tr>
		<?php
	}

	function export_columns( $row ){
		foreach ( $this->get_fields_name() as $key ) {
			$row[ $key ] = $key;
		}

		if( $this->is_groups_active() )
			$row['bp_group_id'] = 'bp_group_id';

		$row['bp_member_type'] = 'bp_member_type';

		return $row;
	}

	function export_data( $row, $user, $args ){
		$fields_name = $this->get_fields_name();
		$fields_to_export = ( count( $args['filtered_columns'] ) == 0 ) ? $fields_name : array_intersect( $fields_name, $args['filtered_columns'] );

		foreach( $fields_to_export as $key ) {
			$row[ $key ] = xprofile_get_field_data( $key, $user, 'comma' );
		}

		if( $this->is_groups_active() && ( count( $args['filtered_columns'] ) == 0 || in_array( 'bp_group_id', $args['filtered_columns'] ) ) )
			$row['bp_group_id'] = $this->get_groups( $user );

		if( count( $args['filtered_columns'] ) == 0 || in_array( 'bp_member_type', $args['filtered_columns'] ) )
			$row['bp_member_type'] = $this->get_member_type( $user );

		return $row;
	}
	
	function import( $headers, $row, $user_id, $role, $positions, $form_data, $is_frontend, $is_cron, $password_changed, $created ){
        $update_roles_existing_users = isset( $form_data["update_roles_existing_users"] ) ? sanitize_text_field( $form_data["update_roles_existing_users"] ) : '';

		foreach( $this->get_fields_name() as $field ){
			$pos = array_search( $field, $headers );

			if( $pos === FALSE )
				continue;

			switch( $this->get_field_type( $field ) ){
				case 'datebox':
					$date = $row[$pos];
					switch( true ){
						case is_numeric( $date ):
							$UNIX_DATE = ($date - 25569) * 86400;
							$datebox = gmdate("Y-m-d H:i:s", $UNIX_DATE);break;
						case preg_match('/(\d{1,2})[\/-](\d{1,2})[\/-]([4567890]{1}\d{1})/',$date,$match):
							$match[3]='19'.$match[3];
						case preg_match('/(\d{1,2})[\/-](\d{1,2})[\/-](20[4567890]{1}\d{1})/',$date,$match):
						case preg_match('/(\d{1,2})[\/-](\d{1,2})[\/-](19[4567890]{1}\d{1})/',$date,$match):
							$datebox= ($match[3].'-'.$match[2].'-'.$match[1]);
							break;

						default:
							$datebox = $date;
					}

					$datebox = strtotime( $datebox );
					xprofile_set_field_data( $field, $user_id, date( 'Y-m-d H:i:s', $datebox ) );
					unset( $datebox );
					break;
				
				case 'checkbox':
					xprofile_set_field_data( $field, $user_id, explode( '##', $row[ $pos ] ) );
					break;

				default:
					xprofile_set_field_data( $field, $user_id, $row[$pos] );
			}	
		}

		$groups_active = $this->is_groups_active();
		$pos_bp_group = array_search( 'bp_group', $headers );
        $pos_bp_group_id = array_search( 'bp_group_id', $headers );
		$pos_bp_group_role = array_search( 'bp_group_role', $headers );

        if( $groups_active && $pos_bp_group !== FALSE ){
			$groups = explode( ',', $row[ $pos_bp_group ] );
			$groups_role = explode( ',', $row[ $pos_bp_group_role ] );

            for( $j = 0; $j < count( $groups ); $j++ ){
				$group_id = BP_Groups_Group::group_exists( $groups[ $j ] );

				if( !empty( $group_id ) ){
					$this->add_user_group( $user_id, $group_id, $groups_role[ $j ], $update_roles_existing_users );
				}
			}
		}

        if( $groups_active && $pos_bp_group_id !== FALSE ){
			$groups_id = explode( ',', $row[ $pos_bp_group_id ] );
			$groups_role = explode( ',', $row[ $pos_bp_group_role ] );

            for( $j = 0; $j < count( $groups_id ); $j++ ){
				$group_id = intval( $groups_id[ $j ] );

				if( !empty( $group_id ) ){
					$this->add_user_group( $user_id, $group_id, $groups_role[ $j ], $update_roles_existing_users );
				}
			}
		}
			
		$headers_lower = array_map( 'strtolower', $headers );
		$pos_member_type = array_search( 'bp_member_type', $headers_lower );
		if( $pos_member_type !== FALSE ){
			bp_set_member_type( $user_id, $row[$pos_member_type] );
		}

		if( $created && $this->is_xprofile_active() ){
			$bp_xprofile_visibility_levels = array();
			foreach( $this->get_fields_id() as $field_id ){
				$bp_xprofile_visibility_levels[ $field_id ] = 'public';
			}

			bp_update_user_meta( $user_id, 'bp_xprofile_visibility_levels', $bp_xprofile_visibility_levels );
		}
	}

    function add_user_group( $user_id, $group_id, $group_role, $update_roles_existing_users ){
        if( $update_roles_existing_users == 'yes' || $update_roles_existing_users == 'yes_no_override' ){
            $member = new BP_Groups_Member( $user_id, $group_id );
            $member->remove();
        }
        
        if( ( $update_roles_existing_users == 'yes' || $update_roles_existing_users == 'yes_no_override' ) && empty( $group_role ) )
            return;

        groups_join_group( $group_id, $user_id );

        $group_role = strtolower( trim( (string) $group_role ) );

        if( $group_role == 'moderator' ){
            $this->promote_user_group( $user_id, $group_id, 'mod' );
        }
        elseif( $group_role == 'administrator' ){
            $this->promote_user_group( $user_id, $group_id, 'admin' );
        }
    }

    function promote_user_group( $user_id, $group_id, $status ){
        $member = new BP_Groups_Member( $user_id, $group_id );

        if( empty( $member->id ) )
            return false;

        do_action( 'groups_promote_member', $group_id, $user_id, $status );

        if( !$member->promote( $status ) )
            return false;

        do_action( 'group_member_promoted', $user_id, $group_id );

        return true;
    }

	function import_avatar( $headers, $row, $user_id ){
		$pos = array_search( 'bp_avatar', $headers );

		if( $pos === FALSE )
			return;

		$this->import_avatar_raw( $user_id, $row[ $pos ] );
	}

	function is_safe_avatar_url( $url ){
		if ( wp_http_validate_url( $url ) === false )
			return false;

		$host = wp_parse_url( $url, PHP_URL_HOST );

		if ( empty( $host ) )
			return false;

		$ip = filter_var( $host, FILTER_VALIDATE_IP ) ? $host : gethostbyname( $host );

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) )
			return false;

		$blocked_ranges = array(
			'127.0.0.0/8',
			'10.0.0.0/8',
			'172.16.0.0/12',
			'192.168.0.0/16',
			'169.254.0.0/16',
			'100.64.0.0/10',
			'198.18.0.0/15',
			'::1/128',
			'fc00::/7',
			'fe80::/10',
		);

		foreach ( $blocked_ranges as $range ) {
			if ( $this->ip_in_range( $ip, $range ) )
				return false;
		}

		return true;
	}

	function fetch_avatar_url_safely( $url, $max_redirects = 3 ){
		for( $i = 0; $i <= $max_redirects; $i++ ){
			if ( ! $this->is_safe_avatar_url( $url ) )
				return false;

			$response = wp_safe_remote_get( $url, array( 'redirection' => 0 ) );

			if ( is_wp_error( $response ) )
				return false;

			$code = wp_remote_retrieve_response_code( $response );

			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				$location = wp_remote_retrieve_header( $response, 'location' );

				if ( empty( $location ) )
					return false;

				$url = WP_Http::make_absolute_url( $location, $url );
				continue;
			}

			if ( $code !== 200 )
				return false;

			return wp_remote_retrieve_body( $response );
		}

		return false;
	}

	function ip_in_range( $ip, $range ){
		list( $subnet, $bits ) = explode( '/', $range );

		$ip_bin = inet_pton( $ip );
		$subnet_bin = inet_pton( $subnet );

		if ( $ip_bin === false || $subnet_bin === false || strlen( $ip_bin ) !== strlen( $subnet_bin ) )
			return false;

		$bits = (int) $bits;
		$bytes = intdiv( $bits, 8 );
		$remainder_bits = $bits % 8;

		if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) )
			return false;

		if ( $remainder_bits === 0 )
			return true;

		$mask = chr( ( 0xFF << ( 8 - $remainder_bits ) ) & 0xFF );

		return ( $ip_bin[ $bytes ] & $mask ) === ( $subnet_bin[ $bytes ] & $mask );
	}

	function import_avatar_raw( $user_id, $source ){
		$avatar_dir = bp_core_avatar_upload_path() . '/avatars';

		if ( ! file_exists( $avatar_dir ) ) {
			if ( ! wp_mkdir_p( $avatar_dir ) ) {
				return false;
			}
		}

		$avatar_folder_dir = apply_filters( 'bp_core_avatar_folder_dir', $avatar_dir . '/' . $user_id, $user_id, 'user', 'avatars' );

		if ( ! is_dir( $avatar_folder_dir ) ) {
			if ( ! wp_mkdir_p( $avatar_folder_dir ) ) {
				return false;
			}
		}

		$original_file = $avatar_folder_dir . '/import-export-users-customers-bp-avatar-' . $user_id . '.png';

		if ( (string)(int)$source == $source ) {
			$data = file_get_contents( get_attached_file( $source ) );
		} elseif ( preg_match( '#^https?://#i', $source ) ) {
			$data = $this->fetch_avatar_url_safely( $source );
		} else {
			return false;
		}

		if ( empty( $data ) ) {
			return false;
		}

		if ( file_put_contents( $original_file, $data ) ) {
			$avatar_to_crop = str_replace( bp_core_avatar_upload_path(), '', $original_file );

			$crop_args = array(
				'item_id'       => $user_id,
				'original_file' => $avatar_to_crop,
				'crop_x'        => 0,
				'crop_y'        => 0,
			);

			return bp_core_avatar_handle_crop( $crop_args );
		} else {
			return false;
		}
	}
}

$acui_buddypress = new ACUI_Buddypress();
$acui_buddypress->hooks();