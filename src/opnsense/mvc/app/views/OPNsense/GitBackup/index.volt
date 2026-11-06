<form id="frm_git" class="form-inline">

    <!-- always visible core options -->
    <div class="content-box" style="padding-bottom: 15px;">
        <table class="table table-striped opnsense_standard_table_form">
            <tbody>
{% for field in formCoreFields %}
                {{ partial("layout_partials/form_input_tr", field) }}
{% endfor %}
            </tbody>
        </table>
    </div>

    <!-- collapsible upstream drawer, revealed for remote mode only -->
    <div class="content-box" id="panel_remote_options" style="margin-top: 20px; padding-bottom: 15px; display: none;">
        <h4 style="padding-left: 15px;"><i class="fa fa-cloud-upload"></i> {{ lang._("Advanced Upstream Remote Options") }}</h4>
        <hr/>
        <table class="table table-striped opnsense_standard_table_form">
            <tbody>
{% for field in formRemoteFields %}
                {{ partial("layout_partials/form_input_tr", field) }}
{% endfor %}
                <tr>
                    <td style="width: 22%"><strong>{{ lang._("Connectivity Validation") }}</strong></td>
                    <td style="width: 78%">
                        <button class="btn btn-default" id="btn_test" type="button">
                            <i class="fa fa-refresh" id="test_progress"></i> <b>{{ lang._("Test Connection") }}</b>
                        </button>
                        <div id="inline_test_result" class="alert" style="display: none; margin-top: 10px; padding: 10px; border-radius: 3px;"></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</form>

<div class="col-md-12" style="margin-top: 20px; padding-left: 0;">
    <div id="git_force_warning" class="alert alert-danger" style="display: none;">
        <strong><i class="fa fa-exclamation-triangle"></i> {{ lang._("WARNING: Destructive Action") }}</strong><br>
        {{ lang._("Force Push overwrites and erases the history of the upstream branch with the local state. Use with extreme caution.") }}
    </div>

<style>
    /* self-contained corner toast: this OPNsense build ships no growl/toast plugin */
    #git_save_toast_stack {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        /* explicit structural width: the card must not collapse horizontally */
        width: 320px;
    }

    #git_save_toast_stack .git-custom-toast {
        margin-bottom: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-left: 5px solid #28a745;
        display: none;
    }
</style>
<div id="git_save_toast_stack" aria-live="polite"></div>

