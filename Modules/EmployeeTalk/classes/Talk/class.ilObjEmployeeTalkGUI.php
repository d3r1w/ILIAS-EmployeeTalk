<?php
declare(strict_types=1);

use ILIAS\EmployeeTalk\UI\ControlFlowCommand;
use OrgUnit\User\ilOrgUnitUser;
use ILIAS\Modules\EmployeeTalk\Talk\DAO\EmployeeTalk;
use ILIAS\Modules\EmployeeTalk\Talk\EmployeeTalkPeriod;
use ILIAS\EmployeeTalk\Service\EmployeeTalkEmailNotificationService;
use ILIAS\EmployeeTalk\Service\VCalendarFactory;
use ILIAS\EmployeeTalk\Service\EmployeeTalkEmailNotification;
use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;

/**
 * Class ilObjEmployeeTalkGUI
 *
 * @ilCtrl_IsCalledBy ilObjEmployeeTalkGUI: ilEmployeeTalkMyStaffListGUI
 * @ilCtrl_Calls      ilObjEmployeeTalkGUI: ilCommonActionDispatcherGUI
 * @ilCtrl_Calls      ilObjEmployeeTalkGUI: ilRepositorySearchGUI
 * @ilCtrl_Calls      ilObjEmployeeTalkGUI: ilColumnGUI, ilObjectCopyGUI, ilUserTableGUI
 * @ilCtrl_Calls      ilObjEmployeeTalkGUI: ilPermissionGUI
 * @ilCtrl_Calls      ilObjEmployeeTalkGUI: ilInfoScreenGUI
 */
final class ilObjEmployeeTalkGUI extends ilObjectGUI
{
    /**
     * @var \ILIAS\DI\Container $container
     */
    private $container;

    /**
     * @var ilPropertyFormGUI $form
     */
    private $form;

    /**
     * @var boolean $isReadonly
     */
    private $isReadonly;

    public function __construct()
    {
        parent::__construct([], $_GET["ref_id"], true, false);

        $this->container = $GLOBALS["DIC"];

        $this->container->language()->loadLanguageModule('mst');
        $this->container->language()->loadLanguageModule('trac');
        $this->container->language()->loadLanguageModule('etal');
        $this->container->language()->loadLanguageModule('dateplaner');

        $this->type = 'etal';

        $this->setReturnLocation("save", strtolower(ilEmployeeTalkMyStaffListGUI::class));

        // get the standard template
        //$this->container->ui()->mainTemplate()->loadStandardTemplate();
        $this->container->ui()->mainTemplate()->setTitle($this->container->language()->txt('mst_my_staff'));
    }

    private function checkAccessOrFail(): void
    {
        $access = \ILIAS\MyStaff\ilMyStaffAccess::getInstance();
        if (!$access->hasCurrentUserAccessToMyStaff() || !$access->hasCurrentUserAccessToUser($this->object->getData()->getEmployee())) {
            ilUtil::sendFailure($this->lng->txt("permission_denied"), true);
            $this->ctrl->redirectByClass(ilDashboardGUI::class, "");
        }
    }

    public function executeCommand() : bool
    {
        $this->checkAccessOrFail();
        $this->isReadonly = $this->access->isTalkReadonlyByCurrentUser($this->ref_id);

        // determine next class in the call structure
        $next_class = $this->container->ctrl()->getNextClass($this);

        switch($next_class) {
            case 'ilpermissiongui':
                parent::prepareOutput();
                $this->tabs_gui->activateTab('perm_settings');
                $ilPermissionGUI = new ilPermissionGUI($this);
                $this->ctrl->forwardCommand($ilPermissionGUI);
                break;
            case 'ilinfoscreengui':
                parent::prepareOutput();
                $this->tabs_gui->activateTab('info_short');
                $ilInfoScreenGUI = new ilInfoScreenGUI($this);
                $this->ctrl->forwardCommand($ilInfoScreenGUI);
                break;
            case strtolower(ilRepositorySearchGUI::class):
                $repo = new ilRepositorySearchGUI();
                //$repo->addUserAccessFilterCallable(function () {
                //    $orgUnitUser = ilOrgUnitUser::getInstanceById($this->container->user()->getId());
                //    $orgUnitUser->addPositions()                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        ;
                //});
                $this->container->ctrl()->forwardCommand($repo);
                break;
            case strtolower(ilEmployeeTalkAppointmentGUI::class):
                $appointmentGUI = new ilEmployeeTalkAppointmentGUI(
                    $this->tpl,
                    $this->lng,
                    $this->ctrl,
                    $this->container->tabs(),
                    $this->object
                );
                $this->container->ctrl()->forwardCommand($appointmentGUI);
                break;
            default:
                return parent::executeCommand();
        }

        //$this->container->ui()->mainTemplate()->printToStdout();
        return true;
    }

