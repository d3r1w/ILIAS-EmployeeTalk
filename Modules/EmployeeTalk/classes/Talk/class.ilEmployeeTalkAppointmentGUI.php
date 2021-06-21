<?php
declare(strict_types=1);

use ILIAS\EmployeeTalk\UI\ControlFlowCommandHandler;
use ILIAS\EmployeeTalk\UI\ControlFlowCommand;
use ILIAS\Modules\EmployeeTalk\Talk\DAO\EmployeeTalk;
use ILIAS\Modules\EmployeeTalk\Talk\Repository\EmployeeTalkRepository;
use ILIAS\Modules\EmployeeTalk\Talk\EmployeeTalkPeriod;
use ILIAS\EmployeeTalk\Service\EmployeeTalkEmailNotificationService;
use ILIAS\EmployeeTalk\Service\VCalendarFactory;
use ILIAS\EmployeeTalk\Service\EmployeeTalkEmailNotification;

/**
 * Class ilEmployeeTalkAppointmentGUI
 *
 * @ilCtrl_IsCalledBy ilEmployeeTalkAppointmentGUI: ilObjEmployeeTalkGUI
 */
final class ilEmployeeTalkAppointmentGUI implements ControlFlowCommandHandler
{
    const EDIT_MODE_APPOINTMENT = 'appointment';
    const EDIT_MODE_SERIES = 'series';
    const EDIT_MODE = 'edit-mode';

    /**
     * @var ilGlobalTemplateInterface $template
     */
    private $template;
    /**
     * @var ilLanguage $language
     */
    private $language;
    /**
     * @var ilCtrl $controlFlow
     */
    private $controlFlow;
    /**
     * @var ilTabsGUI
     */
    private $tabs;
    /**
     * @var ilObjEmployeeTalk $talk
     */
    private $talk;

    /**
     * ilEmployeeTalkAppointmentGUI constructor.
     * @param ilGlobalTemplateInterface $template
     * @param ilLanguage                $language
     * @param ilCtrl                    $controlFlow
     * @param ilTabsGUI                 $tabs
     * @param ilObjEmployeeTalk         $talk
     */
    public function __construct(
        ilGlobalTemplateInterface $template,
        ilLanguage $language,
        ilCtrl $controlFlow,
        ilTabsGUI $tabs,
        ilObjEmployeeTalk $talk
    ) {
        $this->template = $template;
        $this->language = $language;
        $this->controlFlow = $controlFlow;
        $this->tabs = $tabs;
        $this->talk = $talk;

        $this->language->loadLanguageModule('cal');
    }

    function executeCommand(): bool {
        $cmd = $this->controlFlow->getCmd(ControlFlowCommand::DEFAULT);
        $params = $this->controlFlow->getParameterArrayByClass(strtolower(self::class));

        $backClass = strtolower(ilObjEmployeeTalkGUI::class);
        $this->controlFlow->setParameterByClass($backClass, 'ref_id', $params['ref_id']);
        $this->tabs->setBackTarget($this->language->txt('back'), $this->controlFlow->getLinkTargetByClass(strtolower(ilObjEmployeeTalkGUI::class), ControlFlowCommand::DEFAULT));

        switch ($this->editMode()) {
            case self::EDIT_MODE_SERIES:
                return $this->executeSeriesCommand($cmd);
            case self::EDIT_MODE_APPOINTMENT:
                return $this->executeAppointmentCommand($cmd);
            default:
                $this->controlFlow->redirectByClass(strtolower(ilObjEmployeeTalkGUI::class), ControlFlowCommand::DEFAULT);
                return true;
        }
    }

    private function executeSeriesCommand(string $cmd): bool {
        $this->template->setTitle($this->language->txt('etal_date_series_edit'));

        switch ($cmd) {
            case ControlFlowCommand::UPDATE_INDEX:
                $this->editSeries();
                return true;
            case ControlFlowCommand::UPDATE:
                $this->updateSeries();
                return true;
        }

        return false;
    }

    private function executeAppointmentCommand(string $cmd): bool {
        $this->template->setTitle($this->language->txt('etal_date_appointment_edit'));

        switch ($cmd) {
            case ControlFlowCommand::UPDATE_INDEX:
                $this->editAppointment();
                return true;
            case ControlFlowCommand::UPDATE:
                $this->updateAppointment();
                return true;
        }

        return false;
    }

    private function editSeries(): void {
        $form = $this->initSeriesEditForm($this->talk->getData());

        $this->template->setContent($form->getHTML());
    }

