<?php
declare(strict_types=1);

namespace ILIAS\Modules\EmployeeTalk\Talk;

interface EmployeeTalkPositionAccessLevel
{
    const VIEW = 'read_employee_talk';
    const EDIT = 'edit_employee_talk';
    const CREATE = 'create_employee_talk';
}