    public function editObject()
    {
        $this->tabs_gui->activateTab('view_content');

        $form = $this->initEditForm();
        $values = $this->getEditFormValues();
        if ($values) {
            $form->setValuesByArray($values);
        }

        $this->addExternalEditFormCustom($form);

        $this->tpl->setContent($form->getHTML());
    }

    public function updateObject()
    {
        $form = $this->initEditForm();
        if ($form->checkInput() &&
            $this->validateCustom($form)) {
            $this->object->setTitle($form->getInput("title"));
            $this->object->setDescription($form->getInput("desc"));
            $this->updateCustom($form);
            $this->object->update();

            $this->afterUpdate();
            return;
        }

        // display form again to correct errors
        $this->tabs_gui->activateTab("settings");
        $form->setValuesByPost();
        $this->tpl->setContent($form->getHtml());
    }

    public function confirmedDeleteObject(): void
    {
        if (isset($_POST["mref_id"])) {
            $_SESSION["saved_post"] = array_unique(array_merge($_SESSION["saved_post"], $_POST["mref_id"]));
        }

        $ru = new ilRepUtilGUI($this);
        $refIds = ilSession::get("saved_post");
        $talks = [];

        foreach ($refIds as $refId) {
            $talks[] = new ilObjEmployeeTalk(intval($refId), true);
        }

        $ru->deleteObjects($_GET["ref_id"], $refIds);
        $trashEnabled = boolval($this->container->settings()->get('enable_trash'));

        if ($trashEnabled) {
            foreach ($talks as $talk) {
                $talk->delete();
            }
        }

        ilSession::clear("saved_post");

        $this->sendNotification($talks);

        $this->ctrl->redirectByClass(strtolower(ilEmployeeTalkMyStaffListGUI::class), ControlFlowCommand::DEFAULT, "", false);
    }

    /**
     * @param ilObjEmployeeTalk[] $talks
     */
    private function sendNotification(array $talks): void {

        $firstTalk = $talks[0];
        $talkTitle = $firstTalk->getTitle();
        $superior = new ilObjUser($firstTalk->getOwner());
        $employee = new ilObjUser($firstTalk->getData()->getEmployee());
        $superiorName = $superior->getFullname();
        $series = $firstTalk->getParent();

        $dates = [];
        foreach ($talks as $talk) {
            $data = $talk->getData();
            $startDate = $data->getStartDate()->get(IL_CAL_DATETIME);

            $dates[] = $startDate;
        }

        $message = new EmployeeTalkEmailNotification(
            sprintf($this->lng->txt('notification_talks_removed'), $superiorName),
            $this->lng->txt('notification_talks_date_list_header'),
            sprintf($this->lng->txt('notification_talks_talk_title'), $talkTitle),
            $this->lng->txt('notification_talks_date_details'),
            $dates
        );

        // Check if we deleted the last talk of the series
        $vCalSender = null;
        if ($series->hasChildren()) {
            $vCalSender = new EmployeeTalkEmailNotificationService(
                $message,
                $talkTitle,
                $employee,
                $superior,
                VCalendarFactory::getInstanceFromTalks($series)
            );
        } else {
            $vCalSender = new EmployeeTalkEmailNotificationService(
                $message,
                $talkTitle,
                $employee,
                $superior,
                VCalendarFactory::getEmptyInstance($series, $talkTitle)
            );
        }

        $vCalSender->send();
    }

