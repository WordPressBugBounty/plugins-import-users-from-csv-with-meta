; (function ($, window) {
    var userImportForm = function ($form) {
        this.$form = $form;
        this.xhr = false;
        this.isPaused = false;
        this.isStopped = false;
        this.nextFormData = null;

        this.$form.find('.user-importer-progress').val(0);
        this.processStep = this.processStep.bind(this);
        this.$form.on('submit', { userImportForm: this }, this.onSubmit);

        var self = this;
        this.$form.on('click', '#acui_pause_btn', function () {
            if (self.isPaused) {
                self.isPaused = false;
                $(this).text(acui_import_js_object.pause).removeClass('button-primary').addClass('button-secondary');
                if (self.nextFormData) {
                    self.processStep(self.nextFormData);
                    self.nextFormData = null;
                }
            } else {
                self.isPaused = true;
                $(this).text(acui_import_js_object.resume).removeClass('button-secondary').addClass('button-primary');
            }
        });

        this.$form.on('click', '#acui_stop_btn', function () {
            if (confirm(acui_import_js_object.stopped + "?")) {
                self.isStopped = true;
                if (self.xhr) {
                    self.xhr.abort();
                }
                $('.wrap.acui').removeClass('acui-importing');
                $('#uploadfile_btn').prop('disabled', false);
            }
        });
    };

    userImportForm.prototype.onSubmit = function (event) {
        if ($(event.originalEvent.submitter).attr('name') === 'save_options') {
            return;
        }

        event.preventDefault();

        $("html, body").animate({ scrollTop: 0 }, "slow");

        var $form = event.data.userImportForm.$form;
        $('.wrap.acui').addClass('acui-importing');
        $form.find('.user-importer-progress').val(0);
        $form.find('.user-importer-progress-value').text(acui_import_js_object.starting_process + " - 0%");
        $form.find('.user-importer-controls').show();
        $('#acui_import_log').empty();
        $('#acui_import_results').hide();
        $('#acui_import_results .acui-done-notice').remove();
        $('#acui_result_processed').text('0');
        $('#acui_result_created').text('0');
        $('#acui_result_updated').text('0');
        $('#acui_result_deleted').text('0');
        $('#acui_result_errors').text('0');

        var formData = new FormData(this);
        formData.append('action', 'acui_import_users_batch');
        formData.append('step', 1);
        formData.append('row', 0);
        formData.append('security', $form.find('#security').val());

        event.data.userImportForm.isStopped = false;
        event.data.userImportForm.isPaused = false;
        event.data.userImportForm.processStep(formData);
    };

    userImportForm.prototype.processStep = function (formData) {
        var $this = this;

        if (this.isStopped) {
            return;
        }

        if (this.isPaused) {
            this.nextFormData = formData;
            return;
        }

        this.xhr = $.ajax({
            type: 'POST',
            url: acui_import_js_object.ajaxurl,
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    if (response.data.log) {
                        var $existingTable = $('#acui_import_log').find('.acui-import-rows');
                        if ($existingTable.length) {
                            var $parsed = $(response.data.log);
                            $existingTable.find('tbody').append($parsed.find('.acui-import-rows tbody tr'));
                            $parsed.find('.acui-import-rows').remove();
                            var $remaining = $parsed.children();
                            if ($remaining.length) {
                                $('#acui_import_log').append($remaining);
                            }
                        } else {
                            $('#acui_import_log').append(response.data.log);
                        }
                    }

                    // Update results counters on every step
                    if (response.data.results) {
                        var r = response.data.results;
                        $('#acui_result_processed').text(r.created + r.updated);
                        $('#acui_result_created').text(r.created);
                        $('#acui_result_updated').text(r.updated);
                        $('#acui_result_deleted').text(r.deleted);
                        $('#acui_import_results').show();
                    }
                    if (response.data.errors_count !== undefined) {
                        $('#acui_result_errors').text(response.data.errors_count);
                    }

                    if ('done' === response.data.step) {
                        $this.$form.find('.user-importer-progress').val(100);
                        $this.$form.find('.user-importer-progress-value').text('100%');
                        $this.$form.find('.user-importer-controls').hide();

                        // Initialize DataTable on import rows
                        if ($.fn.DataTable) {
                            var $rowTable = $('#acui_import_log .acui-import-rows');
                            if ($rowTable.length && !$.fn.DataTable.isDataTable($rowTable[0])) {
                                $rowTable.DataTable({ scrollX: true });
                            }
                        }

                        // Done notice prepended to the results section
                        var doneNotice =
                            '<div class="acui-done-notice" style="margin-bottom:16px;padding:12px 16px;background:#d1e7dd;border:1px solid #a3cfbb;border-radius:6px;text-align:left;">' +
                            '<p style="margin:0 0 8px;font-weight:600;">' + acui_import_js_object.import_done + '</p>' +
                            '<p style="margin:0 0 12px;">' + acui_import_js_object.import_done_log +
                            ' <a href="' + acui_import_js_object.log_url + '">' + acui_import_js_object.view_log + '</a></p>' +
                            '<a href="' + acui_import_js_object.import_url + '" class="button button-primary">' + acui_import_js_object.new_import + '</a>' +
                            '</div>';
                        $('#acui_import_log').before(doneNotice);

                        // Scroll down to show results below datatable
                        $("html, body").animate({ scrollTop: $('#acui_import_results').offset().top - 40 }, "slow");
                    } else {
                        $this.$form.find('.user-importer-progress').val(response.data.percentage);
                        $this.$form.find('.user-importer-progress-value').text(acui_import_js_object.step + " " + response.data.step + " " + acui_import_js_object.of_approximately + " " + response.data.total_steps + " " + acui_import_js_object.steps + " - " + response.data.percentage + "%");

                        if ($this.isStopped) {
                            $('.wrap.acui').removeClass('acui-importing');
                            $('#uploadfile_btn').prop('disabled', false);
                            $this.$form.find('.user-importer-controls').hide();
                            return;
                        }

                        if ($this.isPaused) {
                            // Prepare next step but hold it
                            var nextFormData = new FormData();
                            nextFormData.append('action', 'acui_import_users_batch');
                            nextFormData.append('step', response.data.step);
                            nextFormData.append('row', response.data.row);
                            nextFormData.append('file_path', response.data.file_path);
                            nextFormData.append('security', $this.$form.find('#security').val());
                            nextFormData.append('form', $this.$form.serialize());
                            $this.nextFormData = nextFormData;
                            return;
                        }

                        // Prepare next step
                        var nextFormData = new FormData();
                        nextFormData.append('action', 'acui_import_users_batch');
                        nextFormData.append('step', response.data.step);
                        nextFormData.append('row', response.data.row);
                        nextFormData.append('file_path', response.data.file_path);
                        nextFormData.append('security', $this.$form.find('#security').val());
                        nextFormData.append('form', $this.$form.serialize());

                        $this.processStep(nextFormData);
                    }
                } else {
                    alert(response.data.message);
                    $('.wrap.acui').removeClass('acui-importing');
                    $('#uploadfile_btn').prop('disabled', false);
                }
            }
        }).fail(function (response) {
            if ($this.isStopped) return;
            window.console.log(response);
            alert(acui_import_js_object.error_thrown);
            $('.wrap.acui').removeClass('acui-importing');
        });
    };

    $.fn.user_import_form = function () {
        new userImportForm(this);
        return this;
    };

    $('#acui_form').each(function (index, element) {
        $(element).user_import_form();
    });
})(jQuery, window);
