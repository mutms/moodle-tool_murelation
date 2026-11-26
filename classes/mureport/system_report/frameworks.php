<?php
// This file is part of MuTMS suite of plugins for Moodle™ LMS.
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <https://www.gnu.org/licenses/>.

// phpcs:disable moodle.Files.BoilerplateComment.CommentEndedTooSoon

namespace tool_murelation\mureport\system_report;

use tool_mureport\mureport\system_report;
use tool_murelation\mureport\entity\framework;
use core\lang_string;
use core\url;

/**
 * All user relation frameworks, for framework managers.
 *
 * @package     tool_murelation
 * @copyright   2026 Petr Skoda
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class frameworks extends system_report {
    /**
     * Make report instance.
     *
     * @return self
     */
    public static function make_instance(): self {
        return self::from_constraints([]);
    }

    #[\Override]
    public static function get_title(): string {
        return get_string('frameworks', 'tool_murelation');
    }

    #[\Override]
    public static function get_url(): ?url {
        return new url('/admin/tool/murelation/management/index.php');
    }

    #[\Override]
    protected function check_access(): void {
        $this->require_capability('tool/murelation:viewframeworks');
    }

    #[\Override]
    protected function define(): void {
        $this->set_main_entity(new framework('framework'));

        $this->set_default_columns([
            'framework:name',
            'framework:idnumber',
            'framework:uimode',
            'framework:supervisortitle',
            'framework:supervisorrole',
            'framework:subordinatetitle',
        ]);
        $this->set_default_filters([
            'framework:name' => ['multisearch' => true],
            'framework:idnumber' => ['multisearch' => true],
            'framework:uimode',
            'framework:visibility',
        ]);
        $this->set_multisearch_label(new lang_string('frameworksearch', 'tool_murelation'));
        $this->set_default_sorting(['framework:name' => 'asc']);
    }
}
