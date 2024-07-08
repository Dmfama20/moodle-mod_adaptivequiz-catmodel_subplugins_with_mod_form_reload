<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Interface to be implemented when a custom CAT model wants to populate mod_form's fields related to it.
 *
 * @package    mod_adaptivequiz
 * @copyright  2023 Vitaly Potenko <potenkov@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_adaptivequiz\local\catmodel\form;

use MoodleQuickForm;

interface catmodel_mod_form_data_preprocessor {

    /**
     * Called in {@see \moodleform_mod::data_preprocessing()}, used to customize populating of form fields.
     *
     * Unlike the calling form's method, it accepts the form's values as the argument without a reference and returns the modified
     * values.
     *
     * @param array $formdefaultvalues
     * @param MoodleQuickForm $form
     * @return array Modified form values.
     */
    public function data_preprocessing_callback(array $formdefaultvalues, ?MoodleQuickForm $form = null): array;
}
