<?php

if ( ! defined( 'ABSPATH' ) ) exit; 

class ACUI_CSV_Uploaded{
    function __construct(){
    }

    function hooks(){
        add_action( 'wp_ajax_acui_delete_attachment', array( $this, 'delete_attachment' ) );
		add_action( 'wp_ajax_acui_bulk_delete_attachment', array( $this, 'bulk_delete_attachment' ) );
    }

	static function admin_gui(){
	    $args_old_csv = array( 'post_type'=> 'attachment', 'post_mime_type' => 'text/csv', 'post_status' => 'inherit', 'posts_per_page' => -1 );
		$old_csv_files = new WP_Query( $args_old_csv );

        if( $old_csv_files->found_posts == 0 ){
            _e( 'All correct, there is no file in the attachment library that is a CSV and therefore may contain sensitive information and could be found illicitly.', 'import-users-from-csv-with-meta' );
            return;
        }
        ?>
        <p><?php _e( 'For security reasons you should delete these files, probably they would be visible on the Internet if a bot or someone discover the URL. You can delete each file or maybe you want to delete all CSV files you have uploaded:', 'import-users-from-csv-with-meta' ); ?></p>
        <input type="button" value="<?php _e( 'Delete all CSV files uploaded', 'import-users-from-csv-with-meta' ); ?>" id="bulk_delete_attachment" style="float:right;" />
        <ul>
            <?php while($old_csv_files->have_posts()) : 
                $old_csv_files->the_post();

                if( get_the_date() == "" )
                    $date = "undefined";
                else
                    $date = get_the_date();
            ?>
            <li><a href="<?php echo wp_get_attachment_url( get_the_ID() ); ?>"><?php the_title(); ?></a> <?php echo __( 'uploaded on', 'import-users-from-csv-with-meta' ) . ' ' . $date; ?> <input type="button" value="<?php _e( 'Delete', 'import-users-from-csv-with-meta' ); ?>" class="delete_attachment" attach_id="<?php the_ID(); ?>" /></li>
            <?php endwhile; ?>
            <?php wp_reset_postdata(); ?>
        </ul>

        <script>
        jQuery( document ).ready( function( $ ){
            $( '.delete_attachment' ).click( function(){
                var answer = confirm( "<?php _e( 'Are you sure you want to delete this file?', 'import-users-from-csv-with-meta' ); ?>" );
                if( answer ){
                    var data = {
                        'action': 'acui_delete_attachment',
                        'attach_id': $( this ).attr( "attach_id" ),
                        'security': '<?php echo wp_create_nonce( "codection-security" ); ?>'
                    };

                    $.post(ajaxurl, data, function(response) {
                        if( response != 1 )
                            alert( response );
                        else{
                            alert( "<?php _e( 'File successfully deleted', 'import-users-from-csv-with-meta' ); ?>" );
                            document.location.reload();
                        }
                    });
                }
            });

            $( '#bulk_delete_attachment' ).click( function(){
                var answer = confirm( "<?php _e( 'Are you sure you want to delete ALL CSV files uploaded? There can be CSV files from other plugins.', 'import-users-from-csv-with-meta' ); ?>" );
                if( answer ){
                    var data = {
                        'action': 'acui_bulk_delete_attachment',
                        'security': '<?php echo wp_create_nonce( "codection-security" ); ?>'
                    };

                    $.post(ajaxurl, data, function(response) {
                        if( response != 1 )
                            alert( "<?php _e( 'There were problems deleting the files, please check file permissions', 'import-users-from-csv-with-meta' ); ?>" );
                        else{
                            alert( "<?php _e( 'Files successfully deleted', 'import-users-from-csv-with-meta' ); ?>" );
                            document.location.reload();
                        }
                    });
                }
            });
        } )
        </script>
		<?php
	}

    function delete_attachment() {
		check_ajax_referer( 'codection-security', 'security' );
	
		if( ! current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
            wp_die( __( 'Only users who are allowed to create users can delete CSV attachments.', 'import-users-from-csv-with-meta' ) );
	
		$attach_id = absint( $_POST['attach_id'] );
		$mime_type  = (string) get_post_mime_type( $attach_id );
	
		if( $mime_type != 'text/csv' )
			_e('This plugin can only delete the type of file it manages, i.e. CSV files.', 'import-users-from-csv-with-meta' );
	
		$result = wp_delete_attachment( $attach_id, true );
	
		if( $result === false )
			_e( 'There were problems deleting the file, please check file permissions', 'import-users-from-csv-with-meta' );
		else
			echo 1;
	
		wp_die();
	}

	function bulk_delete_attachment(){
		check_ajax_referer( 'codection-security', 'security' );
	
		if( ! current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
        wp_die( __( 'Only users who are allowed to create users can bulk delete CSV attachments.', 'import-users-from-csv-with-meta' ) );
	
		$args_old_csv = array( 'post_type'=> 'attachment', 'post_mime_type' => 'text/csv', 'post_status' => 'inherit', 'posts_per_page' => -1 );
		$old_csv_files = new WP_Query( $args_old_csv );
		$result = 1;
	
		while($old_csv_files->have_posts()) : 
			$old_csv_files->the_post();
	
			$mime_type  = (string) get_post_mime_type( get_the_ID() );
			if( $mime_type != 'text/csv' )
				wp_die( __('This plugin can only delete the type of file it manages, i.e. CSV files.', 'import-users-from-csv-with-meta' ) );
	
			if( wp_delete_attachment( get_the_ID(), true ) === false )
				$result = 0;
		endwhile;
		
		wp_reset_postdata();
	
		echo $result;
	
		wp_die();
	}
}

$acui_csv_uploaded = new ACUI_CSV_Uploaded();
$acui_csv_uploaded->hooks();