    public function cancelDeleteObject()
    {
        ilSession::clear("saved_post");

        $this->ctrl->redirectByClass(strtolower(ilEmployeeTalkMyStaffListGUI::class), ControlFlowCommand::DEFAULT, "", false);
    }

    protected function initEditForm()
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;

        $lng->loadLanguageModule($this->object->getType());

        include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
        $form = new ilPropertyFormGUI();
        $form->setFormAction($this->ctrl->getFormAction($this, "update"));

        $form->setTitle($this->lng->txt($this->object->getType() . "_edit"));

        // title
        $ti = new ilTextInputGUI($this->lng->txt("title"), "title");
        $ti->setSize(min(40, ilObject::TITLE_LENGTH));
        $ti->setMaxLength(ilObject::TITLE_LENGTH);
        $ti->setRequired(true);
        $ti->setDisabled($this->isReadonly);
        $form->addItem($ti);

        // description
        $ta = new ilTextAreaInputGUI($this->lng->txt("description"), "desc");
        $ta->setCols(40);
        $ta->setRows(2);
        $ta->setDisabled($this->isReadonly);
        $form->addItem($ta);

        $this->initEditCustomForm($form);

        if (!$this->isReadonly) {
            $form->addCommandButton("update", $this->lng->txt("save"));
        }
        //$this->form->addCommandButton("cancelUpdate", $lng->txt("cancel"));

