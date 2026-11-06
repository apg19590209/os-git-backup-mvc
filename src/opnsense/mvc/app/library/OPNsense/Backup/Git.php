<?php

/*
 * Copyright (C) 2020 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Backup;

use OPNsense\Backup\GitSettings;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Core\Shell;

/**
 * Class Git backup
 * @package OPNsense\Backup
 */
class Git extends Base implements IBackupProvider
{
    /**
     * the real settings, used to filter posted keys; getConfigurationFields() no longer exposes them
     * because the form lives on the dedicated MVC page
     */
    private const SETTINGS_FIELDS = ['enabled', 'dest_type', 'url', 'branch', 'force_push', 'user', 'password', 'privkey'];

    /**
     * @inheritdoc
     */
    public function getConfigurationFields()
    {
        /*
         * The configuration is maintained on the dedicated MVC page (System > Configuration > Git
         * Backup). The legacy backups page renders one label cell and one input cell per field and has
         * no "header" row type, so the notice closes the label cell and emits its own full-width row.
         * The trailing hidden row absorbs the empty input cell the renderer still produces here.
         * The scoped style block then hides the two artefacts the legacy renderer emits
         * unconditionally: the muted info icon abandoned in the closed label cell and the
         * "Setup/Test Git" submit button row. Both selectors stay scoped to this provider, so no
         * other form control (save/download/restore) is affected, and a browser without :has()
         * simply falls back to the current rendering.
         */
        $notice = gettext(
            '<strong><i class="fa fa-info-circle"></i> Interface Migration Notice:</strong> ' .
            'This configuration panel has been upgraded to a modern MVC dashboard. Please use the new ' .
            'dedicated <b><a href="/ui/gitbackup">System &rarr; Configuration &rarr; Git Backup</a></b> ' .
            'sidebar menu to manage your local or remote repository settings.'
        );
        $banner = '<span class="git-mvc-notice-anchor"></span>' .
                  '<style>' .
                  'td:has(> .git-mvc-notice-anchor) > i.fa-info-circle { display: none; }' .
                  'button[name="setup_Git"] { display: none; }' .
                  'tr:has(> td > button[name="setup_Git"]) { display: none; }' .
                  '</style>' .
                  '</td></tr><tr><td colspan="2"><div class="alert alert-info" style="margin-bottom: 0;">' .
                  $notice .
                  '</div></td></tr><tr style="display: none;">';
        return [
            [
                "name" => "mvc_migration_notice",
                "type" => "info",
                "label" => $banner,
                "value" => "",
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return gettext("Git");
    }

    /**
     * @inheritdoc
     */
    public function setConfiguration($conf)
    {
        $mdl = new GitSettings();
        /* only apply keys that exist in the model; the legacy page now posts a notice-only payload */
        $properties = [];
        foreach ((array)$conf as $key => $value) {
            if ($mdl->getNodeByReference($key) !== null) {
                $properties[$key] = $value;
            }
        }
        if (empty($properties)) {
            return [];
        }
        $this->setModelProperties($mdl, $properties);
        $validation_messages = $this->validateModel($mdl);
        if (empty($validation_messages)) {
            $mdl->serializeToConfig();
            Config::getInstance()->save();
        }
        return $validation_messages;
    }

    /**
     * Backup is responsible for initialising the local repo and pushing it to upstream.
     * To ensure initial content, we should trigger a 'system event config_changed' which should enforce a
     * add + commit in our (newly created) repo.
     *
     * Since our backup method is also called from the userinterface directly, we should try to prevent the need
     * for elevated rights. Since all actions are concentrated within the config directory, we only need read/exec
     * access on git. (detaching this further would deviate the implementation from the existing ones)
     *
     * @return array filelist
     */
    public function backup()
    {
        $targetdir = '/conf/backup/git';
        $mdl = new GitSettings();

        if (!$this->isEnabled()) {
            /* provider disabled, do not process any backup */
            return [];
        }

        if (!is_dir($targetdir)) {
            mkdir($targetdir);
        }

        if (!is_dir("{$targetdir}/.git")) {
            Shell::run_safe('/usr/local/bin/git init %s', $targetdir);
        }

        // When there are unprocessed config backups, flush them out. The config_changed event performs the
        // local "git add config.xml" + "git commit" sequence inside the target directory.
        (new Backend())->configdRun('system event config_changed');

        if ((string)$mdl->dest_type === 'local') {
            /* local version control only: no upstream bindings, credentials or push */
            return Shell::shell_safe('/usr/local/bin/git -C %s ls-files', $targetdir, true);
        }

        // XXX: since our git backup is plain text and already contains the private key, it doesn't really matter
        //      to keep the same key in the git directory (we're not going to push it)
        $ident_file = "{$targetdir}/identity";
        $privkey = trim(str_replace("\r", "", (string)$mdl->privkey)) . "\n";
        file_put_contents($ident_file, $privkey);
        chmod("{$targetdir}/identity", 0600);

        // configure upstream
        Shell::run_safe('/usr/local/bin/git -C %s config core.sshCommand %s', [
            $targetdir, "ssh -i {$ident_file} -o StrictHostKeyChecking=accept-new -o PasswordAuthentication=no",
        ]);

        $url = (string)$mdl->url;
        $pos = strpos($url, '//');

        // inject credentials in url (either username or username:password, depending on transport)
        if (stripos(trim((string)$mdl->url), 'http') === 0) {
            $cred = urlencode((string)$mdl->user) . ":" . urlencode((string)$mdl->password);
            $url = substr($url, 0, $pos + 2) . "{$cred}@" . substr($url, $pos + 2);
        } else {
            $url = substr($url, 0, $pos + 2) . urlencode((string)$mdl->user) . "@" . substr($url, $pos + 2);
        }

        Shell::run_safe('/usr/local/bin/git -C %s remote remove origin', $targetdir);
        Shell::run_safe('/usr/local/bin/git -C %s remote add origin %s', [$targetdir, $url]);
        $gitfrmt = ['(/usr/local/bin/git -C %s push'];
        if ($mdl->force_push->isEqual('1')) {
            $gitfrmt[] = '--force';
        }
        $gitfrmt[] = 'origin %s && echo "__exit_ok__") 2>&1';
        $pushtxt = Shell::shell_safe($gitfrmt, [$targetdir, "master:{$mdl->branch}"]);

        if (strpos($pushtxt, '__exit_ok__')) {
            $error_type = null;
        } elseif (strpos($pushtxt, 'Permission denied') || strpos($pushtxt, 'Authentication failed ')) {
            $error_type = "authentication failure";
        } elseif (strpos($pushtxt, 'WARNING: REMOTE HOST IDENTIFICATION HAS CHANGED')) {
            $error_type = "ssh hostkey changed";
        } elseif (strpos($pushtxt, "remote contains work that you do")) {
            $error_type = "git out of sync";
        } else {
            $error_type = "unknown error, check log for details";
        }

        if (!empty($error_type)) {
            syslog(LOG_ERR, "git-backup {$error_type} (" . str_replace("\n", " ", $pushtxt) . ")");
            throw new \Exception($error_type);
        } else {
            // return filelist in git
            return Shell::shell_safe('/usr/local/bin/git -C %s ls-files', $targetdir, true);
        }
    }

    /**
     * Non destructive connectivity check against the configured upstream.
     * Uses "git ls-remote" so nothing is committed, pushed, initialised or written to the
     * local repository (and therefore nothing is written to config.xml either).
     *
     * @param array|null $params posted configuration (Git_* keys), defaults to the stored settings
     * @return array status/message pair, status is either "success" or "error"
     */
    public function testConnection($params = null)
    {
        $mdl = new GitSettings();
        if (is_array($params)) {
            /* accept both the legacy "Git_<field>" keys and the plain "<field>" keys used by the API */
            $prefix = (new \ReflectionClass($this))->getShortName() . '_';
            $known = self::SETTINGS_FIELDS;
            $map = [];
            foreach ($params as $key => $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $name = strpos($key, $prefix) === 0 ? substr($key, strlen($prefix)) : $key;
                if (in_array($name, $known, true)) {
                    $map[$name] = $value;
                }
            }
            $this->setModelProperties($mdl, $map);
        }

        $plain_url = trim((string)$mdl->url);
        if ($plain_url === '' || strpos($plain_url, '//') === false) {
            return ['status' => 'error', 'message' => gettext('No upstream location configured.')];
        }

        /* inject the credentials exactly like backup() does, they are never stored in the url */
        $pos = strpos($plain_url, '//');
        if (stripos($plain_url, 'http') === 0) {
            $cred = urlencode((string)$mdl->user) . ':' . urlencode((string)$mdl->password);
            $url = substr($plain_url, 0, $pos + 2) . "{$cred}@" . substr($plain_url, $pos + 2);
        } else {
            $url = substr($plain_url, 0, $pos + 2) . urlencode((string)$mdl->user) . '@' . substr($plain_url, $pos + 2);
        }

        /* stage the private key in a temporary file, it is removed again below */
        $ident_file = null;
        $ssh_command = 'ssh -o StrictHostKeyChecking=accept-new -o PasswordAuthentication=no ' .
                       '-o BatchMode=yes -o ConnectTimeout=10';
        $privkey = trim(str_replace("\r", "", (string)$mdl->privkey));
        if ($privkey !== '') {
            $ident_file = tempnam(sys_get_temp_dir(), 'git-test-');
            if ($ident_file === false) {
                return ['status' => 'error', 'message' => gettext('Unable to stage the private key.')];
            }
            file_put_contents($ident_file, $privkey . "\n");
            chmod($ident_file, 0600);
            $ssh_command .= " -i {$ident_file}";
        }

        $branch = trim((string)$mdl->branch);
        $ref = $branch === '' ? 'HEAD' : "refs/heads/{$branch}";
        $output = Shell::shell_safe(
            '(/usr/bin/env GIT_TERMINAL_PROMPT=0 GIT_SSH_COMMAND=%s /usr/local/bin/git ls-remote %s %s ' .
            '&& echo "__exit_ok__") 2>&1',
            [$ssh_command, $url, $ref]
        );

        if ($ident_file !== null) {
            @unlink($ident_file);
        }

        if ($url !== $plain_url) {
            /* never echo the injected credentials back to the browser */
            $output = str_replace($url, $plain_url, $output);
        }
        $clean = preg_replace('/\s+/', ' ', trim(str_replace('__exit_ok__', '', $output)));

        if (strpos($output, '__exit_ok__') === false) {
            $haystack = strtolower($clean);
            if (
                strpos($haystack, 'permission denied') !== false ||
                strpos($haystack, 'authentication failed') !== false ||
                strpos($haystack, 'publickey') !== false
            ) {
                $message = gettext('Authentication failed, check the user name and the credentials.');
            } elseif (strpos($haystack, 'identification has changed') !== false) {
                $message = gettext('The remote host key has changed.');
            } elseif (
                strpos($haystack, 'could not resolve host') !== false ||
                strpos($haystack, 'connection refused') !== false ||
                strpos($haystack, 'timed out') !== false
            ) {
                $message = gettext('The remote host could not be reached.');
            } else {
                $message = gettext('The remote repository could not be queried.');
            }
            return ['status' => 'error', 'message' => trim("{$message} {$clean}")];
        }

        if ($clean === '') {
            return [
                'status' => 'error',
                'message' => sprintf(gettext('Authenticated, but %s was not found upstream.'), $ref),
            ];
        }

        return [
            'status' => 'success',
            'message' => sprintf(gettext('Connection successful, %s resolved upstream.'), $ref),
        ];
    }

    /**
     * @inheritdoc
     */
    public function isEnabled()
    {
        return (string)(new GitSettings())->enabled === "1";
    }
}
