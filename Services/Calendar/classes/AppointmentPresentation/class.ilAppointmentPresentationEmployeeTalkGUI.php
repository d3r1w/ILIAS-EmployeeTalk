<?php

/**
 * Class ilAppointmentPresentationEmployeeTalkGUI
 *
 * @ilCtrl_IsCalledBy ilAppointmentPresentationEmployeeTalkGUI: ilCalendarAppointmentPresentationGUI
 *
 * @ingroup ServicesCalendar
 */
class ilAppointmentPresentationEmployeeTalkGUI extends ilAppointmentPresentationGUI implements ilCalendarAppointmentPresentation
{

    /**
     * ilAppointmentPresentationEmployeeTalkGUI constructor.
     */
    public function __construct($a_appointment, $a_info_screen, $a_toolbar, $a_list_item)
    {
        parent::__construct($a_appointment, $a_info_screen, $a_toolbar, $a_list_item);

        $this->lng->loadLanguageModule(ilObjEmployeeTalk::TYPE);
    }

    public function collectPropertiesAndActions(): void
    {
        $talk = new ilObjEmployeeTalk($this->getObjIdForAppointment(), false);

        $superior = $this->getUserName($talk->getOwner(), true);
        $employee = $this->getUserName($talk->getData()->getEmployee(), true);

        $this->addObjectLinks($talk->getId(), $this->appointment);

        // get talk ref id (this is possible, since talks only have one ref id)
        $refs = ilObject::_getAllReferences($talk->getId());
        $etalRef = current($refs);
        $this->addAction($this->lng->txt("etal_open"), ilLink::_getStaticLink($etalRef, ilObjEmployeeTalk::TYPE));

        $this->addInfoSection($this->lng->txt('obj_etal'));
        $this->addInfoProperty($this->lng->txt('title'), $talk->getTitle());
        $this->addEventDescription($this->appointment);

        $this->addEventLocation($this->appointment);
        $this->addLastUpdate($this->appointment);
        $this->addListItemProperty($this->lng->txt("il_orgu_superior"), $superior);
        $this->addListItemProperty($this->lng->txt("il_orgu_employee"), $employee);


        $this->addInfoProperty($this->lng->txt("il_orgu_superior"), $superior);
        $this->addInfoProperty($this->lng->txt("il_orgu_employee"), $employee);

        parent::collectPropertiesAndActions();
    }

}