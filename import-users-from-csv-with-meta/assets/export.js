;(function ( $, window ) {
	var userExportForm = function( $form ) {
		this.$form = $form;
		this.xhr = false;

		this.$form.find( '.user-exporter-progress' ).val( 0 );
		this.processStep = this.processStep.bind( this );
		$form.on( 'submit', { userExportForm: this }, this.onSubmit );
	};

	userExportForm.prototype.onSubmit = function( event ) {
		event.preventDefault();

        $( "html, body" ).animate({ scrollTop: 0 }, "slow" );

		event.data.userExportForm.$form.addClass( 'user-exporter__exporting' );
		event.data.userExportForm.$form.find( '.user-exporter-progress' ).val( 0 );
        event.data.userExportForm.$form.find( '.user-exporter-progress-value' ).text( acui_export_js_object.starting_process + " - 0%" );
		event.data.userExportForm.processStep( 1, $( this ).serialize(), '' );
	};

	userExportForm.prototype.processStep = function( step, data, filename ) {
		var $this = this;
        var frontend = $( '[name="acui_frontend_export"]' ).val();
        var convert_timestamp, order_fields_alphabetically, double_encapsulate_serialized_values, display_arrays_as_comma_separated_list_of_values, export_password_hashes;

        if( frontend == 1 ){
            convert_timestamp = $this.$form.find( '[name="convert_timestamp"]' ).val();
            order_fields_alphabetically = $this.$form.find( '[name="order_fields_alphabetically"]' ).val();
            double_encapsulate_serialized_values = $this.$form.find( '[name="double_encapsulate_serialized_values"]' ).val();
			display_arrays_as_comma_separated_list_of_values = $this.$form.find( '[name="display_arrays_as_comma_separated_list_of_values"]' ).val();
			export_password_hashes = $this.$form.find( '[name="export_password_hashes"]' ).length ? $this.$form.find( '[name="export_password_hashes"]' ).val() : 1;
        }
        else{
            convert_timestamp = $this.$form.find( '[name="convert_timestamp"]' ).is( ":checked");
            order_fields_alphabetically = $this.$form.find( '[name="order_fields_alphabetically"]' ).is( ":checked");
            double_encapsulate_serialized_values = $this.$form.find( '[name="double_encapsulate_serialized_values"]' ).is( ":checked");
			display_arrays_as_comma_separated_list_of_values = $this.$form.find( '[name="display_arrays_as_comma_separated_list_of_values"]' ).is( ":checked");
			export_password_hashes = $this.$form.find( '[name="export_password_hashes"]' ).is( ":checked");
        }

		$.ajax( {
			type: 'POST',
			url: acui_export_js_object.ajaxurl,
			data: {
				form: data,
				action: 'acui_export_users_csv',
				filename: filename,
				current_url: window.location.href,
				step: step,
				format: $this.$form.find( '[name="format"]' ).val(),
				delimiter: $this.$form.find( '[name="delimiter"]' ).val(),
				role: $this.$form.find( '[name="role[]"]' ).val(),
				from: $this.$form.find( '[name="from"]' ).val(),
				users: $this.$form.find( '[name="users[]"]' ).val(),
				include_guests: $this.$form.find( '[name="include_guests"]' ).is( ':checked' ),
				offset_rows: $this.$form.find( '[name="offset_rows"]' ).val(),
				max_rows: $this.$form.find( '[name="max_rows"]' ).val(),
				to: $this.$form.find( '[name="to"]' ).val(),
				convert_timestamp: convert_timestamp,
				datetime_format: $this.$form.find( '[name="datetime_format"]' ).val(),
				order_fields_alphabetically: order_fields_alphabetically,
				double_encapsulate_serialized_values: double_encapsulate_serialized_values,
				display_arrays_as_comma_separated_list_of_values: display_arrays_as_comma_separated_list_of_values,
				export_password_hashes: export_password_hashes,
				columns: $this.$form.find( '[name="columns"]' ).val(),
				orderby: $this.$form.find( '[name="orderby"]' ).val(),
				order: $this.$form.find( '[name="order"]' ).val(),
				security: $this.$form.find( '#security' ).val(),
			},
			dataType: 'json',
			success: function( response ) {
                if ( response.success ) {
					if ( 'done' === response.data.step ) {
						$this.$form.find('.user-exporter-progress').val( response.data.percentage );
                        $this.$form.find('.user-exporter-progress-value').text( response.data.percentage + "%" );
						window.location = response.data.url;
						setTimeout( function() {
							$this.$form.removeClass( 'user-exporter__exporting' );
							$( '#acui_download_csv_wrapper > td > input' ).prop( 'disabled', false );

							if( response.data.results != '' ){
								$( '#acui_export_results' )
									.html( response.data.results )
									.show()
							}
						}, 2000 );
					} else {
						$this.$form.find( '.user-exporter-progress' ).val( response.data.percentage );
                        $this.$form.find( '.user-exporter-progress-value' ).text( acui_export_js_object.step + " " + response.data.step + " " + acui_export_js_object.of_approximately +  " " + response.data.total_steps + " " + acui_export_js_object.steps + " - " + response.data.percentage + "%" );
						$this.processStep( parseInt( response.data.step, 10 ), data, response.data.filename );
					}
				}
			}
		} ).fail( function( response ) {
			window.console.log( response );
            alert( acui_export_js_object.error_thrown );
		} );
	};

	$.fn.user_export_form = function() {
		new userExportForm( this );
		return this;
	};

	$( '.acui_exporter' ).each( function( index, element ){
		$( element ).user_export_form();
	} );
})( jQuery, window );