    private function updateSeries(): void {
        $form = $this->initSeriesEditForm();
        if ($form->checkInput()) {
            $reoccurrence = $this->loadRecurrenceSettings($form);
            $parent = $this->talk->getParent();
            $this->deletePendingTalks($parent);
            $this->createRecurringTalks($form, $reoccurrence, $parent);

            ilUtil::sendSuccess($this->language->txt('saved_successfully'), true);
        }

        $this->controlFlow->redirectToURL(
            $this->controlFlow->getLinkTargetByClass(strtolower(ilEmployeeTalkMyStaffListGUI::class),
                ControlFlowCommand::UPDATE_INDEX) . $this->getEditModeParameter(ilEmployeeTalkAppointmentGUI::EDIT_MODE_SERIES)
        );

    }

    private function initTalkEditForm(?EmployeeTalk $employeeTalk = null): ilPropertyFormGUI {
        // Init dom events or ui will break on page load
        ilYuiUtil::initDomEvent();

        $form = new ilPropertyFormGUI();
        $editMode = $this->getEditModeParameter(ilEmployeeTalkAppointmentGUI::EDIT_MODE_APPOINTMENT);
        $form->setFormAction($this->controlFlow->getFormActionByClass(
                strtolower(self::class)
            ) . $editMode);

        $header = new ilFormSectionHeaderGUI();
        $header->setTitle($this->language->txt('appointment'));
        $form->addItem($header);

        $dur = new ilDateDurationInputGUI($this->language->txt('cal_fullday'), 'event');
        $dur->setRequired(true);
        $dur->setShowTime(true);

        if ($employeeTalk !== null) {
            $dur->enableToggleFullTime(
                $this->language->txt('cal_fullday_title'), $employeeTalk->isAllDay()
            );

            $dur->setStart($employeeTalk->getStartDate());
            $dur->setEnd($employeeTalk->getEndDate());
        }

        $form->addItem($dur);
        $form->addCommandButton(ControlFlowCommand::UPDATE, $this->language->txt('save'), 'etal_date_save');

        return $form;
    }

    private function initSeriesEditForm(?EmployeeTalk $employeeTalk = null): ilPropertyFormGUI {
        // Init dom events or ui will break on page load
        ilYuiUtil::initDomEvent();

        $form = new ilPropertyFormGUI();
        $editMode = $this->getEditModeParameter(ilEmployeeTalkAppointmentGUI::EDIT_MODE_SERIES);
        $form->setFormAction($this->controlFlow->getFormActionByClass(
                strtolower(self::class)
            ) . $editMode);

        $this->template->addJavaScript('./Services/Form/js/date_duration.js');

        $header = new ilFormSectionHeaderGUI();
        $header->setTitle($this->language->txt('appointments'));
        $form->addItem($header);

        $dur = new ilDateDurationInputGUI($this->language->txt('cal_fullday'), 'event');
        $dur->setRequired(true);
        $dur->setShowTime(true);

        if ($employeeTalk !== null) {
            $dur->enableToggleFullTime(
                $this->language->txt('cal_fullday_title'), $employeeTalk->isAllDay()
            );

            $dur->setStart($employeeTalk->getStartDate());
            $dur->setEnd($employeeTalk->getEndDate());
        }

        $form->addItem($dur);

        // Recurrence
        $cal = new ilRecurrenceInputGUI($this->language->txt('cal_recurrences'), "frequence");
        $event = new ilCalendarRecurrence();

        $cal->allowUnlimitedRecurrences(false);
        $cal->setRecurrence($event);

        $form->addItem($cal);
        $form->addCommandButton(ControlFlowCommand::UPDATE, $this->language->txt('save'), 'etal_series_save');

        return $form;
    }

    private function editAppointment(): void {

        $form = $this->initTalkEditForm($this->talk->getData());

        $this->template->setContent($form->getHTML());
    }

    private function updateAppointment(): void {
        $form = $this->initTalkEditForm();
        $form->setValuesByPost();
        if ($form->checkInput()) {
            /**
             * @var ilDateDurationInputGUI $dateTimeInput
             */
            $dateTimeInput = $form->getItemByPostVar('event');
            ['tgl' => $tgl] = $form->getInput('event');
            ['start' => $start, 'end' => $end] = $dateTimeInput->getValue();

            $startDate = new ilDateTime($start, IL_CAL_UNIX, ilTimeZone::UTC);
            $endDate = new ilDateTime($end, IL_CAL_UNIX, ilTimeZone::UTC);

            $data = $this->talk->getData();
            $data->setAllDay(boolval(intval($tgl)));
            $data->setStartDate($startDate);
            $data->setEndDate($endDate);
            $data->setStandalone(true);

            $this->talk->setData($data);
            $this->talk->update();

            $this->sendNotification([$this->talk]);

            ilUtil::sendSuccess($this->language->txt('saved_successfully'), true);
        }


        $this->controlFlow->redirectToURL(
            $this->controlFlow->getLinkTargetByClass(strtolower(self::class),
                ControlFlowCommand::UPDATE_INDEX) . $this->getEditModeParameter(ilEmployeeTalkAppointmentGUI::EDIT_MODE_APPOINTMENT)
        );
    }

