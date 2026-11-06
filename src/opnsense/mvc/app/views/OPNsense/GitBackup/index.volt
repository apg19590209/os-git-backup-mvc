<script>
    $(document).ready(function () {
        var apiBase = '/api/gitbackup/settings';

        function gitLayout() {
            var enabled = $('#git\\.enabled').is(':checked');
            var remote = $('#git\\.dest_type').val() === 'remote';
            var forcePush = $('#git\\.force_push').is(':checked');
            var remoteFields = $('#git\\.url, #git\\.branch, #git\\.force_push, #git\\.user, #git\\.password, #git\\.privkey');

            $('#git\\.dest_type').prop('disabled', !enabled);
            $('#btn_test').prop('disabled', !enabled || !remote);

            /* disabling is safe here: the API only stores the keys it actually receives */
            remoteFields.prop('disabled', !enabled || !remote);
            remoteFields.closest('tr').css('opacity', (!enabled || !remote) ? '0.5' : '1');
            $('#git\\.dest_type').closest('tr').css('opacity', enabled ? '1' : '0.5');

            if (!enabled) {
                $('#btn_save').html('<b><i class="fa fa-floppy-o"></i> {{ lang._("Save Settings") }}</b>');
            } else if (!remote) {
                $('#btn_save').html('<b><i class="fa fa-floppy-o"></i> {{ lang._("Save Local Configuration") }}</b>');
            } else {
                $('#btn_save').html('<b><i class="fa fa-floppy-o"></i> {{ lang._("Save Remote Configuration") }}</b>');
            }

            if (enabled && remote && forcePush) {
                $('#git_force_warning').stop(true, true).fadeIn(200);
            } else {
                $('#git_force_warning').stop(true, true).fadeOut(200);
            }
        }

        mapDataToFormUI({'frm_git': apiBase + '/get'}).done(function () {
            gitLayout();
        });

        $(document).on('change', '#git\\.enabled, #git\\.dest_type, #git\\.force_push', gitLayout);

        $('#btn_save').click(function () {
            saveFormToEndpoint(apiBase + '/set', 'frm_git', function () {
                mapDataToFormUI({'frm_git': apiBase + '/get'}).done(gitLayout);
            });
        });

        $('#btn_test').click(function () {
            var button = $(this);
            var result = $('#git_test_result');
            button.prop('disabled', true);
            $('#test_progress').addClass('fa-spinner fa-spin');
            result.hide().removeClass('alert alert-success alert-danger').empty();

            $.post(apiBase + '/test_connection', getFormData('frm_git'), function (data) {
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
                $('#test_progress').removeClass('fa-spinner fa-spin');
                button.prop('disabled', !$('#git\\.enabled').is(':checked') ||
                                        $('#git\\.dest_type').val() !== 'remote');
            });
        });
    });
</script>

{{ partial("layout_partials/base_form", ['fields': formSettings, 'id': 'frm_git']) }}

<div class="col-md-12">
    <div id="git_force_warning" class="alert alert-danger" style="display: none;">
        <strong><i class="fa fa-exclamation-triangle"></i> {{ lang._("WARNING: Destructive Action") }}</strong><br>
        {{ lang._("Force Push overwrites and erases the history of the upstream branch with the local state. Use with extreme caution.") }}
    </div>
    <div id="git_test_result" style="display: none; padding: 8px; border-radius: 3px;"></div>
    <button class="btn btn-primary" id="btn_save" type="button">
        <b>{{ lang._("Save Configuration") }}</b>
    </button>
    <button class="btn btn-default" id="btn_test" type="button">
        <b>{{ lang._("Test Connection") }}</b> <i id="test_progress" class="fa"></i>
    </button>
</div>
