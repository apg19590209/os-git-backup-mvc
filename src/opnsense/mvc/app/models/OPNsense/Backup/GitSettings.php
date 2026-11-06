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

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;

/**
 * Class GitSettings
 * @package Backup
 */
class GitSettings extends BaseModel
{
    /**
     * When the destination type is "local" only a local repository is maintained, in which case the remote
     * transport fields (url, user) are not applicable and must not be validated as being required.
     * @param bool $validateFullModel validate the full model or only changed fields
     * @return \OPNsense\Base\Validation
     */
    public function performValidation($validateFullModel = false)
    {
        if ((string)$this->dest_type === 'local') {
            $this->url->setConstraints([]);
            $this->user->setConstraints([]);
        }

        $validation = parent::performValidation($validateFullModel);

        /*
         * The xml DependConstraint on url/user only runs for nodes that changed in this request, so a
         * save that merely switches the panel into remote mode would not be rejected. Re-check the
         * pair explicitly and only add a message the constraint did not already report.
         */
        if ((string)$this->enabled === '1' && (string)$this->dest_type === 'remote') {
            $required = [
                'url' => gettext('Required field.'),
                'user' => gettext('Required field.'),
            ];
            foreach ($required as $field => $message) {
                if (trim((string)$this->getNodeByReference($field)) !== '') {
                    continue;
                }
                $reported = false;
                foreach ($validation as $existing) {
                    if ($existing->getField() === $field) {
                        $reported = true;
                        break;
                    }
                }
                if (!$reported) {
                    $validation->appendMessage(new Message($message, $field));
                }
            }
        }

        return $validation;
    }
}