        return $form;
    }

    public function addExternalEditFormCustom(ilPropertyFormGUI $a_form)
    {
        /**
         * @var EmployeeTalk $data
         */
        $data = $this->object->getData();

        $location = new ilTextInputGUI("Location", "etal_location");
        $location->setMaxLength(200);
        $location->setValue($data->getLocation());
        $location->setDisabled($this->isReadonly);
        $a_form->addItem($location);

        $superior = new ilTextInputGUI($this->lng->txt("superior"), "etal_superior");
        $superior->setDisabled(true);
        $superior->setValue(ilObjUser::_lookupLogin(intval($this->object->getOwner())));
        $a_form->addItem($superior);

        $login = new ilTextInputGUI($this->lng->txt("employee"), "etal_employee");
        $login->setDisabled(true);
        $login->setValue(ilObjUser::_lookupLogin($data->getEmployee()));
        $a_form->addItem($login);

        $completed = new ilCheckboxInputGUI('Completed', 'etal_completed');
        $completed->setChecked($data->isCompleted());
        $completed->setDisabled($this->isReadonly);
        $a_form->addItem($completed);

        $this->container->ctrl()->setParameterByClass(strtolower(ilEmployeeTalkAppointmentGUI::class), 'ref_id', $this->ref_id);

        if (!$this->isReadonly) {
            $btnChangeThis = ilLinkButton::getInstance();
            $btnChangeThis->setCaption("change_date_of_talk");
            $editMode = '&' . ilEmployeeTalkAppointmentGUI::EDIT_MODE . '=' . ilEmployeeTalkAppointmentGUI::EDIT_MODE_APPOINTMENT;
            $btnChangeThis->setUrl($this->ctrl->getLinkTargetByClass(strtolower(ilEmployeeTalkAppointmentGUI::class), ControlFlowCommand::UPDATE_INDEX) . $editMode);
            $this->toolbar->addButtonInstance($btnChangeThis);

            $btnChangeAll = ilLinkButton::getInstance();
            $btnChangeAll->setCaption("change_date_of_series");
            $editMode = '&' . ilEmployeeTalkAppointmentGUI::EDIT_MODE . '=' . ilEmployeeTalkAppointmentGUI::EDIT_MODE_SERIES;
            $btnChangeAll->setUrl($this->ctrl->getLinkTargetByClass(strtolower(ilEmployeeTalkAppointmentGUI::class), ControlFlowCommand::UPDATE_INDEX) . $editMode);
            $this->toolbar->addButtonInstance($btnChangeAll);
        }

        $header = new ilFormSectionHeaderGUI();
        $header->setParentForm($a_form);
        $header->setTitle("Metadata");



        $md = $this->initMetaDataForm($a_form);
        $md->parse();



        parent::addExternalEditFormCustom($a_form);
    }

    protected function updateCustom(ilPropertyFormGUI $a_form)
    {
        /**
         * @var ilObjTalkTemplate $template
         */
        $template = $this->object->getParent();

        $md = $this->initMetaDataForm($a_form);
        $md->parse();
        $md->importEditFormPostValues();
        $md->writeEditForm($template->getId(), $this->object->getId());

        $location = $a_form->getInput('etal_location');
        $completed = boolval(
            intval($a_form->getInput('etal_completed'))
        );

        /**
         * @var EmployeeTalk $data
         */
        $data = $this->object->getData();
        $data->setCompleted($completed);
        $data->setLocation($location ?? '');
        $this->object->setData($data);

        /**
         * @var ilObjEmployeeTalkSeries $parent
         */
        $parent = $this->object->getParent();
        /**
         * @var ilObjEmployeeTalk[] $talks
         */
        $subTree = $parent->getSubItems()['_all'];


        // Update the title of every talk which belongs to the talk series
        foreach ($subTree as $treeNode) {
            if (boolval($treeNode['deleted']) === true) {
                continue;
            }
            $talk = new ilObjEmployeeTalk(intval($treeNode['ref_id']));
            if (
                $talk->getId() !== $this->object->getId()
            ) {
                $talk->setTitle($this->object->getTitle());
                $talk->update();
            }
        }

        parent::updateCustom($a_form);
    }

    public function viewObject()
    {
        $this->tabs_gui->activateTab('view_content');
        $this->editObject();
    }

    public function getTabs(): void
    {
        $this->tabs_gui->addTab('view_content', $this->lng->txt("content"), $this->ctrl->getLinkTarget($this, ControlFlowCommand::UPDATE));
        $this->tabs_gui->addTab("info_short", "Info", $this->ctrl->getLinkTargetByClass(strtolower(ilInfoScreenGUI::class), "showSummary"));
        //$this->tabs_gui->addTab('settings', $this->lng->txt("settings"), $this->ctrl->getLinkTarget($this, "edit"));

        parent::getTabs();
    }

    /**
     * @param ilTabsGUI $tabs_gui
     */
    public function getAdminTabs()
    {
        $this->getTabs();

        if ($this->checkPermissionBool("edit_permission")) {
            $this->tabs_gui->addTab(
                'perm_settings',
                $this->lng->txt('perm_settings'),
                $this->ctrl->getLinkTargetByClass(
                    [
                        get_class($this),
                        'ilpermissiongui'
                    ],
                    'perm'
                )
            );
        }
    }

    private function initMetaDataForm(ilPropertyFormGUI $form): ilAdvancedMDRecordGUI {
        /**
         * @var ilObjEmployeeTalkSeries $series
         */
        $series = $this->object->getParent();
        $md = new ilAdvancedMDRecordGUI(ilAdvancedMDRecordGUI::MODE_EDITOR, $series->getType(), $series->getId(), $this->object->getType(), $this->object->getId());
        $md->setPropertyForm($form);
        return $md;
    }

    public static function _goto(string $refId): void {
        /**
         * @var \ILIAS\DI\Container $container
         */
        $container = $GLOBALS['DIC'];
        $container->ctrl()->setParameterByClass(strtolower(self::class), 'ref_id', $refId);
        $container->ctrl()->redirectByClass([
            strtolower(ilDashboardGUI::class),
            strtolower(ilMyStaffGUI::class),
            strtolower(ilEmployeeTalkMyStaffListGUI::class),
            strtolower(self::class),
        ], ControlFlowCommand::INDEX);
    }
}