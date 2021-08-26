<?php
declare(strict_types=1);

namespace ILIAS\Modules\EmployeeTalk\TalkSeries\Entity;
use ActiveRecord;

/**
 * Class EmployeeTalkSerie
 */
class EmployeeTalkSerieSettings extends ActiveRecord
{

    /** @var string  */
     protected $connector_container_name = 'etal_serie';
    /**
     * @var int $id
     * @con_has_field  true
     * @con_is_primary true
     * @con_fieldtype  integer
     * @con_length     8
     * @con_is_notnull true
     */
    protected $id = -1;
    /**
     * @var integer $editing_locked
     *
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_length     1
     * @con_is_notnull true
     */
    protected $editing_locked = 0;


    /**
     * @return int
     */
    public function getId() : int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return EmployeeTalkSerieSettings
     */
    public function setId(int $id) : EmployeeTalkSerieSettings
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getEditingLocked() : int
    {
        return intval($this->editing_locked);
    }

    /**
     * @param int $editing_locked
     * @return EmployeeTalkSerieSettings
     */
    public function setEditingLocked(int $editing_locked) : EmployeeTalkSerieSettings
    {
        $this->editing_locked =  $editing_locked;
        return $this;
    }
}