    /**
     * @param ilObjEmployeeTalk[] $talks
     */
    private function sendNotification(array $talks): void {
        if (count($talks) === 0) {
            return;
        }

        $firstTalk = $talks[0];
        $talkTitle = $firstTalk->getTitle();
        $superior = new ilObjUser($firstTalk->getOwner());
        $employee = new ilObjUser($firstTalk->getData()->getEmployee());
        $superiorName = $superior->getFullname();

        $dates = [];
        foreach ($talks as $talk) {
            $data = $talk->getData();
            $startDate = $data->getStartDate()->get(IL_CAL_DATETIME);

            $dates[] = $startDate;
        }

        $message = new EmployeeTalkEmailNotification(
            sprintf($this->language->txt('notification_talks_updated'), $superiorName),
            $this->language->txt('notification_talks_date_details'),
            sprintf($this->language->txt('notification_talks_talk_title'), $talkTitle),
            $this->language->txt('notification_talks_date_list_header'),
            $dates
        );

        $vCalSender = new EmployeeTalkEmailNotificationService(
            $message,
            $talkTitle,
            $employee,
            $superior,
            VCalendarFactory::getInstanceFromTalks($firstTalk->getParent())
        );

        $vCalSender->send();
    }

    private function editMode(): string {
        return filter_input(INPUT_GET, self::EDIT_MODE, FILTER_CALLBACK, ['options' => function(string $value) {
                if ($value === self::EDIT_MODE_SERIES || $value === self::EDIT_MODE_APPOINTMENT) {
                    return $value;
                }

                return 'invalid';
            }]) ?? 'invalid';
    }

    private function getEditModeParameter(string $mode): string {
        return '&' . ilEmployeeTalkAppointmentGUI::EDIT_MODE . '=' . $mode;
    }

    /**
     * load recurrence settings
     *
     * @access protected
     * @return
     */
    private function loadRecurrenceSettings(ilPropertyFormGUI $form): ilCalendarRecurrence
    {
        $rec = new ilCalendarRecurrence();

        switch ($_POST['frequence']) {
            case IL_CAL_FREQ_DAILY:
                $rec->setFrequenceType($_POST['frequence']);
                $rec->setInterval((int) $_POST['count_DAILY']);
                break;

            case IL_CAL_FREQ_WEEKLY:
                $rec->setFrequenceType($_POST['frequence']);
                $rec->setInterval((int) $_POST['count_WEEKLY']);
                if (is_array($_POST['byday_WEEKLY'])) {
                    $rec->setBYDAY(ilUtil::stripSlashes(implode(',', $_POST['byday_WEEKLY'])));
                }
                break;

            case IL_CAL_FREQ_MONTHLY:
                $rec->setFrequenceType($_POST['frequence']);
                $rec->setInterval((int) $_POST['count_MONTHLY']);
                switch ((int) $_POST['subtype_MONTHLY']) {
                    case 0:
                        // nothing to do;
                        break;

                    case 1:
                        switch ((int) $_POST['monthly_byday_day']) {
                            case 8:
                                // Weekday
                                $rec->setBYSETPOS((int) $_POST['monthly_byday_num']);
                                $rec->setBYDAY('MO,TU,WE,TH,FR');
                                break;

                            case 9:
                                // Day of month
                                $rec->setBYMONTHDAY((int) $_POST['monthly_byday_num']);
                                break;

                            default:
                                $rec->setBYDAY((int) $_POST['monthly_byday_num'] . $_POST['monthly_byday_day']);
                                break;
                        }
                        break;

                    case 2:
                        $rec->setBYMONTHDAY((int) $_POST['monthly_bymonthday']);
                        break;
                }
                break;

            case IL_CAL_FREQ_YEARLY:
                $rec->setFrequenceType($_POST['frequence']);
                $rec->setInterval((int) $_POST['count_YEARLY']);
                switch ((int) $_POST['subtype_YEARLY']) {
                    case 0:
                        // nothing to do;
                        break;

                    case 1:
                        $rec->setBYMONTH((int) $_POST['yearly_bymonth_byday']);
                        $rec->setBYDAY((int) $_POST['yearly_byday_num'] . $_POST['yearly_byday']);
                        break;

                    case 2:
                        $rec->setBYMONTH((int) $_POST['yearly_bymonth_by_monthday']);
                        $rec->setBYMONTHDAY((int) $_POST['yearly_bymonthday']);
                        break;
                }
                break;
        }

        // UNTIL
        switch ((int) $_POST['until_type']) {
            case 1:
                $rec->setFrequenceUntilDate(null);
                // nothing to do
                break;

            case 2:
                $rec->setFrequenceUntilDate(null);
                $rec->setFrequenceUntilCount((int) $_POST['count']);
                break;

            case 3:
                $frequence = $form->getItemByPostVar('frequence');
                $end = $frequence->getRecurrence()->getFrequenceUntilDate();
                $rec->setFrequenceUntilCount(0);
                $rec->setFrequenceUntilDate($end);
                break;
        }

        return $rec;
    }

