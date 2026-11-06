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

namespace OPNsense\GitBackup;

/**
 * Class IndexController
 * @package OPNsense\GitBackup
 */
class IndexController extends \OPNsense\Base\IndexController
{
    /**
     * fields rendered inside the always visible core panel, everything else goes into the
     * collapsible remote drawer
     */
    private const CORE_FIELDS = ['git.enabled', 'git.dest_type'];

    /**
     * flatten the parsed form definition into a plain field list
     * @param array $form parsed form definition (see ControllerBase::getForm)
     * @param array|null $keep field ids to select, null selects everything
     * @return array
     */
    private function collectFields($form, $keep = null)
    {
        $result = [];
        foreach ($form['sections'] as $section) {
            foreach ($section['children'] as $field) {
                if ($keep === null || in_array($field['id'], $keep, true)) {
                    $result[] = $field;
                }
            }
        }
        return $result;
    }

    /**
     * git backup settings page, works on the existing //system/backup/git mount
     */
    public function indexAction()
    {
        $form = $this->getForm('settings');
        $all = [];
        foreach ($this->collectFields($form) as $field) {
            $all[] = $field['id'];
        }
        $this->view->formCoreFields = $this->collectFields($form, self::CORE_FIELDS);
        $this->view->formRemoteFields = $this->collectFields($form, array_diff($all, self::CORE_FIELDS));
        $this->view->pick('OPNsense/GitBackup/index');
    }
}
