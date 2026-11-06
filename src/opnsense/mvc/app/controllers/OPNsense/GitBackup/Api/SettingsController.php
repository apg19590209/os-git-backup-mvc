<?php

/*
 * Copyright (c) 2026 OPNsense Device Monitor maintenance
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
 * THIS SOFTWARE IS PROVIDED ``AS IS`` AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

namespace OPNsense\GitBackup\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Backup\GitSettings;
use OPNsense\Core\Config;

/**
 * Class SettingsController
 * @package OPNsense\GitBackup
 */
class SettingsController extends ApiControllerBase
{
    /**
     * fields returned to the form, in the order they are rendered
     */
    private const FIELDS = ['enabled', 'url', 'branch', 'force_push', 'user', 'password', 'privkey'];

    /**
     * read the git backup settings, the destination type is returned as a key/value list so
     * setFormData() can build the dropdown and restore the selected entry
     * @return array
     */
    public function getAction()
    {
        $mdl = new GitSettings();
        $result = ['git' => []];
        foreach (self::FIELDS as $field) {
            $result['git'][$field] = (string)$mdl->getNodeByReference($field);
        }
        $result['git']['dest_type'] = [];
        foreach ($mdl->dest_type->getNodeData() as $key => $option) {
            $result['git']['dest_type'][] = [
                'key' => $key,
                'value' => $option['value'],
                'selected' => $option['selected'],
            ];
        }
        return $result;
    }

    /**
     * store the posted git backup settings
     * @return array
     */
    public function setAction()
    {
        $result = ['result' => 'failed'];
        if ($this->request->isPost() && $this->request->hasPost('git')) {
            $mdl = new GitSettings();
            $mdl->setNodes($this->request->getPost('git'));

            $validations = [];
            foreach ($mdl->performValidation() as $message) {
                /* prefix with the form group, so handleFormValidation() can highlight the field */
                $validations['git.' . $message->getField()] = $message->getMessage();
            }

            if (empty($validations)) {
                $mdl->serializeToConfig();
                Config::getInstance()->save();
                $result = ['result' => 'saved'];
            } else {
                $result['validations'] = $validations;
            }
        }
        return $result;
    }

    /**
     * non destructive connectivity check against the upstream repository
     * @return array status/message pair
     */
    public function testConnectionAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => gettext('Invalid request method.')];
        }

        try {
            $backend = new \OPNsense\Backup\Git();
            return $backend->testConnection((array)$this->request->getPost('git'));
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