    /**
     * create recurring talks
     * @param ilPropertyFormGUI       $form
     * @param ilCalendarRecurrence    $recurrence
     * @param ilObjEmployeeTalkSeries $series
     *
     * @return bool true if successful otherwise false
     * @throws ilDateTimeException
     */
    private function createRecurringTalks(ilPropertyFormGUI $form, ilCalendarRecurrence $recurrence, ilObjEmployeeTalkSeries $series) : bool
    {
        $data = $this->loadEtalkData($form);

        $firstAppointment = new EmployeeTalkPeriod(
            $data->getStartDate(),
            $data->getEndDate(),
            $data->isAllDay()
        );
        $calc = new ilCalendarRecurrenceCalculator($firstAppointment, $recurrence);

        $periodStart = clone $data->getStartDate();

        $periodEnd = clone $data->getStartDate();
        $periodEnd->increment(IL_CAL_YEAR, 5);
        $dateIterator = $calc->calculateDateList($periodStart, $periodEnd);

        $periodDiff = $data->getEndDate()->get(IL_CAL_UNIX) -
            $data->getStartDate()->get(IL_CAL_UNIX);

        $talkSession = new ilObjEmployeeTalk();
        $talkSession->setTitle($this->talk->getTitle());
        $talkSession->setDescription($this->talk->getLongDescription());
        $talkSession->setType(ilObjEmployeeTalk::TYPE);
        $talkSession->create();

        $talkSession->createReference();
        $talkSession->putInTree($series->getRefId());

        $data->setObjectId($talkSession->getId());
        $talkSession->setData($data);
        $talkSession->update();

        $talks = [];
        $talks[] = $talkSession;

        if (!$recurrence->getFrequenceType()) {
            $this->sendNotification($talks);
            return true;
        }

        // Remove start date
        $dateIterator->removeByDAY($periodStart);
        $dateIterator->rewind();

        /**
         * @var ilDateTime $date
         */
        foreach ($dateIterator as $date) {

            $cloneObject = $talkSession->cloneObject($series->getRefId());
            $cloneData = $cloneObject->getData();

            $cloneData->setStartDate($date);
            $endDate = $date->get(IL_CAL_UNIX) + $periodDiff;
            $cloneData->setEndDate(new ilDateTime($endDate, IL_CAL_UNIX));
            $cloneObject->setData($cloneData);
            $cloneObject->update();

            $talks[] = $cloneObject;
        }

        $this->sendNotification($talks);

        return true;
    }

    private function deletePendingTalks(ilObjEmployeeTalkSeries $series): void {
        $subItems = $series->getSubItems()['_all'];

        foreach ($subItems as $subItem) {
            if ($subItem['type'] === 'etal') {
                $refId = intval($subItem['ref_id']);
                $talk = new ilObjEmployeeTalk($refId, true);
                $talkData = $talk->getData();
                if ($talkData->isStandalone() || $talkData->isCompleted()) {
                    continue;
                }

                $talk->delete();
            }
        }
    }

    private function loadEtalkData(ilPropertyFormGUI $form): EmployeeTalk {

        $data = $this->talk->getData();
        ['tgl' => $tgl] = $form->getInput('event');

        /**
         * @var ilDateDurationInputGUI $dateTimeInput
         */
        $dateTimeInput = $form->getItemByPostVar('event');
        ['start' => $start, 'end' => $end] = $dateTimeInput->getValue();
        $startDate = new ilDateTime($start, IL_CAL_UNIX, ilTimeZone::UTC);
        $endDate = new ilDateTime($end, IL_CAL_UNIX, ilTimeZone::UTC);

        return new EmployeeTalk(
            -1,
            $startDate,
            $endDate,
            boolval(intval($tgl)),
            '',
            $data->getLocation(),
            $data->getEmployee(),
            false,
            false
        );
    }
}