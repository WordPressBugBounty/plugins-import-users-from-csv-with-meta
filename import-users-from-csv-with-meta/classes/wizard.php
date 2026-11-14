<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ACUI_Wizard{
    const MODE_META = 'acui_import_mode';
    const SAMPLE_ROWS = 200;

    function hooks(){
        add_action( 'wp_ajax_acui_wizard_analyze', array( $this, 'ajax_analyze' ) );
        add_action( 'wp_ajax_acui_wizard_preview', array( $this, 'ajax_preview' ) );
        add_action( 'admin_post_acui_wizard_errors', array( $this, 'download_errors' ) );
        add_action( 'admin_init', array( $this, 'maybe_switch_mode' ) );
    }

    static function is_active(){
        return (bool) apply_filters( 'acui_wizard_active', get_user_meta( get_current_user_id(), self::MODE_META, true ) !== 'classic' );
    }

    static function switch_mode_url( $mode ){
        return wp_nonce_url( add_query_arg( array( 'page' => 'acui', 'tab' => 'homepage', 'acui_mode' => $mode ), admin_url( 'tools.php' ) ), 'acui_switch_mode' );
    }

    function maybe_switch_mode(){
        if( !isset( $_GET['page'], $_GET['acui_mode'] ) || $_GET['page'] !== 'acui' )
            return;

        check_admin_referer( 'acui_switch_mode' );

        if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
            return;

        $mode = ( $_GET['acui_mode'] === 'classic' ) ? 'classic' : 'wizard';
        update_user_meta( get_current_user_id(), self::MODE_META, $mode );

        wp_safe_redirect( admin_url( 'tools.php?page=acui&tab=homepage' ) );
        exit;
    }

    static function enqueue(){
        if( !self::is_active() )
            return;

        $path = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/wizard.js';
        wp_enqueue_script( 'acui_wizard_js', plugins_url( 'assets/wizard.js', dirname( __FILE__ ) ), array( 'jquery', 'acui_import_js', 'select2-js' ), filemtime( $path ), true );
        wp_localize_script( 'acui_wizard_js', 'acui_wizard', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'email_required' => self::email_required(),
            'targets' => self::get_targets(),
            'i18n' => array(
                'choose_file' => __( 'Please choose a file', 'import-users-from-csv-with-meta' ),
                'enter_path' => __( 'Please enter a path to the file', 'import-users-from-csv-with-meta' ),
                'analyzing' => __( 'Reading your file…', 'import-users-from-csv-with-meta' ),
                'continue' => __( 'Continue', 'import-users-from-csv-with-meta' ),
                'error_thrown' => __( 'The file could not be read. Please check that it is a valid CSV and try again.', 'import-users-from-csv-with-meta' ),
                'other' => __( 'Other', 'import-users-from-csv-with-meta' ),
                'custom_field' => __( 'Custom field or add-on column', 'import-users-from-csv-with-meta' ),
                'skip' => __( 'Don\'t import', 'import-users-from-csv-with-meta' ),
                'meta_key' => __( 'Field name', 'import-users-from-csv-with-meta' ),
                'exact' => __( 'Exact match', 'import-users-from-csv-with-meta' ),
                'suggested' => __( 'Suggested', 'import-users-from-csv-with-meta' ),
                'by_content' => __( 'Suggested because the values look like email addresses', 'import-users-from-csv-with-meta' ),
                'by_position' => __( 'Suggested because it is the first column, as in the classic format', 'import-users-from-csv-with-meta' ),
                'chosen' => __( 'Chosen by you', 'import-users-from-csv-with-meta' ),
                'meta' => __( 'Custom field', 'import-users-from-csv-with-meta' ),
                'ignored' => __( 'Ignored', 'import-users-from-csv-with-meta' ),
                'empty_header' => __( '(no name)', 'import-users-from-csv-with-meta' ),
                'no_values' => __( 'empty in the first rows', 'import-users-from-csv-with-meta' ),
                /* translators: 1: file name, 2: number of rows, 3: number of columns, 4: delimiter, 5: character encoding */
                'file_facts' => __( '%1$s: %2$s rows, %3$s columns, delimiter "%4$s", encoding %5$s', 'import-users-from-csv-with-meta' ),
                'need_email' => __( 'Choose which column holds the email. It is required to create users.', 'import-users-from-csv-with-meta' ),
                /* translators: %s: name of the field */
                'duplicated' => __( 'Two columns are imported as the same field (%s). Choose a different one for one of them.', 'import-users-from-csv-with-meta' ),
                'need_meta_key' => __( 'Write the field name for every column imported as a custom field.', 'import-users-from-csv-with-meta' ),
                /* translators: 1: number of imported columns, 2: number of ignored columns */
                'ready' => __( 'Ready. %1$s columns will be imported and %2$s ignored.', 'import-users-from-csv-with-meta' ),
                'id_warning' => __( 'Existing users with this ID will be updated. Use it only if the file comes from this same site.', 'import-users-from-csv-with-meta' ),
                'hint_password' => __( 'Plain text password. Empty cell: new users get a random password, which they can receive in the welcome email or change with "Lost your password?". With a value: that will be their password. Existing users only get a new password if you allow it in More options > Update passwords for existing users.', 'import-users-from-csv-with-meta' ),
                'hint_user_pass' => __( 'Encrypted password exported from another WordPress (it starts with $P$, $wp$ or similar). Users keep the password they already had. Existing users only get it if you allow it in More options > Update passwords for existing users.', 'import-users-from-csv-with-meta' ),
                'hint_role' => __( 'Use the role slug, for example subscriber or customer. Several roles separated by commas. Write no_role for users without any role. Rows with an empty cell are skipped.', 'import-users-from-csv-with-meta' ),
                'by_hash' => __( 'Suggested because the values look like encrypted passwords. If you import them as plain text passwords, users will not be able to log in.', 'import-users-from-csv-with-meta' ),
                'choose_role' => __( 'Choose at least one role, or go back to the default role of this site. If you want users without any role, choose "No role".', 'import-users-from-csv-with-meta' ),
                'preview_button' => __( 'Preview import', 'import-users-from-csv-with-meta' ),
                /* translators: %s: percentage of rows already checked */
                'checking' => __( 'Checking rows… %s', 'import-users-from-csv-with-meta' ),
                /* translators: %s: number of users */
                'import_n' => __( 'Import %s users', 'import-users-from-csv-with-meta' ),
                'result_created' => __( 'New', 'import-users-from-csv-with-meta' ),
                'result_updated' => __( 'Update', 'import-users-from-csv-with-meta' ),
                'result_ignored' => __( 'Skip', 'import-users-from-csv-with-meta' ),
                'result_errors' => __( 'Error', 'import-users-from-csv-with-meta' ),
                'random_username' => __( '(random)', 'import-users-from-csv-with-meta' ),
                /* translators: %s: number of rows */
                'issues_title' => __( '%s rows need your attention', 'import-users-from-csv-with-meta' ),
                /* translators: %s: number of rows */
                'issues_more' => __( 'And %s more.', 'import-users-from-csv-with-meta' ),
                /* translators: %s: row number */
                'row_n' => __( 'Row %s', 'import-users-from-csv-with-meta' ),
                /* translators: %s: number of rows */
                'skipped_info' => __( '%s rows will be skipped', 'import-users-from-csv-with-meta' ),
                'tf_add' => __( 'Transform the value', 'import-users-from-csv-with-meta' ),
                'tf_remove' => __( 'Remove transformation', 'import-users-from-csv-with-meta' ),
                'tf_types' => array(
                    'replace' => __( 'Replace text', 'import-users-from-csv-with-meta' ),
                    'prefix' => __( 'Add before', 'import-users-from-csv-with-meta' ),
                    'suffix' => __( 'Add after', 'import-users-from-csv-with-meta' ),
                    'upper' => __( 'UPPERCASE', 'import-users-from-csv-with-meta' ),
                    'lower' => __( 'lowercase', 'import-users-from-csv-with-meta' ),
                    'empty' => __( 'Value for empty cells', 'import-users-from-csv-with-meta' ),
                ),
                'tf_placeholders' => array(
                    'replace' => array( __( 'Text to find', 'import-users-from-csv-with-meta' ), __( 'Replace with', 'import-users-from-csv-with-meta' ) ),
                    'prefix' => array( __( 'Text to add before', 'import-users-from-csv-with-meta' ), '' ),
                    'suffix' => array( __( 'Text to add after', 'import-users-from-csv-with-meta' ), '' ),
                    'empty' => array( __( 'Value for the empty cells', 'import-users-from-csv-with-meta' ), '' ),
                ),
                'tf_help' => array(
                    'replace' => __( 'Every appearance of the text is replaced, without distinguishing upper and lower case. Leave the second box empty to remove the text.', 'import-users-from-csv-with-meta' ),
                    'prefix' => __( 'Not added to empty cells or to values that already start with this text.', 'import-users-from-csv-with-meta' ),
                    'suffix' => __( 'Not added to empty cells or to values that already end with this text.', 'import-users-from-csv-with-meta' ),
                    'empty' => __( 'Cells with a value are not changed.', 'import-users-from-csv-with-meta' ),
                ),
                'need_tf_text' => __( 'Write the text of each transformation you added, or remove it.', 'import-users-from-csv-with-meta' ),
                'fixed_add' => __( 'Add a field with the same value for every user', 'import-users-from-csv-with-meta' ),
                'fixed_value' => __( 'Value', 'import-users-from-csv-with-meta' ),
                'fixed_remove' => __( 'Remove', 'import-users-from-csv-with-meta' ),
                'need_fixed_value' => __( 'Write the value of each field added with the same value for every user, or remove it.', 'import-users-from-csv-with-meta' ),
            ),
        ) );
    }

    static function email_required(){
        return (bool) apply_filters( 'acui_wizard_email_required', !apply_filters( 'acui_allow_no_email', false ) );
    }

    static function get_targets(){
        $targets = array(
            __( 'WordPress', 'import-users-from-csv-with-meta' ) => array(
                'user_login' => __( 'Username', 'import-users-from-csv-with-meta' ),
                'user_email' => __( 'Email', 'import-users-from-csv-with-meta' ),
                'password' => __( 'Password', 'import-users-from-csv-with-meta' ),
                'user_pass' => __( 'Password hash (from another WordPress)', 'import-users-from-csv-with-meta' ),
                'first_name' => __( 'First name', 'import-users-from-csv-with-meta' ),
                'last_name' => __( 'Last name', 'import-users-from-csv-with-meta' ),
                'display_name' => __( 'Display name', 'import-users-from-csv-with-meta' ),
                'nickname' => __( 'Nickname', 'import-users-from-csv-with-meta' ),
                'user_nicename' => __( 'Nicename (author slug)', 'import-users-from-csv-with-meta' ),
                'user_url' => __( 'Website', 'import-users-from-csv-with-meta' ),
                'description' => __( 'Biographical info', 'import-users-from-csv-with-meta' ),
                'role' => __( 'Role', 'import-users-from-csv-with-meta' ),
                'user_registered' => __( 'Registration date', 'import-users-from-csv-with-meta' ),
                'locale' => __( 'Language', 'import-users-from-csv-with-meta' ),
                'id' => __( 'WordPress user ID', 'import-users-from-csv-with-meta' ),
            ),
        );

        if( class_exists( 'WooCommerce' ) ){
            $woocommerce = array();
            $fields = array(
                'first_name' => __( 'first name', 'import-users-from-csv-with-meta' ),
                'last_name' => __( 'last name', 'import-users-from-csv-with-meta' ),
                'company' => __( 'company', 'import-users-from-csv-with-meta' ),
                'address_1' => __( 'address line 1', 'import-users-from-csv-with-meta' ),
                'address_2' => __( 'address line 2', 'import-users-from-csv-with-meta' ),
                'city' => __( 'city', 'import-users-from-csv-with-meta' ),
                'postcode' => __( 'postcode', 'import-users-from-csv-with-meta' ),
                'state' => __( 'state or province', 'import-users-from-csv-with-meta' ),
                'country' => __( 'country (ISO code)', 'import-users-from-csv-with-meta' ),
                'phone' => __( 'phone', 'import-users-from-csv-with-meta' ),
                'email' => __( 'email', 'import-users-from-csv-with-meta' ),
            );

            foreach( array( 'billing' => __( 'Billing', 'import-users-from-csv-with-meta' ), 'shipping' => __( 'Shipping', 'import-users-from-csv-with-meta' ) ) as $type => $type_label ){
                foreach( $fields as $field => $field_label ){
                    if( $type === 'shipping' && $field === 'email' )
                        continue;

                    $woocommerce[ $type . '_' . $field ] = $type_label . ' ' . $field_label;
                }
            }

            $targets['WooCommerce'] = $woocommerce;
        }

        return apply_filters( 'acui_wizard_targets', $targets );
    }

    static function get_target_keys(){
        $keys = array();

        foreach( self::get_targets() as $group ){
            foreach( $group as $key => $label )
                $keys[] = $key;
        }

        return $keys;
    }

    static function normalize( $text ){
        return preg_replace( '/[^a-z0-9]/', '', strtolower( remove_accents( $text ) ) );
    }

    static function get_aliases(){
        $aliases = array(
            'user_login' => array( 'username', 'user', 'login', 'userlogin', 'usuario', 'nombredeusuario', 'nombreusuario', 'identifiant', 'nomdutilisateur', 'benutzername', 'benutzer', 'nomeutente', 'utente', 'nomedeusuario', 'nomedeutilizador' ),
            'user_email' => array( 'email', 'mail', 'emailaddress', 'useremail', 'correo', 'correoelectronico', 'direcciondecorreo', 'direccionemail', 'courriel', 'adressemail', 'adresseemail', 'adressecourriel', 'emailadresse', 'mailadresse', 'indirizzoemail', 'postaelettronica', 'enderecodeemail', 'correioeletronico' ),
            'password' => array( 'password', 'pass', 'pwd', 'contrasena', 'clave', 'motdepasse', 'passwort', 'kennwort', 'senha' ),
            'first_name' => array( 'firstname', 'givenname', 'forename', 'nombre', 'nombres', 'prenom', 'vorname', 'nome', 'primeironome' ),
            'last_name' => array( 'lastname', 'surname', 'familyname', 'apellido', 'apellidos', 'nom', 'nomdefamille', 'nachname', 'familienname', 'cognome', 'sobrenome', 'ultimonome' ),
            'display_name' => array( 'displayname', 'name', 'fullname', 'nombrecompleto', 'nombreyapellidos', 'nomcomplet', 'vollstandigername', 'nomecompleto' ),
            'nickname' => array( 'nickname', 'nick', 'apodo', 'pseudo', 'surnom', 'spitzname', 'soprannome', 'alcunha' ),
            'user_url' => array( 'website', 'web', 'url', 'site', 'sitioweb', 'paginaweb', 'siteweb', 'siteinternet', 'webseite', 'homepage', 'sitoweb' ),
            'description' => array( 'description', 'bio', 'biography', 'biografia', 'descripcion', 'biographie', 'beschreibung', 'descrizione', 'descricao' ),
            'role' => array( 'role', 'roles', 'userrole', 'rol', 'roles', 'rolle', 'ruolo', 'papel', 'funcao' ),
            'user_registered' => array( 'registered', 'registrationdate', 'dateregistered', 'signupdate', 'fechaalta', 'fechadealta', 'fecharegistro', 'fechaderegistro', 'dateinscription', 'datedinscription', 'registrierungsdatum', 'dataregistrazione', 'datadiregistrazione', 'datacadastro', 'datadecadastro', 'dataderegisto' ),
            'locale' => array( 'locale', 'language', 'idioma', 'langue', 'sprache', 'lingua' ),
        );

        if( class_exists( 'WooCommerce' ) ){
            $aliases = array_merge( $aliases, array(
                'billing_phone' => array( 'phone', 'telephone', 'phonenumber', 'mobile', 'telefono', 'tel', 'movil', 'celular', 'telefon', 'handy', 'cellulare', 'telemovel' ),
                'billing_company' => array( 'company', 'empresa', 'compania', 'societe', 'entreprise', 'firma', 'unternehmen', 'azienda', 'societa' ),
                'billing_address_1' => array( 'address', 'address1', 'street', 'direccion', 'domicilio', 'adresse', 'strasse', 'anschrift', 'indirizzo', 'endereco', 'morada' ),
                'billing_address_2' => array( 'address2' ),
                'billing_city' => array( 'city', 'town', 'ciudad', 'localidad', 'poblacion', 'municipio', 'ville', 'stadt', 'ort', 'citta', 'cidade' ),
                'billing_postcode' => array( 'postcode', 'zip', 'zipcode', 'postalcode', 'codigopostal', 'cp', 'codepostal', 'plz', 'postleitzahl', 'cap', 'cep' ),
                'billing_state' => array( 'state', 'province', 'county', 'region', 'provincia', 'estado', 'etat', 'bundesland', 'regione', 'distrito' ),
                'billing_country' => array( 'country', 'pais', 'pays', 'land', 'paese' ),
            ) );
        }

        $aliases = apply_filters( 'acui_wizard_aliases', $aliases );

        $map = array();
        foreach( $aliases as $target => $list ){
            foreach( (array) $list as $alias ){
                $alias = self::normalize( $alias );
                if( $alias !== '' && !isset( $map[ $alias ] ) )
                    $map[ $alias ] = $target;
            }
        }

        return $map;
    }

    static function suggest( $headers, $samples ){
        $target_keys = self::get_target_keys();
        $target_keys_lower = array_map( 'strtolower', $target_keys );
        $aliases = self::get_aliases();
        $columns = array();
        $used = array();

        foreach( $headers as $i => $header ){
            $lower = strtolower( $header );
            $target = null;
            $match = '';

            if( $lower === 'username' ){
                $target = 'user_login';
                $match = 'exact';
            }
            elseif( $lower === 'email' ){
                $target = 'user_email';
                $match = 'exact';
            }
            elseif( ( $index = array_search( $lower, $target_keys_lower, true ) ) !== false ){
                $target = $target_keys[ $index ];
                $match = 'exact';
            }
            elseif( isset( $aliases[ self::normalize( $header ) ] ) ){
                $target = $aliases[ self::normalize( $header ) ];
                $match = 'suggested';
            }

            if( $target !== null && isset( $used[ $target ] ) )
                $target = null;

            if( $target !== null ){
                $used[ $target ] = $i;
                $columns[ $i ] = array( 'target' => $target, 'match' => $match, 'meta_key' => '' );
            }
            elseif( $header === '' || in_array( $lower, array( 'acui_problem', 'acui_row' ), true ) ){
                $columns[ $i ] = array( 'target' => '__skip', 'match' => 'skip', 'meta_key' => '' );
            }
            else{
                $columns[ $i ] = array( 'target' => '__meta', 'match' => 'meta', 'meta_key' => $header );
            }
        }

        if( !isset( $used['user_email'] ) ){
            foreach( $columns as $i => $column ){
                if( $column['target'] !== '__meta' || empty( $samples[ $i ] ) )
                    continue;

                if( count( array_filter( $samples[ $i ], 'is_email' ) ) === count( $samples[ $i ] ) ){
                    $columns[ $i ] = array( 'target' => 'user_email', 'match' => 'content', 'meta_key' => '' );
                    $used['user_email'] = $i;
                    break;
                }
            }
        }

        if( isset( $used['password'] ) && !isset( $used['user_pass'] ) ){
            $i = $used['password'];

            if( !empty( $samples[ $i ] ) && count( preg_grep( '/^\$(P|H|wp|2y|2a|2b|argon2i|argon2id)\$/', $samples[ $i ] ) ) === count( $samples[ $i ] ) ){
                $columns[ $i ] = array( 'target' => 'user_pass', 'match' => 'hash', 'meta_key' => '' );
                $used['user_pass'] = $i;
                unset( $used['password'] );
            }
        }

        if( !isset( $used['user_login'] ) && isset( $used['user_email'] ) && $used['user_email'] === 1 && isset( $columns[0] ) && $columns[0]['target'] === '__meta' ){
            $columns[0] = array( 'target' => 'user_login', 'match' => 'position', 'meta_key' => '' );
        }

        return $columns;
    }

    static function state_key(){
        return 'acui_wizard_' . get_current_user_id();
    }

    static function discard_state(){
        $state = get_option( self::state_key() );

        if( !is_array( $state ) )
            return;

        $paths = isset( $state['delete'] ) ? (array) $state['delete'] : array();

        if( !empty( $state['prepared'] ) )
            $paths[] = $state['prepared'];

        $paths[] = self::preview_path();
        $paths[] = self::errors_path();

        foreach( array_filter( $paths ) as $path )
            @unlink( $path );

        delete_option( self::state_key() );
    }

    static function detect_encoding( $path ){
        if( !function_exists( 'mb_check_encoding' ) )
            return 'UTF-8';

        $handle = @fopen( $path, 'r' );

        if( !$handle )
            return 'UTF-8';

        $encoding = 'UTF-8';
        $carry = '';

        while( !feof( $handle ) ){
            $chunk = $carry . fread( $handle, 1048576 );
            $carry = '';

            if( !feof( $handle ) ){
                $cut = strrpos( $chunk, "\n" );

                if( $cut !== false ){
                    $carry = substr( $chunk, $cut + 1 );
                    $chunk = substr( $chunk, 0, $cut + 1 );
                }
            }

            if( !mb_check_encoding( $chunk, 'UTF-8' ) ){
                $encoding = 'Windows-1252';
                break;
            }
        }

        fclose( $handle );

        return apply_filters( 'acui_wizard_file_encoding', $encoding, $path );
    }

    static function to_utf8( $value, $encoding ){
        $value = (string) $value;

        if( $encoding === 'UTF-8' || $value === '' )
            return $value;

        if( function_exists( 'mb_convert_encoding' ) )
            return mb_convert_encoding( $value, 'UTF-8', $encoding );

        $converted = function_exists( 'iconv' ) ? @iconv( $encoding, 'UTF-8//TRANSLIT', $value ) : false;

        return ( $converted === false ) ? $value : $converted;
    }

    static function is_blank_row( $data ){
        return !is_array( $data ) || ( count( $data ) === 1 && ( $data[0] === null || trim( (string) $data[0] ) === '' ) );
    }

    function ajax_analyze(){
        check_ajax_referer( 'codection-security', 'security' );

        if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
            wp_send_json_error( array( 'message' => __( 'Only users who are allowed to create users can import them.', 'import-users-from-csv-with-meta' ) ) );

        global $acui_import;

        self::discard_state();

        $file = '';
        $name = '';
        $delete = array();

        if( !empty( $_FILES['uploadfile']['error'] ) && (int) $_FILES['uploadfile']['error'] !== UPLOAD_ERR_NO_FILE )
            wp_send_json_error( array( 'message' => ACUI_Private_Files::upload_error_message( $_FILES['uploadfile']['error'] ) ) );

        if( !empty( $_FILES['uploadfile']['tmp_name'] ) ){
            $name = sanitize_file_name( wp_unslash( $_FILES['uploadfile']['name'] ) );
            $extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
            $allowed_extensions = apply_filters( 'acui_allowed_upload_extensions', array( 'csv' ) );

            if( !in_array( $extension, $allowed_extensions, true ) ){
                $addon_url = $acui_import->get_file_formats_addon_url( $extension );

                if( $addon_url )
                    wp_send_json_error( array(
                        'message' => __( 'Invalid file type. XLSX, XLS, ODS and XML files can be imported with the File Formats addon. Do you want to see it?', 'import-users-from-csv-with-meta' ),
                        'addon_url' => $addon_url,
                    ) );

                wp_send_json_error( array( 'message' => __( 'Invalid file type.', 'import-users-from-csv-with-meta' ) ) );
            }

            $file = ACUI_Private_Files::save_upload( $_FILES['uploadfile'], 'acui-import-' . get_current_user_id() . '-' . ACUI_Private_Files::random_token() . '.' . $extension );

            if( is_wp_error( $file ) )
                wp_send_json_error( array( 'message' => $file->get_error_message() ) );

            $delete[] = $file;
        }
        elseif( !empty( $_POST['path_to_file'] ) ){
            $path = trim( wp_unslash( $_POST['path_to_file'] ) );
            $is_url = ( wp_http_validate_url( $path ) !== false );

            ob_start();
            $file = $acui_import->manage_file_upload( $path );
            $output = ob_get_clean();

            if( empty( $file ) ){
                $message = preg_match( '#<p>(.*?)</p>#s', $output, $matches ) ? $matches[1] : $output;
                wp_send_json_error( array( 'message' => trim( wp_strip_all_tags( $message ) ) ) );
            }

            $name = $is_url ? wp_basename( (string) wp_parse_url( $path, PHP_URL_PATH ) ) : wp_basename( $file );

            if( $is_url )
                $delete[] = $file;
        }
        else{
            wp_send_json_error( array( 'message' => __( 'Please choose a file', 'import-users-from-csv-with-meta' ) ) );
        }

        $readable = apply_filters( 'acui_import_file_path', $file, wp_unslash( $_POST ) );

        $readable_extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

        if( $readable === $file && in_array( $readable_extension, array( 'xlsx', 'xls', 'ods', 'odf', 'xml' ), true ) ){
            foreach( $delete as $path )
                @unlink( $path );

            if( $readable_extension === 'xml' )
                wp_send_json_error( array( 'message' => __( 'The XML file could not be read. Check that it is a valid XML file with one element for each user, or save it as CSV and try again.', 'import-users-from-csv-with-meta' ) ) );

            wp_send_json_error( array( 'message' => __( 'The spreadsheet could not be read. Check that it is not damaged or protected with a password, or save it as CSV and try again.', 'import-users-from-csv-with-meta' ) ) );
        }

        if( $readable !== $file )
            $delete[] = $readable;

        $delete = array_values( array_unique( $delete ) );
        $analysis = $this->analyze_file( $readable );

        if( is_wp_error( $analysis ) ){
            foreach( $delete as $path )
                @unlink( $path );

            wp_send_json_error( array( 'message' => $analysis->get_error_message() ) );
        }

        $token = ACUI_Private_Files::random_token();

        update_option( self::state_key(), array(
            'token' => $token,
            'name' => $name !== '' ? $name : wp_basename( $file ),
            'path' => $readable,
            'delete' => $delete,
            'delimiter' => $analysis['delimiter'],
            'encoding' => $analysis['encoding'],
            'columns' => count( $analysis['headers'] ),
            'rows' => $analysis['rows'],
            'time' => time(),
        ), false );

        $suggestions = self::suggest( $analysis['headers'], $analysis['samples'] );
        $columns = array();

        foreach( $analysis['headers'] as $i => $header ){
            $columns[] = array_merge( array( 'header' => $header, 'samples' => $analysis['samples'][ $i ] ), $suggestions[ $i ] );
        }

        wp_send_json_success( array(
            'token' => $token,
            'name' => $name,
            'rows' => $analysis['rows'],
            'delimiter' => $analysis['delimiter'] === "\t" ? 'Tab' : $analysis['delimiter'],
            'encoding' => $analysis['encoding'],
            'columns' => $columns,
        ) );
    }

    function analyze_file( $path ){
        if( empty( $path ) || !is_readable( $path ) )
            return new WP_Error( 'acui_wizard_unreadable', __( 'The file could not be read.', 'import-users-from-csv-with-meta' ) );

        $delimiter = ACUIHelper()->detect_delimiter( $path );
        $encoding = self::detect_encoding( $path );
        $escape = ACUIHelper()->get_csv_escape();
        $manager = new SplFileObject( $path );
        $header = $manager->fgetcsv( $delimiter, '"', $escape );

        if( self::is_blank_row( $header ) )
            return new WP_Error( 'acui_wizard_empty', __( 'The first row of the file is empty. It must contain the name of each column.', 'import-users-from-csv-with-meta' ) );

        $header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );
        $headers = array();

        foreach( $header as $cell )
            $headers[] = trim( self::to_utf8( $cell, $encoding ) );

        $samples = array_fill( 0, count( $headers ), array() );
        $rows = 0;

        while( !$manager->eof() ){
            $data = $manager->fgetcsv( $delimiter, '"', $escape );

            if( self::is_blank_row( $data ) )
                continue;

            $rows++;

            if( $rows > self::SAMPLE_ROWS )
                continue;

            foreach( $data as $i => $value ){
                if( !isset( $samples[ $i ] ) || count( $samples[ $i ] ) >= 3 )
                    continue;

                $value = trim( self::to_utf8( $value, $encoding ) );

                if( $value !== '' )
                    $samples[ $i ][] = function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $value, 0, 60, '…' ) : substr( $value, 0, 60 );
            }
        }

        if( $rows === 0 )
            return new WP_Error( 'acui_wizard_no_rows', __( 'The file only has the header row. Add at least one user below it.', 'import-users-from-csv-with-meta' ) );

        return array( 'delimiter' => $delimiter, 'encoding' => $encoding, 'headers' => $headers, 'samples' => $samples, 'rows' => $rows );
    }

    static function get_valid_state( $data ){
        if( empty( $data['acui_wizard_token'] ) || !self::is_active() )
            return false;

        $state = get_option( self::state_key() );

        if( !is_array( $state ) || empty( $state['token'] ) || !hash_equals( $state['token'], (string) $data['acui_wizard_token'] ) || empty( $state['path'] ) || !is_readable( $state['path'] ) )
            return new WP_Error( 'acui_wizard_expired', __( 'The file chosen in the first step is no longer available. Please choose it again.', 'import-users-from-csv-with-meta' ) );

        return $state;
    }

    static function get_transform_types(){
        return array( 'replace', 'prefix', 'suffix', 'upper', 'lower', 'empty' );
    }

    static function clean_value( $value ){
        return str_replace( "\0", '', strip_tags( is_scalar( $value ) ? (string) $value : '' ) );
    }

    static function get_transform( $data, $index ){
        $type = isset( $data['acui_tf'][ $index ] ) ? sanitize_key( $data['acui_tf'][ $index ] ) : '';

        if( !in_array( $type, self::get_transform_types(), true ) )
            return null;

        $transform = array(
            'type' => $type,
            'a' => isset( $data['acui_tf_a'][ $index ] ) ? self::clean_value( $data['acui_tf_a'][ $index ] ) : '',
            'b' => isset( $data['acui_tf_b'][ $index ] ) ? self::clean_value( $data['acui_tf_b'][ $index ] ) : '',
        );

        if( !in_array( $type, array( 'upper', 'lower' ), true ) && $transform['a'] === '' )
            return new WP_Error( 'acui_wizard_transform', __( 'Write the text of each transformation you added, or remove it.', 'import-users-from-csv-with-meta' ) );

        return $transform;
    }

    static function apply_transform( $value, $transform ){
        if( empty( $transform ) )
            return $value;

        $value = (string) $value;
        $a = $transform['a'];
        $mb = function_exists( 'mb_strtoupper' );

        switch( $transform['type'] ){
            case 'replace':
                return str_ireplace( $a, $transform['b'], $value );

            case 'prefix':
                if( trim( $value ) === '' || stripos( $value, $a ) === 0 )
                    return $value;
                return $a . $value;

            case 'suffix':
                if( trim( $value ) === '' || ( strlen( $value ) >= strlen( $a ) && strcasecmp( substr( $value, -strlen( $a ) ), $a ) === 0 ) )
                    return $value;
                return $value . $a;

            case 'upper':
                return $mb ? mb_strtoupper( $value, 'UTF-8' ) : strtoupper( $value );

            case 'lower':
                return $mb ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );

            case 'empty':
                return trim( $value ) === '' ? $a : $value;
        }

        return $value;
    }

    static function get_mapping( $data, $state ){
        $map = ( isset( $data['acui_map'] ) && is_array( $data['acui_map'] ) ) ? $data['acui_map'] : array();
        $meta_keys = ( isset( $data['acui_map_meta'] ) && is_array( $data['acui_map_meta'] ) ) ? $data['acui_map_meta'] : array();
        $username_mode = ( isset( $data['acui_username_mode'] ) && $data['acui_username_mode'] === 'random' ) ? 'random' : 'email';
        $target_keys = self::get_target_keys();

        $mapping = array( 'email' => null, 'login' => null, 'others' => array(), 'username_mode' => $username_mode, 'transforms' => array(), 'fixed' => array() );
        $names = array();

        for( $i = 0; $i < (int) $state['columns']; $i++ ){
            $target = isset( $map[ $i ] ) ? sanitize_text_field( $map[ $i ] ) : '__skip';

            if( $target === '__skip' )
                continue;

            if( $target === '__meta' ){
                $name = isset( $meta_keys[ $i ] ) ? trim( wp_strip_all_tags( $meta_keys[ $i ] ) ) : '';

                if( $name === '' )
                    return new WP_Error( 'acui_wizard_meta_key', __( 'Write the field name for every column imported as a custom field.', 'import-users-from-csv-with-meta' ) );
            }
            elseif( in_array( $target, $target_keys, true ) ){
                $name = $target;
            }
            else{
                continue;
            }

            $lower = strtolower( $name );

            if( $lower === 'username' )
                $lower = 'user_login';
            elseif( $lower === 'email' )
                $lower = 'user_email';

            if( isset( $names[ $lower ] ) )
                return new WP_Error( 'acui_wizard_duplicated', sprintf( __( 'Two columns are imported as the same field (%s). Choose a different one for one of them.', 'import-users-from-csv-with-meta' ), $name ) );

            $names[ $lower ] = true;

            $transform = self::get_transform( $data, $i );

            if( is_wp_error( $transform ) )
                return $transform;

            if( $transform )
                $mapping['transforms'][ $i ] = $transform;

            if( $lower === 'user_email' )
                $mapping['email'] = $i;
            elseif( $lower === 'user_login' )
                $mapping['login'] = $i;
            else
                $mapping['others'][ $i ] = $name;
        }

        $fixed_keys = ( isset( $data['acui_fixed_key'] ) && is_array( $data['acui_fixed_key'] ) ) ? $data['acui_fixed_key'] : array();

        foreach( $fixed_keys as $n => $key ){
            $key = sanitize_text_field( $key );

            if( $key === '' )
                continue;

            if( $key === '__meta' )
                $name = isset( $data['acui_fixed_meta'][ $n ] ) ? trim( wp_strip_all_tags( $data['acui_fixed_meta'][ $n ] ) ) : '';
            elseif( in_array( $key, $target_keys, true ) && !in_array( $key, array( 'user_login', 'user_email' ), true ) )
                $name = $key;
            else
                continue;

            if( $name === '' )
                return new WP_Error( 'acui_wizard_meta_key', __( 'Write the field name for every column imported as a custom field.', 'import-users-from-csv-with-meta' ) );

            $lower = strtolower( $name );

            if( in_array( $lower, array( 'username', 'user_login', 'email', 'user_email' ), true ) )
                continue;

            if( isset( $names[ $lower ] ) )
                return new WP_Error( 'acui_wizard_duplicated', sprintf( __( 'Two columns are imported as the same field (%s). Choose a different one for one of them.', 'import-users-from-csv-with-meta' ), $name ) );

            $value = isset( $data['acui_fixed_value'][ $n ] ) ? self::clean_value( $data['acui_fixed_value'][ $n ] ) : '';

            if( $value === '' )
                return new WP_Error( 'acui_wizard_fixed_value', __( 'Write the value of each field added with the same value for every user, or remove it.', 'import-users-from-csv-with-meta' ) );

            $names[ $lower ] = true;
            $mapping['fixed'][] = array( 'name' => $name, 'value' => $value );
        }

        if( $mapping['email'] === null && self::email_required() )
            return new WP_Error( 'acui_wizard_email', __( 'Choose which column holds the email. It is required to create users.', 'import-users-from-csv-with-meta' ) );

        return apply_filters( 'acui_wizard_mapping', $mapping, $data, $state );
    }

    static function prepare_file( $data ){
        $state = self::get_valid_state( $data );

        if( !is_array( $state ) )
            return $state;

        $mapping = self::get_mapping( $data, $state );

        if( is_wp_error( $mapping ) )
            return $mapping;

        $hash = md5( serialize( $mapping ) );

        if( !empty( $state['prepared'] ) && $state['prepared_hash'] === $hash && is_readable( $state['prepared'] ) )
            return $state['prepared'];

        if( !empty( $state['prepared'] ) )
            @unlink( $state['prepared'] );

        $output_path = ACUI_Private_Files::get_path( 'acui-import-' . get_current_user_id() . '-' . ACUI_Private_Files::random_token() . '.csv' );
        $changed = self::write_mapped_file( $state, $mapping, $output_path );

        if( is_wp_error( $changed ) )
            return $changed;

        $state['prepared'] = $output_path;
        $state['prepared_hash'] = $hash;
        $state['changed_columns'] = $changed;
        update_option( self::state_key(), $state, false );

        return $output_path;
    }

    static function write_mapped_file( $state, $mapping, $output_path ){
        $output = @fopen( $output_path, 'w' );

        if( !$output )
            return new WP_Error( 'acui_wizard_write', ACUI_Private_Files::save_error( $output_path ) );

        $escape = ACUIHelper()->get_csv_escape();
        $encoding = isset( $state['encoding'] ) ? $state['encoding'] : 'UTF-8';
        $columns = (int) $state['columns'];
        $others = $mapping['others'];
        $fixed = $mapping['fixed'];
        $transforms = $mapping['transforms'];
        $manager = new SplFileObject( $state['path'] );
        $manager->fgetcsv( $state['delimiter'], '"', $escape );
        $header = array_merge( array( 'Username', 'Email' ), array_values( $others ), wp_list_pluck( $fixed, 'name' ) );
        $changed = array();

        foreach( array_keys( $others ) as $position => $i ){
            if( isset( $transforms[ $i ] ) && strtolower( $others[ $i ] ) !== 'role' )
                $changed[] = $position + 2;
        }

        foreach( array_keys( $fixed ) as $position ){
            if( strtolower( $fixed[ $position ]['name'] ) !== 'role' )
                $changed[] = count( $others ) + 2 + $position;
        }

        fputcsv( $output, $header, ',', '"', $escape );

        while( !$manager->eof() ){
            $row = $manager->fgetcsv( $state['delimiter'], '"', $escape );

            if( self::is_blank_row( $row ) )
                continue;

            if( count( $row ) !== $columns ){
                fputcsv( $output, array_fill( 0, count( $header ) + 1, '' ), ',', '"', $escape );
                continue;
            }

            $row = array_map( function( $value ) use ( $encoding ){
                return self::to_utf8( $value, $encoding );
            }, $row );

            foreach( $transforms as $i => $transform )
                $row[ $i ] = self::apply_transform( $row[ $i ], $transform );

            $email = ( $mapping['email'] === null ) ? '' : $row[ $mapping['email'] ];

            if( $mapping['login'] !== null && trim( $row[ $mapping['login'] ] ) !== '' )
                $login = $row[ $mapping['login'] ];
            else
                $login = ( $mapping['username_mode'] === 'email' ) ? $email : '';

            $line = array( $login, $email );

            foreach( $others as $i => $name )
                $line[] = $row[ $i ];

            foreach( $fixed as $field )
                $line[] = $field['value'];

            fputcsv( $output, $line, ',', '"', $escape );
        }

        fclose( $output );
        unset( $manager );

        return $changed;
    }

    static function header_key( $header ){
        $header = trim( (string) $header );

        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $header, 'UTF-8' ) : strtolower( $header );
    }

    static function rules_to_data( $rules, $headers ){
        $data = array( 'acui_map' => array(), 'acui_map_meta' => array(), 'acui_tf' => array(), 'acui_tf_a' => array(), 'acui_tf_b' => array(), 'acui_fixed_key' => array(), 'acui_fixed_meta' => array(), 'acui_fixed_value' => array() );
        $data['acui_username_mode'] = ( isset( $rules['username_mode'] ) && $rules['username_mode'] === 'random' ) ? 'random' : 'email';
        $positions = array();
        $missing = array();

        foreach( $headers as $i => $header ){
            $key = self::header_key( $header );

            if( $key !== '' && !isset( $positions[ $key ] ) )
                $positions[ $key ] = $i;
        }

        $columns = ( isset( $rules['columns'] ) && is_array( $rules['columns'] ) ) ? $rules['columns'] : array();

        foreach( $columns as $column ){
            if( empty( $column['header'] ) || empty( $column['target'] ) || $column['target'] === '__skip' )
                continue;

            $key = self::header_key( $column['header'] );

            if( !isset( $positions[ $key ] ) ){
                $missing[] = $column['header'];
                continue;
            }

            $i = $positions[ $key ];
            $data['acui_map'][ $i ] = $column['target'];
            $data['acui_map_meta'][ $i ] = isset( $column['meta'] ) ? $column['meta'] : '';

            if( !empty( $column['tf']['type'] ) ){
                $data['acui_tf'][ $i ] = $column['tf']['type'];
                $data['acui_tf_a'][ $i ] = isset( $column['tf']['a'] ) ? $column['tf']['a'] : '';
                $data['acui_tf_b'][ $i ] = isset( $column['tf']['b'] ) ? $column['tf']['b'] : '';
            }
        }

        $fixed = ( isset( $rules['fixed'] ) && is_array( $rules['fixed'] ) ) ? $rules['fixed'] : array();

        foreach( array_values( $fixed ) as $n => $field ){
            $data['acui_fixed_key'][ $n ] = isset( $field['key'] ) ? $field['key'] : '';
            $data['acui_fixed_meta'][ $n ] = isset( $field['meta'] ) ? $field['meta'] : '';
            $data['acui_fixed_value'][ $n ] = isset( $field['value'] ) ? $field['value'] : '';
        }

        return array( 'data' => $data, 'missing' => $missing );
    }

    static function build_mapped_file( $path, $rules ){
        $wizard = new self();
        $analysis = $wizard->analyze_file( $path );

        if( is_wp_error( $analysis ) )
            return $analysis;

        $converted = self::rules_to_data( $rules, $analysis['headers'] );

        if( !empty( $converted['missing'] ) )
            return new WP_Error( 'acui_mapped_missing', sprintf( __( 'These columns of the mapping are not in the file: %s', 'import-users-from-csv-with-meta' ), implode( ', ', $converted['missing'] ) ) );

        $state = array( 'path' => $path, 'delimiter' => $analysis['delimiter'], 'encoding' => $analysis['encoding'], 'columns' => count( $analysis['headers'] ) );
        $mapping = self::get_mapping( $converted['data'], $state );

        if( is_wp_error( $mapping ) )
            return $mapping;

        $output_path = ACUI_Private_Files::get_path( 'acui-mapped-' . ACUI_Private_Files::random_token() . '.csv' );
        $changed = self::write_mapped_file( $state, $mapping, $output_path );

        if( is_wp_error( $changed ) ){
            @unlink( $output_path );
            return $changed;
        }

        return $output_path;
    }

    static function describe_file( $path ){
        $wizard = new self();
        $analysis = $wizard->analyze_file( $path );

        if( is_wp_error( $analysis ) )
            return $analysis;

        return array(
            'headers' => $analysis['headers'],
            'samples' => $analysis['samples'],
            'rows' => $analysis['rows'],
            'suggestions' => self::suggest( $analysis['headers'], $analysis['samples'] ),
        );
    }

    static function build_import_file( $data ){
        $path = self::prepare_file( $data );

        if( !is_string( $path ) )
            return $path;

        $state = get_option( self::state_key() );

        if( is_array( $state ) ){
            $state['prepared'] = '';
            update_option( self::state_key(), $state, false );
        }

        self::discard_state();

        return $path;
    }

    static function preview_path(){
        $state = get_option( self::state_key() );

        if( !is_array( $state ) || empty( $state['token'] ) )
            return '';

        return ACUI_Private_Files::get_path( 'acui-preview-' . get_current_user_id() . '-' . substr( md5( $state['token'] ), 0, 16 ) . '.json' );
    }

    static function errors_path(){
        $state = get_option( self::state_key() );

        if( !is_array( $state ) || empty( $state['token'] ) )
            return '';

        return ACUI_Private_Files::get_path( 'acui-preview-errors-' . get_current_user_id() . '-' . substr( md5( $state['token'] . 'errors' ), 0, 16 ) . '.csv' );
    }

    function ajax_preview(){
        check_ajax_referer( 'codection-security', 'security' );

        if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
            wp_send_json_error( array( 'message' => __( 'Only users who are allowed to create users can import them.', 'import-users-from-csv-with-meta' ) ) );

        $data = wp_unslash( $_POST );
        $offset = isset( $data['offset'] ) ? absint( $data['offset'] ) : 0;
        $path = self::prepare_file( $data );

        if( $path === false )
            wp_send_json_error( array( 'message' => __( 'The file chosen in the first step is no longer available. Please choose it again.', 'import-users-from-csv-with-meta' ) ) );

        if( is_wp_error( $path ) )
            wp_send_json_error( array( 'message' => $path->get_error_message() ) );

        global $acui_import;
        $settings = $acui_import->prepare_settings( $data );
        $preview_path = self::preview_path();
        $errors_path = self::errors_path();
        $escape = ACUIHelper()->get_csv_escape();
        $manager = new SplFileObject( $path );
        $headers = $manager->fgetcsv( ',', '"', $escape );
        $headers_lower = array_map( 'strtolower', $headers );
        $wizard_state = get_option( self::state_key() );
        $changed_columns = ( is_array( $wizard_state ) && isset( $wizard_state['changed_columns'] ) ) ? (array) $wizard_state['changed_columns'] : array();

        if( $offset === 0 ){
            $preview = array(
                'row' => 1,
                'counts' => array( 'created' => 0, 'updated' => 0, 'ignored' => 0, 'errors' => 0 ),
                'totals' => array( 'errors' => 0, 'warnings' => 0 ),
                'issues' => array(),
                'issues_count' => 0,
                'sample' => array(),
                'emails' => array(),
                'logins' => array(),
                'registered' => array(),
                'roles_appeared' => $settings['role_default'],
            );

            $errors_file = @fopen( $errors_path, 'w' );
            if( $errors_file ){
                fputcsv( $errors_file, array_merge( array( 'acui_problem', 'acui_row' ), $headers ), ',', '"', $escape );
                fclose( $errors_file );
            }
        }
        else{
            $preview = json_decode( (string) @file_get_contents( $preview_path ), true );

            if( !is_array( $preview ) )
                wp_send_json_error( array( 'message' => __( 'The preview could not continue. Please go back one step and try again.', 'import-users-from-csv-with-meta' ) ) );

            $manager->fseek( $offset );
        }

        $positions = array(
            'role' => array_search( 'role', $headers_lower, true ),
            'id' => array_search( 'id', $headers_lower, true ),
            'password' => array_search( 'password', $headers_lower, true ),
        );

        $time_start = microtime( true );
        $processed = 0;
        $done = false;
        $errors_file = @fopen( $errors_path, 'a' );

        while( true ){
            $row = $manager->fgetcsv( ',', '"', $escape );

            if( self::is_blank_row( $row ) ){
                if( $manager->eof() ){
                    $done = true;
                    break;
                }
                continue;
            }

            $preview['row']++;
            $result = $this->simulate_row( $row, count( $headers ), $positions, $settings, $preview );
            $preview['counts'][ $result['result'] ]++;

            if( $result['type'] === 'error' )
                $preview['totals']['errors']++;
            elseif( $result['type'] === 'warning' )
                $preview['totals']['warnings']++;

            if( !empty( $result['user_id'] ) )
                $preview['registered'][] = (int) $result['user_id'];

            if( $result['message'] !== '' ){
                $preview['issues_count']++;

                if( count( $preview['issues'] ) < 100 )
                    $preview['issues'][] = array( 'row' => $preview['row'], 'type' => $result['type'], 'message' => $result['message'] );

                if( $errors_file && $result['result'] === 'errors' )
                    fputcsv( $errors_file, array_merge( array( $result['message'], $preview['row'] ), $row ), ',', '"', $escape );
            }

            if( count( $preview['sample'] ) < 10 ){
                $changes = array();

                foreach( $changed_columns as $position ){
                    if( isset( $headers[ $position ], $row[ $position ] ) && $row[ $position ] !== '' )
                        $changes[] = $headers[ $position ] . ': ' . $row[ $position ];
                }

                $preview['sample'][] = array(
                    'row' => $preview['row'],
                    'email' => isset( $row[1] ) ? $row[1] : '',
                    'changes' => $changes,
                    'login' => $result['login'],
                    'role' => $result['role'],
                    'result' => $result['result'],
                    'type' => $result['type'],
                    'message' => $result['message'],
                );
            }

            $processed++;

            if( $processed >= 500 || ( microtime( true ) - $time_start ) > 10 )
                break;
        }

        if( $errors_file )
            fclose( $errors_file );

        if( !$done && $manager->eof() )
            $done = true;

        $state = get_option( self::state_key() );
        $total = max( 1, isset( $state['rows'] ) ? (int) $state['rows'] : 1 );

        if( !$done ){
            $next_offset = $manager->ftell();
            file_put_contents( $preview_path, wp_json_encode( $preview ) );

            wp_send_json_success( array(
                'done' => false,
                'offset' => $next_offset,
                'percentage' => min( 99, floor( ( ( $preview['row'] - 1 ) / $total ) * 100 ) ),
            ) );
        }

        @unlink( $preview_path );

        wp_send_json_success( array(
            'done' => true,
            'counts' => $preview['counts'],
            'issues' => $preview['issues'],
            'issues_count' => $preview['issues_count'],
            'sample' => $preview['sample'],
            'not_present' => $this->simulate_not_present( $settings, $preview ),
            'errors_url' => $preview['counts']['errors'] > 0 ? wp_nonce_url( admin_url( 'admin-post.php?action=acui_wizard_errors' ), 'acui_wizard_errors' ) : '',
        ) );
    }

    function simulate_row( $row, $columns, $positions, $settings, &$preview ){
        $result = array( 'result' => 'created', 'type' => '', 'message' => '', 'user_id' => 0, 'login' => '', 'role' => '' );

        if( count( $row ) !== $columns ){
            $result['result'] = 'errors';
            $result['type'] = 'error';
            $result['message'] = __( 'Row does not have the same columns as the header, we are going to ignore this row', 'import-users-from-csv-with-meta' );
            return $result;
        }

        $username = trim( (string) $row[0] );
        $original_email = trim( (string) $row[1] );
        $email = $original_email;
        $generated_email = ( $email === '' && apply_filters( 'acui_allow_no_email', false ) );
        $result['login'] = $username;

        if( $positions['role'] !== false ){
            $cells = array_map( 'strtolower', array_map( 'trim', explode( ',', (string) $row[ $positions['role'] ] ) ) );
            $result['role'] = implode( ', ', $cells );

            if( !in_array( 'no_role', $cells, true ) ){
                $checked = ACUIHelper()->check_roles( $cells );

                if( !empty( $checked['not_existing'] ) ){
                    $role = reset( $checked['not_existing'] );
                    $result['result'] = 'errors';
                    $result['type'] = 'error';
                    $result['message'] = isset( $checked['suggestions'][ $role ] )
                        ? sprintf( __( 'The role "%1$s" does not exist on this site, but there is a role named like that whose slug is "%2$s": you have to use the slug of the role and not its name', 'import-users-from-csv-with-meta' ), $role, $checked['suggestions'][ $role ] )
                        : sprintf( __( 'The role "%1$s" does not exist on this site, these are the roles you can use: %2$s', 'import-users-from-csv-with-meta' ), $role, implode( ', ', array_keys( ACUIHelper()->get_editable_roles( false ) ) ) );
                    return $result;
                }

                if( !empty( $checked['not_editable'] ) ){
                    $result['result'] = 'errors';
                    $result['type'] = 'error';
                    $result['message'] = sprintf( __( 'The role "%1$s" exists but you are not allowed to assign it, probably because another plugin or your own role limits which roles you can manage: you can only assign %2$s', 'import-users-from-csv-with-meta' ), implode( '", "', $checked['not_editable'] ), implode( ', ', array_keys( ACUIHelper()->get_editable_roles( false ) ) ) );
                    return $result;
                }

                if( empty( $checked['roles'] ) ){
                    $result['result'] = 'errors';
                    $result['type'] = 'error';
                    $result['message'] = __( 'The role column is empty on this row, you have to write at least one role or use "no_role" if you do not want to assign any', 'import-users-from-csv-with-meta' );
                    return $result;
                }

                $roles = $checked['roles'];
            }
            else{
                $roles = array();
            }
        }
        else{
            $roles = $settings['role_default'];
            $result['role'] = implode( ', ', $roles );
        }

        $preview['roles_appeared'] = array_values( array_unique( array_intersect( $preview['roles_appeared'], $roles ) ) );

        if( $email !== '' && sanitize_email( $email ) === '' ){
            $result['result'] = 'errors';
            $result['type'] = 'error';
            $result['message'] = sprintf( __( 'Invalid email "%s"', 'import-users-from-csv-with-meta' ), $email );
            return $result;
        }

        if( $email === '' && !$generated_email ){
            $result['result'] = 'errors';
            $result['type'] = 'error';
            $result['message'] = __( 'Email not specified', 'import-users-from-csv-with-meta' );
            return $result;
        }

        $email_key = strtolower( $email );
        $login_key = strtolower( $username );

        if( ( !$generated_email && isset( $preview['emails'][ $email_key ] ) ) || ( $username !== '' && isset( $preview['logins'][ $login_key ] ) ) ){
            $first = ( !$generated_email && isset( $preview['emails'][ $email_key ] ) ) ? $preview['emails'][ $email_key ] : $preview['logins'][ $login_key ];
            $result['result'] = ( $settings['update_existing_users'] === 'no' ) ? 'ignored' : 'updated';
            $result['type'] = 'warning';
            $result['message'] = sprintf( __( 'This user is already in row %d of the file. This row will update the same user again.', 'import-users-from-csv-with-meta' ), $first );
            return $result;
        }

        if( !$generated_email )
            $preview['emails'][ $email_key ] = $preview['row'];

        if( $username !== '' )
            $preview['logins'][ $login_key ] = $preview['row'];

        $id = ( $positions['id'] === false ) ? '' : trim( (string) $row[ $positions['id'] ] );

        if( $id !== '' ){
            if( ACUIHelper()->user_id_exists( $id ) ){
                $user = get_user_by( 'ID', $id );
                $result['user_id'] = $user->ID;

                if( $settings['update_existing_users'] === 'no' ){
                    $result['result'] = 'ignored';
                    $result['type'] = 'notice';
                    $result['message'] = sprintf( __( 'User with ID "%s" exists, we ignore it', 'import-users-from-csv-with-meta' ), $id );
                    return $result;
                }

                if( $user->user_login !== $username ){
                    $result['user_id'] = 0;
                    $result['result'] = 'errors';
                    $result['type'] = 'error';
                    $result['message'] = sprintf( __( 'Problems with ID "%s" username is not the same in the CSV and in database', 'import-users-from-csv-with-meta' ), $id );
                    return $result;
                }

                return $this->simulate_email_change( $result, $user, $email, $original_email, $settings );
            }

            return $result;
        }

        if( $username !== '' && ( $user = get_user_by( 'login', $username ) ) ){
            $result['user_id'] = $user->ID;

            if( $settings['update_existing_users'] === 'no' ){
                $result['result'] = 'ignored';
                $result['type'] = 'notice';
                $result['message'] = sprintf( __( 'User with username "%s" exists, we ignore it', 'import-users-from-csv-with-meta' ), $username );
                return $result;
            }

            return $this->simulate_email_change( $result, $user, $email, $original_email, $settings );
        }

        if( !$generated_email && $settings['allow_multiple_accounts'] === 'not_allowed' && ( $user = get_user_by( 'email', $email ) ) ){
            $result['user_id'] = $user->ID;
            $result['type'] = 'warning';

            if( $settings['update_existing_users'] === 'no' ){
                $result['result'] = 'ignored';
                $result['message'] = sprintf( __( 'The email %s already exists in the system but is used by a different user than the one indicated in the CSV', 'import-users-from-csv-with-meta' ), $email );
                return $result;
            }

            $result['result'] = 'updated';
            $result['message'] = sprintf( __( 'User already exists as: %s (in this CSV file, it is called: %s)', 'import-users-from-csv-with-meta' ), $user->user_login, $username );
            return $result;
        }

        if( $username !== '' ){
            $sanitized = sanitize_user( $username, true );
            $illegal = array_map( 'strtolower', (array) apply_filters( 'illegal_user_logins', array() ) );
            $message = '';

            if( $sanitized === '' )
                $message = __( 'Cannot create a user with an empty login name.' );
            elseif( mb_strlen( $sanitized ) > 60 )
                $message = __( 'Username may not be longer than 60 characters.' );
            elseif( in_array( strtolower( $sanitized ), $illegal, true ) )
                $message = __( 'Sorry, that username is not allowed.' );

            if( $message !== '' ){
                $result['result'] = 'errors';
                $result['type'] = 'error';
                $result['message'] = sprintf( __( 'Problems with user: "%s" does not exist, error: %s', 'import-users-from-csv-with-meta' ), $username, $message );
            }
        }

        return $result;
    }

    function simulate_email_change( $result, $user, $email, $original_email, $settings ){
        $result['result'] = 'updated';

        if( strtolower( trim( $user->user_email ) ) === strtolower( $email ) || ( apply_filters( 'acui_allow_no_email', false ) && $original_email === '' ) )
            return $result;

        switch( $settings['update_emails_existing_users'] ){
            case 'no':
                $result['result'] = 'ignored';
                $result['type'] = 'notice';
                $result['message'] = sprintf( __( 'User with email "%s" exists with other username, it will be ignored', 'import-users-from-csv-with-meta' ), $email );
                break;

            case 'create':
                $result['result'] = 'created';
                $result['type'] = 'warning';
                $result['user_id'] = 0;
                $result['message'] = __( 'The email is different from the one of the existing user, so a new user will be created with a prefix in the username.', 'import-users-from-csv-with-meta' );
                break;

            default:
                $other = get_user_by( 'email', $email );

                if( $other && (int) $other->ID !== (int) $user->ID ){
                    $result['result'] = 'errors';
                    $result['type'] = 'error';
                    $result['message'] = __( 'Sorry, that email address is already used!' );
                }
                else{
                    $result['message'] = sprintf( __( 'The email of this user will change from %1$s to %2$s.', 'import-users-from-csv-with-meta' ), $user->user_email, $email );
                    $result['type'] = 'notice';
                }
                break;
        }

        return $result;
    }

    function simulate_not_present( $settings, $preview ){
        $delete = ( $settings['delete_users_not_present'] === 'yes' && current_user_can( 'delete_users' ) );
        $change_role = ( !$delete && $settings['change_role_not_present'] === 'yes' && current_user_can( 'promote_users' ) );

        if( !$delete && !$change_role )
            return array( 'action' => 'none', 'count' => 0, 'message' => '' );

        if( $preview['totals']['errors'] > 0 || $preview['totals']['warnings'] > 0 )
            return array(
                'action' => 'blocked',
                'count' => 0,
                'message' => $delete
                    ? __( 'Users who are not in the file will not be deleted, because some rows have errors or warnings. Fix them first if you want to delete those users.', 'import-users-from-csv-with-meta' )
                    : __( 'The role of users who are not in the file will not change, because some rows have errors or warnings. Fix them first if you want to change those roles.', 'import-users-from-csv-with-meta' ),
            );

        $exclude_roles = array_diff( array_keys( wp_roles()->roles ), array_keys( ACUIHelper()->get_editable_roles() ) );

        if( !in_array( 'administrator', $exclude_roles ) )
            $exclude_roles[] = 'administrator';

        $args = array(
            'fields' => 'ID',
            'role__not_in' => $exclude_roles,
            'exclude' => $delete ? apply_filters( 'acui_exclude_users_to_delete', array( get_current_user_id() ) ) : apply_filters( 'acui_exclude_users_to_change_role', array( get_current_user_id() ) ),
        );

        if( ( $delete && $settings['delete_users_only_specified_role'] ) || $settings['not_present_same_role'] === 'yes' )
            $args['role__in'] = $preview['roles_appeared'];

        if( $settings['not_present_only_imported'] === 'yes' ){
            $args['meta_key'] = '_acui_imported';
            $args['meta_value'] = '1';
        }

        $users = array_map( 'intval', get_users( $args ) );
        $count = count( array_diff( $users, $preview['registered'] ) );

        if( $delete )
            return array( 'action' => 'delete', 'count' => $count, 'message' => sprintf( _n( '%s user who is not in the file will be deleted.', '%s users who are not in the file will be deleted.', $count, 'import-users-from-csv-with-meta' ), number_format_i18n( $count ) ) );

        $roles = wp_roles()->get_names();
        $role_name = isset( $roles[ $settings['change_role_not_present_role'] ] ) ? translate_user_role( $roles[ $settings['change_role_not_present_role'] ] ) : $settings['change_role_not_present_role'];

        return array( 'action' => 'change_role', 'count' => $count, 'message' => sprintf( _n( '%1$s user who is not in the file will have their role changed to %2$s.', '%1$s users who are not in the file will have their role changed to %2$s.', $count, 'import-users-from-csv-with-meta' ), number_format_i18n( $count ), $role_name ) );
    }

    function download_errors(){
        check_admin_referer( 'acui_wizard_errors' );

        if( !current_user_can( apply_filters( 'acui_capability', 'create_users' ) ) )
            wp_die( __( 'You are not allowed to see this content.', 'import-users-from-csv-with-meta' ) );

        $path = self::errors_path();

        if( $path === '' || !is_readable( $path ) )
            wp_die( __( 'The file with the rows that have errors is no longer available. Run the preview again.', 'import-users-from-csv-with-meta' ) );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="rows-with-errors.csv"' );
        header( 'Content-Length: ' . filesize( $path ) );
        readfile( $path );
        exit;
    }
}

$acui_wizard = new ACUI_Wizard();
$acui_wizard->hooks();
