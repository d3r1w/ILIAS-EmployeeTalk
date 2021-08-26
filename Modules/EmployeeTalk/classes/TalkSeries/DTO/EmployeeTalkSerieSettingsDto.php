<?php
declare(strict_types=1);

namespace ILIAS\Modules\EmployeeTalk\TalkSeries\DTO;
/**
 * Class EmployeeTalkSerieSettingsDto
 */
class EmployeeTalkSerieSettingsDto {

    /** @var int  $objectId*/
    private $objectId = -1;
    /** @var bool $lockedEditing */
    private $lockedEditing = false;

    /**
     * EmployeeTalk constructor.
     * @param int $objectId
     * @param bool $lockedEditing
     */
    public function __construct(
        int $objectId,
        bool $lockedEditing
    )
    {
        $this->objectId = $objectId;
        $this->lockedEditing = $lockedEditing;
    }

    /**
     * @return int
     */
    public function getObjectId() : int
    {
        return $this->objectId;
    }

    /**
     * @param int $objectId
     * @return EmployeeTalkSerieSettingsDto
     */
    public function setObjectId(int $objectId) : EmployeeTalkSerieSettingsDto
    {
        $this->objectId = $objectId;
        return $this;
    }

    /**
     * @return bool
     */
    public function isLockedEditing() : bool
    {
        return $this->lockedEditing;
    }

    /**
     * @param bool $lockedEditing
     * @return EmployeeTalkSerieSettingsDto
     */
    public function setLockedEditing(bool $lockedEditing) : EmployeeTalkSerieSettingsDto
    {
        $this->lockedEditing = $lockedEditing;
        return $this;
    }


}