<script>
    $(document).ready(function () {
        var dataKey = '/api/gitbackup/settings';

        /* minimal floating confirmation, replaces the modal dialog used earlier */
        function showSaveToast(message) {
            /* a new card replaces any older row, so repeated saves can never stack */
            $('#git_save_toast_stack').empty();

            /* the shell is complete before the card is mounted, so it never fades in empty.
               The only caller passes the translated "Changes Applied Successfully!" literal; if this
               helper is ever called with dynamic text, escape it at the call site. */
            var toastHtml = '<div class="alert alert-success git-custom-toast" role="status">' +
                            '<strong><i class="fa fa-check-circle"></i></strong> ' +
                            '<span>' + message + '</span>' +
                            '</div>';
            var toast = $(toastHtml).appendTo('#git_save_toast_stack');
            toast.fadeIn(200);
            window.setTimeout(function () {
                toast.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 3000);
        }

        function formatLayoutTransitions(duration) {
            var enabled = $('#git\\.enabled').is(':checked');
            var remote = $('#git\\.dest_type').val() === 'remote';
            var destTypeRow = $('#git\\.dest_type').closest('tr');
            var remotePanel = $('#panel_remote_options');
            var forceWarning = $('#git_force_warning');

            if (!enabled) {
                /* level 1: provider off, everything except the master switch disappears */
                destTypeRow.slideUp(duration);
                remotePanel.slideUp(duration);
                forceWarning.fadeOut(duration);
                $('#btn_save').html('<b><i class="fa fa-floppy-o"></i> {{ lang._("Save Disablement Settings") }}</b>');
            } else {
                destTypeRow.slideDown(duration);
                if (!remote) {
                    /* level 2a: local history only */
                    remotePanel.slideUp(duration);
                    forceWarning.fadeOut(duration);
                    $('#btn_save').html('<b><i class="fa fa-floppy-o"></i> {{ lang._("Save Local Configuration") }}</b>');
                } else {
                    /* level 2b: local repository plus upstream push */
                    remotePanel.slideDown(duration);
                    $('#btn_save').html('<b><i class="fa fa-floppy-o"></i> {{ lang._("Save Remote Configuration") }}</b>');
                    if ($('#git\\.force_push').is(':checked')) {
                        forceWarning.stop(true, true).fadeIn(duration);
                    } else {
                        forceWarning.fadeOut(duration);
                    }
                }
            }
        }

        mapDataToFormUI({'frm_git': dataKey + '/get'}).done(function () {
            formatLayoutTransitions(0);
        });

        $(document).on('change', '#git\\.enabled, #git\\.dest_type, #git\\.force_push', function () {
            formatLayoutTransitions(200);
        });

        /*
         * in-flight gate for the save track. The disabled attribute stops the browser from
         * dispatching further clicks, but a second event already queued for the same tick
         * (rapid double click, programmatic trigger) is short-circuited here before it can
         * touch the DOM or the API. Every release path below clears the flag.
         */
        let isSaving = false;

        $('#btn_save').click(function () {
            if (isSaving) {
                /* instantly kill any rapid-fire event before it reaches the DOM or the API */
                return;
            }
            isSaving = true;

            var button = $(this);
            var progress = $('#save_progress');

            /* lock the button and spin its trailing icon while the endpoint is busy */
            button.prop('disabled', true);
            progress.addClass('fa fa-spinner fa-spin');

            saveFormToEndpoint(dataKey + '/set', 'frm_git', function () {
                /* re-sync first, then raise the floating confirmation */
                mapDataToFormUI({'frm_git': dataKey + '/get'}).done(function () {
                    formatLayoutTransitions(0);
                    showSaveToast('{{ lang._("Changes Applied Successfully!") }}');
                }).always(function () {
                    /*
                     * the lock is released only once the whole save track is finished, and
                     * released on failure too, so a dropped re-sync can never leave the button
                     * permanently disabled
                     */
                    progress.removeClass('fa-spinner fa-spin');
                    button.prop('disabled', false);
                    isSaving = false;
                });
            }, false, function () {
                /* validation rejected the payload: fields are marked, button must stay usable */
                progress.removeClass('fa-spinner fa-spin');
                button.prop('disabled', false);
                isSaving = false;
            });
        });

        $('#btn_test').click(function () {
            var button = $(this);
            var result = $('#inline_test_result');
            var progress = $('#test_progress');

            button.prop('disabled', true);
            progress.addClass('fa-spin');
            result.hide().removeClass('alert alert-success alert-danger').empty();

            $.post(dataKey + '/test_connection', getFormData('frm_git'), function (data) {
                var message = (data && data.message) ? data.message : '{{ lang._("unknown error") }}';
                /* remote output is untrusted, insert it as text only */
                var safe = $('<div/>').text(message).html();
                if (data && data.status === 'success') {
                    result.addClass('alert alert-success').html(
                        '<strong><i class="fa fa-check-circle"></i> {{ lang._("Connection successful") }}</strong> ' + safe
                    );
                } else {
                    result.addClass('alert alert-danger').html(
                        '<strong><i class="fa fa-exclamation-triangle"></i> {{ lang._("Verification failed") }}:</strong> ' + safe
                    );
                }
                result.fadeIn();
            }).fail(function () {
                result.addClass('alert alert-danger').html(
                    '<strong><i class="fa fa-exclamation-triangle"></i> {{ lang._("Communication error") }}:</strong> ' +
                    '{{ lang._("the request could not be completed.") }}'
                ).fadeIn();
            }).always(function () {
                progress.removeClass('fa-spin');
                button.prop('disabled', !$('#git\\.enabled').is(':checked') ||
                                        $('#git\\.dest_type').val() !== 'remote');
            });
        });
    });
</script>

    <button class="btn btn-primary" id="btn_save" type="button">
        <b><i class="fa fa-floppy-o"></i> {{ lang._("Save Settings") }}</b> <i id="save_progress" class="fa"></i>
    </button>
</div>
