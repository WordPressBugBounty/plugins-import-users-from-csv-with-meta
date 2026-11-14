; (function ($) {
    var $wrap = $('.wrap.acui.acui-wizard');

    if (!$wrap.length || typeof acui_wizard === 'undefined') {
        return;
    }

    var $form = $('#acui_form');
    var $next = $wrap.find('.acui-wizard-next');
    var $check = $wrap.find('.acui-wizard-check');
    var $info = $wrap.find('.acui-wizard-nav-info');
    var i18n = acui_wizard.i18n;
    var analysis = null;
    var preview = null;
    var fileChanged = true;

    function format(text, args) {
        var position = 0;
        return text.replace(/%(\d+)\$s|%s/g, function (match, index) {
            return args[index ? index - 1 : position++];
        });
    }

    function currentStep() {
        return parseInt($wrap.attr('data-acui-step'), 10);
    }

    function go(step, quiet) {
        $wrap.attr('data-acui-step', step);
        $next.prop('disabled', false).text(step === 3 ? i18n.preview_button : i18n.continue);
        $info.text('');

        if (step === 4 && preview) {
            var skipped = preview.counts.ignored + preview.counts.errors;
            $info.text(skipped > 0 ? format(i18n.skipped_info, [Number(skipped).toLocaleString()]) : '');
        }

        if (step === 1) {
            $('#acui_file_header').prop('open', true);
        }

        if (step === 2) {
            validate();
        }

        if (step === 3) {
            updateRoleRow();
        }

        if (!quiet) {
            $('html, body').animate({ scrollTop: $wrap.offset().top - 40 }, 'fast');
        }
    }

    function usingPath() {
        return $('#introduce_path').is(':visible');
    }

    function analyze() {
        var data = new FormData($form[0]);

        if (usingPath()) {
            if ($.trim($('#path_to_file').val()) === '') {
                alert(i18n.enter_path);
                return;
            }
            data.delete('uploadfile');
        } else {
            if (!$('#uploadfile')[0].files.length) {
                alert(i18n.choose_file);
                return;
            }
            data.delete('path_to_file');
        }

        if (!fileChanged && analysis) {
            go(2);
            return;
        }

        data.append('action', 'acui_wizard_analyze');
        $next.prop('disabled', true).text(i18n.analyzing);

        $.ajax({
            type: 'POST',
            url: acui_wizard.ajaxurl,
            data: data,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                analysis = response.data;
                fileChanged = false;
                $('#acui_wizard_token').val(analysis.token);
                render();
                go(2);
                return;
            }

            if (response.data && response.data.addon_url) {
                if (confirm(response.data.message)) {
                    window.open(response.data.addon_url, '_blank');
                }
            } else {
                alert(response.data && response.data.message ? response.data.message : i18n.error_thrown);
            }
        }).fail(function () {
            alert(i18n.error_thrown);
        }).always(function () {
            if (currentStep() === 1) {
                $next.prop('disabled', false).text(i18n.continue);
            }
        });
    }

    function buildSelect(index, column) {
        var $select = $('<select>').attr({ name: 'acui_map[' + index + ']', id: 'acui_map_' + index, 'aria-label': column.header });

        $.each(acui_wizard.targets, function (group, fields) {
            var $group = $('<optgroup>').attr('label', group);
            $.each(fields, function (key, label) {
                $group.append($('<option>').val(key).text(label));
            });
            $select.append($group);
        });

        $select.append(
            $('<optgroup>').attr('label', i18n.other)
                .append($('<option>').val('__meta').text(i18n.custom_field))
                .append($('<option>').val('__skip').text(i18n.skip))
        );

        return $select.val(column.target);
    }

    function buildTransform(index) {
        var $type = $('<select class="acui-wizard-tf-type">').attr({ name: 'acui_tf[' + index + ']', 'aria-label': i18n.tf_add }).append($('<option>').val('').text('—'));

        $.each(i18n.tf_types, function (key, label) {
            $type.append($('<option>').val(key).text(label));
        });

        return $('<div class="acui-wizard-tf">').append(
            $('<button type="button" class="button-link acui-wizard-tf-toggle">').text('+ ' + i18n.tf_add),
            $('<div class="acui-wizard-tf-fields" hidden>').append(
                $type,
                $('<input type="text" class="acui-wizard-tf-a">').attr('name', 'acui_tf_a[' + index + ']'),
                $('<span class="acui-wizard-tf-arrow" aria-hidden="true">→</span>'),
                $('<input type="text" class="acui-wizard-tf-b">').attr('name', 'acui_tf_b[' + index + ']'),
                $('<button type="button" class="button-link acui-wizard-tf-remove">').attr('aria-label', i18n.tf_remove).append('<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>'),
                $('<span class="acui-wizard-tf-help">')
            )
        );
    }

    function refreshTransform($tf) {
        var type = $tf.find('.acui-wizard-tf-type').val();
        var placeholders = i18n.tf_placeholders[type] || ['', ''];
        var needsText = type !== '' && type !== 'upper' && type !== 'lower';

        $tf.find('.acui-wizard-tf-a').toggle(needsText).attr('placeholder', placeholders[0]);
        $tf.find('.acui-wizard-tf-b, .acui-wizard-tf-arrow').toggle(type === 'replace');
        $tf.find('.acui-wizard-tf-b').attr('placeholder', placeholders[1]);
        $tf.find('.acui-wizard-tf-help').text(i18n.tf_help[type] || '').toggle(!!i18n.tf_help[type]);
    }

    var fixedIndex = 0;

    function addFixedRow() {
        var n = fixedIndex++;
        var $select = buildSelect(n, { header: i18n.fixed_add, target: '__meta' }).attr({ name: 'acui_fixed_key[' + n + ']', id: 'acui_fixed_key_' + n });

        $select.find('option[value="user_login"], option[value="user_email"], option[value="__skip"]').remove();
        $select.find('optgroup').filter(function () { return !$(this).children().length; }).remove();
        $select.val('__meta');

        var $item = $('<li class="acui-wizard-fixed-item">').append(
            $('<span class="acui-wizard-fixed-field">').append(
                $select,
                $('<input type="text" class="acui-wizard-fixed-meta">').attr({ name: 'acui_fixed_meta[' + n + ']', placeholder: i18n.meta_key, 'aria-label': i18n.meta_key })
            ),
            $('<span class="acui-wizard-fixed-equals" aria-hidden="true">=</span>'),
            $('<input type="text" class="acui-wizard-fixed-value">').attr({ name: 'acui_fixed_value[' + n + ']', placeholder: i18n.fixed_value, 'aria-label': i18n.fixed_value }),
            $('<button type="button" class="button-link acui-wizard-fixed-remove">').attr('aria-label', i18n.fixed_remove).append('<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>')
        );

        $wrap.find('.acui-wizard-fixed-list').append($item);
        enhanceSelect($select);
        refreshFixed($item);
        $item.find('.acui-wizard-fixed-meta').trigger('focus');
        validate();
    }

    function refreshFixed($item) {
        $item.find('.acui-wizard-fixed-meta').toggle($item.find('select').val() === '__meta');
    }

    function render() {
        var $tbody = $wrap.find('.acui-wizard-map tbody').empty();

        $wrap.find('.acui-wizard-file-facts').text(format(i18n.file_facts, [analysis.name, Number(analysis.rows).toLocaleString(), analysis.columns.length, analysis.delimiter, analysis.encoding]));

        $.each(analysis.columns, function (index, column) {
            column.chosen = false;

            var $meta = $('<input type="text" class="acui-wizard-meta">').attr({
                name: 'acui_map_meta[' + index + ']',
                id: 'acui_map_meta_' + index,
                placeholder: i18n.meta_key,
                'aria-label': i18n.meta_key
            }).val(column.meta_key || column.header);

            var $row = $('<tr>').attr('data-index', index).append(
                $('<td>').append($('<strong>').text(column.header !== '' ? column.header : i18n.empty_header)),
                $('<td class="acui-wizard-samples">').text(column.samples.length ? column.samples.join(' · ') : i18n.no_values).attr('title', column.samples.join('\n')),
                $('<td class="acui-wizard-target">').append(buildSelect(index, column), $meta, $('<span class="acui-wizard-hint">'), $('<span class="acui-wizard-help">'), buildTransform(index)),
                $('<td class="acui-wizard-status">')
            );

            $tbody.append($row);
            enhanceSelect($row.find('select'));
            refreshRow($row);
        });
    }

    function optionKey(option) {
        return option.id && option.id.indexOf('__') !== 0 ? option.id : '';
    }

    function enhanceSelect($select) {
        if (!$.fn.select2) {
            return;
        }

        $select.select2({
            width: '100%',
            dropdownAutoWidth: true,
            matcher: function (params, data) {
                var term = $.trim(params.term || '').toLowerCase();

                if (term === '') {
                    return data;
                }

                if (data.children && data.children.length) {
                    var children = $.grep(data.children, function (child) {
                        return (child.text + ' ' + optionKey(child)).toLowerCase().indexOf(term) !== -1;
                    });

                    if (!children.length) {
                        return null;
                    }

                    return $.extend({}, data, { children: children });
                }

                return (data.text + ' ' + optionKey(data)).toLowerCase().indexOf(term) !== -1 ? data : null;
            },
            templateResult: function (option) {
                var key = optionKey(option);

                if (!key || key === option.text) {
                    return option.text;
                }

                return $('<span>').text(option.text + ' ').append($('<code class="acui-wizard-key">').text(key));
            }
        });
    }

    function refreshRow($row) {
        var column = analysis.columns[$row.data('index')];
        var target = $row.find('select').val();
        var pill = 'skip';
        var label = i18n.ignored;
        var hint = '';

        if (target === '__meta') {
            pill = 'meta';
            label = i18n.meta;
        } else if (target !== '__skip') {
            if (column.chosen) {
                pill = 'exact';
                label = i18n.chosen;
            } else if (column.match === 'exact') {
                pill = 'exact';
                label = i18n.exact;
            } else {
                pill = 'suggested';
                label = i18n.suggested;
                if (column.match === 'content') {
                    hint = i18n.by_content;
                } else if (column.match === 'position') {
                    hint = i18n.by_position;
                } else if (column.match === 'hash' && target === 'user_pass') {
                    hint = i18n.by_hash;
                }
            }
        }

        var help = { password: i18n.hint_password, user_pass: i18n.hint_user_pass, role: i18n.hint_role }[target] || '';
        var warning = target === 'id';

        if (warning) {
            hint = i18n.id_warning;
        }

        $row.find('.acui-wizard-meta').toggle(target === '__meta');
        $row.find('.acui-wizard-tf').toggle(target !== '__skip');
        $row.find('.acui-wizard-hint').text(hint).toggle(hint !== '').toggleClass('acui-wizard-hint--warning', warning || (column.match === 'hash' && target === 'user_pass' && !column.chosen));
        $row.find('.acui-wizard-help').text(help).toggle(help !== '');
        $row.find('.acui-wizard-status').empty().append($('<span>').addClass('acui-wizard-pill acui-wizard-pill--' + pill).text(label));
    }

    function validate() {
        var used = {};
        var duplicated = '';
        var hasEmail = false;
        var hasLogin = false;
        var missingMeta = false;
        var missingTransform = false;
        var missingFixed = false;
        var imported = 0;
        var ignored = 0;
        var error = '';

        $wrap.find('.acui-wizard-map tbody tr').each(function () {
            var $row = $(this);
            var target = $row.find('select').val();
            var name = target;

            if (target === '__skip') {
                ignored++;
                return;
            }

            imported++;

            if (target === '__meta') {
                name = $.trim($row.find('.acui-wizard-meta').val());
                if (name === '') {
                    missingMeta = true;
                    return;
                }
            }

            var tfType = $row.find('.acui-wizard-tf-type').val();
            if (tfType && tfType !== 'upper' && tfType !== 'lower' && $.trim($row.find('.acui-wizard-tf-a').val()) === '') {
                missingTransform = true;
            }

            var key = name.toLowerCase();
            if (key === 'username') {
                key = 'user_login';
            } else if (key === 'email') {
                key = 'user_email';
            }

            if (key === 'user_email') {
                hasEmail = true;
            } else if (key === 'user_login') {
                hasLogin = true;
            }

            if (used[key] && duplicated === '') {
                duplicated = name;
            }
            used[key] = true;
        });

        $wrap.find('.acui-wizard-fixed-item').each(function () {
            var $item = $(this);
            var target = $item.find('select').val();
            var name = target === '__meta' ? $.trim($item.find('.acui-wizard-fixed-meta').val()) : target;

            if (name === '') {
                missingMeta = true;
                return;
            }

            if ($item.find('.acui-wizard-fixed-value').val() === '') {
                missingFixed = true;
            }

            var key = name.toLowerCase();
            if (used[key] && duplicated === '') {
                duplicated = name;
            }
            used[key] = true;
        });

        $wrap.find('.acui-wizard-username').prop('hidden', hasLogin);

        if (!hasEmail && acui_wizard.email_required) {
            error = i18n.need_email;
        } else if (duplicated !== '') {
            error = format(i18n.duplicated, [duplicated]);
        } else if (missingMeta) {
            error = i18n.need_meta_key;
        } else if (missingTransform) {
            error = i18n.need_tf_text;
        } else if (missingFixed) {
            error = i18n.need_fixed_value;
        }

        $check.toggleClass('acui-wizard-check--error', error !== '').text(error !== '' ? error : format(i18n.ready, [imported, ignored]));

        if (currentStep() === 2) {
            $next.prop('disabled', error !== '');
        }

        return error === '';
    }

    function roleColumnName() {
        var name = '';

        $wrap.find('.acui-wizard-map tbody tr').each(function () {
            if ($(this).find('select').val() === 'role') {
                name = $(this).find('strong').first().text();
                return false;
            }
        });

        return name;
    }

    function updateRoleRow() {
        var column = roleColumnName();
        var $row = $('#acui_wizard_role_row');

        $row.find('.acui-role-from-column').prop('hidden', column === '').find('.acui-role-column-name').text(column);
        $row.find('.acui-role-choice').toggle(column === '');
        $row.find('.acui-role-error').remove();
    }

    function rolesAreValid() {
        var $row = $('#acui_wizard_role_row');

        $row.find('.acui-role-error').remove();

        if (!$row.length || roleColumnName() !== '' || $row.find('input[name="acui_role_mode"]:checked').val() !== 'custom') {
            return true;
        }

        var roles = $row.find('#role').val() || [];

        if (roles.length) {
            return true;
        }

        $row.find('.acui-role-custom').append($('<p class="acui-role-error">').text(i18n.choose_role));
        $('html, body').animate({ scrollTop: $row.offset().top - 60 }, 'fast');

        return false;
    }

    function runPreview(offset) {
        var data = new FormData($form[0]);
        data.delete('uploadfile');
        data.delete('path_to_file');
        data.append('action', 'acui_wizard_preview');
        data.append('offset', offset);

        $.ajax({
            type: 'POST',
            url: acui_wizard.ajaxurl,
            data: data,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (!response.success) {
                alert(response.data && response.data.message ? response.data.message : i18n.error_thrown);
                $next.prop('disabled', false).text(i18n.preview_button);
                return;
            }

            if (!response.data.done) {
                $next.text(format(i18n.checking, [response.data.percentage + '%']));
                runPreview(response.data.offset);
                return;
            }

            preview = response.data;
            renderPreview();
            go(4);
        }).fail(function () {
            alert(i18n.error_thrown);
            $next.prop('disabled', false).text(i18n.preview_button);
        });
    }

    function resultPill(result, type) {
        var pill = { created: 'exact', updated: 'suggested', ignored: 'skip', errors: 'error' }[result];

        if (type === 'warning' && result !== 'errors') {
            pill = 'warning';
        }

        return $('<span>').addClass('acui-wizard-pill acui-wizard-pill--' + pill).text(i18n['result_' + result]);
    }

    function renderPreview() {
        var counts = preview.counts;
        var $tbody = $wrap.find('.acui-wizard-sample tbody').empty();
        var $issues = $wrap.find('.acui-wizard-issues');
        var $notPresent = $wrap.find('.acui-wizard-not-present');
        var toImport = counts.created + counts.updated;

        $('#acui_preview_created').text(Number(counts.created).toLocaleString());
        $('#acui_preview_updated').text(Number(counts.updated).toLocaleString());
        $('#acui_preview_ignored').text(Number(counts.ignored).toLocaleString());
        $('#acui_preview_errors').text(Number(counts.errors).toLocaleString());
        $('#acui_preview_errors_card').toggleClass('acui-log-card--errors', counts.errors > 0).toggleClass('acui-log-card--no-errors', counts.errors === 0);

        $notPresent.prop('hidden', preview.not_present.action === 'none')
            .toggleClass('acui-wizard-not-present--delete', preview.not_present.action === 'delete' && preview.not_present.count > 0)
            .text(preview.not_present.message);

        $.each(preview.sample, function (index, row) {
            var $result = $('<td>').append(resultPill(row.result, row.type));

            if (row.message) {
                $result.append($('<span class="acui-wizard-row-message">').text(row.message));
            }

            var $email = $('<td>').text(row.email);

            if (row.changes && row.changes.length) {
                $email.append($('<span class="acui-wizard-row-changes">').text(row.changes.join(' · ')));
            }

            $tbody.append($('<tr>').append(
                $('<td>').text(row.row),
                $email,
                $('<td>').text(row.login !== '' ? row.login : i18n.random_username),
                $('<td>').text(row.role),
                $result
            ));
        });

        $issues.prop('hidden', preview.issues_count === 0);
        $issues.find('h3').text(format(i18n.issues_title, [Number(preview.issues_count).toLocaleString()]));
        $issues.find('ul').empty();

        $.each(preview.issues, function (index, issue) {
            $issues.find('ul').append($('<li>').append(
                $('<span class="acui-wizard-issue-row">').text(format(i18n.row_n, [issue.row])),
                $('<span>').append(resultPill(issue.type === 'error' ? 'errors' : (issue.type === 'notice' ? 'ignored' : 'updated'), issue.type)),
                $('<span>').text(issue.message)
            ));
        });

        $issues.find('.acui-wizard-issues-more').text(preview.issues_count > preview.issues.length ? format(i18n.issues_more, [Number(preview.issues_count - preview.issues.length).toLocaleString()]) : '');
        $issues.find('.acui-wizard-errors-download').attr('href', preview.errors_url).toggle(preview.errors_url !== '');

        $wrap.find('.acui-wizard-import').text(format(i18n.import_n, [Number(toImport).toLocaleString()])).prop('disabled', toImport === 0);
    }

    $wrap.on('change', '.acui-wizard-map select:not(.acui-wizard-tf-type)', function () {
        var $row = $(this).closest('tr');
        analysis.columns[$row.data('index')].chosen = true;
        refreshRow($row);
        validate();
    });

    $wrap.on('input', '.acui-wizard-map input[type="text"], .acui-wizard-fixed input[type="text"]', validate);

    $wrap.on('click', '.acui-wizard-tf-toggle', function () {
        var $tf = $(this).closest('.acui-wizard-tf');
        $(this).prop('hidden', true);
        $tf.find('.acui-wizard-tf-fields').prop('hidden', false);
        $tf.find('.acui-wizard-tf-type').val('replace');
        refreshTransform($tf);
        $tf.find('.acui-wizard-tf-a').trigger('focus');
        validate();
    });

    $wrap.on('change', '.acui-wizard-tf-type', function () {
        var $tf = $(this).closest('.acui-wizard-tf');
        if ($(this).val() === '') {
            $tf.find('.acui-wizard-tf-remove').trigger('click');
            return;
        }
        refreshTransform($tf);
        validate();
    });

    $wrap.on('click', '.acui-wizard-tf-remove', function () {
        var $tf = $(this).closest('.acui-wizard-tf');
        $tf.find('.acui-wizard-tf-type').val('');
        $tf.find('.acui-wizard-tf-a, .acui-wizard-tf-b').val('');
        $tf.find('.acui-wizard-tf-fields').prop('hidden', true);
        $tf.find('.acui-wizard-tf-toggle').prop('hidden', false);
        validate();
    });

    $wrap.on('click', '.acui-wizard-fixed-add', addFixedRow);

    $wrap.on('change', '.acui-wizard-fixed select', function () {
        refreshFixed($(this).closest('.acui-wizard-fixed-item'));
        validate();
    });

    $wrap.on('click', '.acui-wizard-fixed-remove', function () {
        $(this).closest('.acui-wizard-fixed-item').remove();
        validate();
    });

    $form.on('change', '#uploadfile', function () {
        fileChanged = true;
    });

    $form.on('input', '#path_to_file', function () {
        fileChanged = true;
    });

    $form.on('change', 'input[name="acui_file_source"]', function () {
        fileChanged = true;
    });

    $next.on('click', function () {
        var step = currentStep();

        if (step === 1) {
            analyze();
        } else if (step === 2 && validate()) {
            go(3);
        } else if (step === 3) {
            if (!rolesAreValid()) {
                return;
            }

            preview = null;
            $next.prop('disabled', true).text(format(i18n.checking, ['0%']));
            runPreview(0);
        }
    });

    $wrap.on('click', '.acui-wizard-back', function () {
        var step = currentStep();
        if (step > 1) {
            go(step - 1);
        }
    });

    $form.on('keydown', 'input', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });

    $(document).on('click', '#acui_wizard_import, #uploadfile_btn', function (event) {
        if (fileChanged || !analysis || !preview || !$('#acui_wizard_token').val()) {
            event.preventDefault();
            go(1);
            return;
        }

        var $fileFields = $('#uploadfile, #path_to_file').prop('disabled', true);
        setTimeout(function () {
            $fileFields.prop('disabled', false);
        }, 0);
    });

    function setMoreOptions(open) {
        $wrap.toggleClass('acui-more-open', open);
        $wrap.find('.acui-wizard-more-toggle').attr('aria-expanded', open ? 'true' : 'false');
    }

    $wrap.find('.acui-accordion > details.acui-advanced').each(function () {
        $(this).toggleClass('acui-empty', $(this).find('tbody tr').length === 0);
    });

    var moreOpen = false;
    try {
        moreOpen = window.localStorage.getItem('acui_wizard_more') === 'open';
    } catch (e) {}

    if ($('#delete_users_not_present').is(':checked') || $('#change_role_not_present').is(':checked')) {
        moreOpen = true;
    }

    setMoreOptions(moreOpen);

    $wrap.on('click', '.acui-wizard-more-toggle', function () {
        var open = !$wrap.hasClass('acui-more-open');
        setMoreOptions(open);
        try {
            window.localStorage.setItem('acui_wizard_more', open ? 'open' : 'closed');
        } catch (e) {}
    });

    go(1, true);
})(jQuery);