;(function ( $ ) {
	var $box = $( '.acui-columns-box' );

	if ( ! $box.length ) {
		return;
	}

	var $list = $box.find( '.acui-columns-list' );
	var $text = $( 'textarea[name="columns"]' );
	var syncing = false;

	function parse( value ) {
		var items = [];

		$.each( String( value || '' ).split( ',' ), function( i, part ) {
			part = $.trim( part );

			if ( ! part ) {
				return;
			}

			var pos = part.indexOf( '=>' );
			items.push( pos === -1 ? { key: part, label: '' } : { key: $.trim( part.substring( 0, pos ) ), label: $.trim( part.substring( pos + 2 ) ) } );
		} );

		return items;
	}

	function find( key ) {
		return $list.children( 'li' ).filter( function() {
			return $( this ).attr( 'data-key' ) === key;
		} ).first();
	}

	function addItem( key ) {
		var $item = $list.children( 'li' ).first().clone();
		$item.attr( 'data-key', key ).find( 'code' ).text( key );
		$item.find( '.acui-col-check' ).prop( 'checked', false );
		$item.find( '.acui-col-label' ).val( '' );
		$list.append( $item );
		return $item;
	}

	function updateCount() {
		var checked = $list.find( '.acui-col-check:checked' ).length;
		$box.find( '.acui-columns-count' ).text( checked + ' / ' + $list.children( 'li' ).length );
	}

	function fromText() {
		var items = parse( $text.val() );
		var $anchor = null;

		$list.children( 'li' ).removeClass( 'is-checked' ).find( '.acui-col-check' ).prop( 'checked', false );
		$list.find( '.acui-col-label' ).val( '' );

		$.each( items, function( i, item ) {
			var $item = find( item.key );

			if ( ! $item.length && $list.children( 'li' ).length ) {
				$item = addItem( item.key );
			}

			$item.addClass( 'is-checked' ).find( '.acui-col-check' ).prop( 'checked', true );
			$item.find( '.acui-col-label' ).val( item.label !== item.key ? item.label : '' );

			if ( $anchor ) {
				$item.insertAfter( $anchor );
			} else {
				$list.prepend( $item );
			}

			$anchor = $item;
		} );

		updateCount();
	}

	function toText() {
		if ( syncing ) {
			return;
		}

		var parts = [];

		$list.children( 'li' ).each( function() {
			var $item = $( this );

			if ( ! $item.find( '.acui-col-check' ).is( ':checked' ) ) {
				return;
			}

			var key = $item.attr( 'data-key' );
			var label = $.trim( String( $item.find( '.acui-col-label' ).val() ).replace( /,|=>/g, ' ' ).replace( /\s+/g, ' ' ) );
			parts.push( label && label !== key ? key + '=>' + label : key );
		} );

		$text.val( parts.join( ',' ) );
		updateCount();
	}

	function setMode( mode ) {
		$box.prop( 'hidden', mode !== 'choose' );

		if ( mode === 'all' ) {
			$text.val( '' );
			fromText();
		} else if ( ! $list.find( '.acui-col-check:checked' ).length ) {
			$list.children( 'li' ).filter( function() {
				return $.inArray( $( this ).attr( 'data-key' ), [ 'user_login', 'user_email', 'role' ] ) !== -1;
			} ).find( '.acui-col-check' ).prop( 'checked', true ).closest( 'li' ).addClass( 'is-checked' );
			toText();
		}
	}

	$list.sortable( {
		handle: '.acui-col-handle',
		items: '> li.is-checked',
		axis: 'y',
		placeholder: 'acui-col-placeholder',
		update: toText
	} );

	$list.on( 'change', '.acui-col-check', function() {
		var $item = $( this ).closest( 'li' );
		$item.toggleClass( 'is-checked', this.checked );

		if ( this.checked ) {
			var $last = $list.children( 'li.is-checked' ).not( $item ).last();

			if ( $last.length ) {
				$item.insertAfter( $last );
			} else {
				$list.prepend( $item );
			}
		}

		toText();
	} );

	$list.on( 'input', '.acui-col-label', toText );

	$box.on( 'input', '.acui-columns-search', function() {
		var term = $.trim( $( this ).val() ).toLowerCase();

		$list.children( 'li' ).each( function() {
			$( this ).toggleClass( 'is-hidden', term !== '' && $( this ).attr( 'data-key' ).toLowerCase().indexOf( term ) === -1 );
		} );
	} );

	$box.on( 'click', '.acui-columns-all', function() {
		$list.children( 'li' ).not( '.is-hidden' ).addClass( 'is-checked' ).find( '.acui-col-check' ).prop( 'checked', true );
		toText();
	} );

	$box.on( 'click', '.acui-columns-none', function() {
		$list.children( 'li' ).not( '.is-hidden' ).removeClass( 'is-checked' ).find( '.acui-col-check' ).prop( 'checked', false );
		toText();
	} );

	$( 'input[name="acui_columns_mode"]' ).on( 'change', function() {
		setMode( $( this ).val() );
	} );

	$( '.acui-columns-text-toggle' ).on( 'click', function( event ) {
		event.preventDefault();
		var $wrap = $( '.acui-columns-text' );
		$wrap.prop( 'hidden', ! $wrap.prop( 'hidden' ) );
	} );

	$text.on( 'input', function() {
		syncing = true;
		fromText();
		syncing = false;
		var mode = $.trim( $text.val() ) === '' ? 'all' : 'choose';
		$( 'input[name="acui_columns_mode"][value="' + mode + '"]' ).prop( 'checked', true );
		$box.prop( 'hidden', mode !== 'choose' );
	} );

	fromText();
	$box.prop( 'hidden', $( 'input[name="acui_columns_mode"]:checked' ).val() !== 'choose' );
})( jQuery );

;(function ( $ ) {
	var $format = $( '#acui_format_wrapper [name="format"]' );

	if ( ! $format.length ) {
		return;
	}

	function toggleDelimiter() {
		$( '#acui_delimiter_wrapper' ).toggle( $format.val() === 'csv' );
	}

	$format.on( 'change', toggleDelimiter );
	toggleDelimiter();
})( jQuery );
