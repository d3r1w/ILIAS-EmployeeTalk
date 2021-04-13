<?php
declare(strict_types=1);

use ILIAS\EmployeeTalk\UI\ControlFlowCommandHandler;
use ILIAS\EmployeeTalk\UI\ControlFlowCommand;
use ILIAS\MyStaff\ilMyStaffAccess;
use ILIAS\Modules\EmployeeTalk\Talk\Repository\EmployeeTalkRepository;
use ILIAS\Modules\EmployeeTalk\Talk\Repository\IliasDBEmployeeTalkRepository;
use ILIAS\Modules\EmployeeTalk\Talk\DAO\EmployeeTalk;

/**
 * Class ilEmployeeTalkMyStaffListGUI
 *
 * @ilCtrl_IsCalledBy ilEmployeeTalkMyStaffListGUI: ilMyStaffGUI
 * @ilCtrl_IsCalledBy ilEmployeeTalkMyStaffListGUI: ilFormPropertyDispatchGUI
 * @ilCtrl_Calls ilEmployeeTalkMyStaffListGUI: ilObjEmployeeTalkGUI, ilObjEmployeeTalkSeriesGUI
 */
final class ilEmployeeTalkMyStaffListGUI implements ControlFlowCommandHandler
{
    /**
     * @var \ILIAS\DI\UIServices $ui
     */
    private $ui;
    /**
     * @var ilLanguage
     */
    private $language;

    /**
     * @var ilTabsGUI
     */
    private $tabs;
    /**
     * @var ilToolbarGUI
     */
    private $toolbar;
    /**
     * @var ilCtrl $controlFlow
     */
    private $controlFlow;
    /**
     * @var ilObjUser $currentUser
     */
    private $currentUser;
    /**
     * @var EmployeeTalkRepository $repository
     */
    private $repository;

    public function __construct()
    {
        /**
         * @var \ILIAS\DI\Container $container
         */
        $container = $GLOBALS['DIC'];

        $container->language()->loadLanguageModule('etal');
        $container->language()->loadLanguageModule('orgu');
        $this->language = $container->language();

        $this->tabs = $container->tabs();
        $this->ui = $container->ui();
        $this->controlFlow = $container->ctrl();
        $this->ui->mainTemplate()->setTitle($container->language()->txt('mm_org_etal'));
        $this->toolbar = $container->toolbar();
        $this->currentUser = $container->user();
        $this->repository = new IliasDBEmployeeTalkRepository($container->database());
    }

    public function executeCommand() : bool
    {
        $nextClass = $this->controlFlow->getNextClass();
        $command = $this->controlFlow->getCmd(ControlFlowCommand::DEFAULT);
        switch ($nextClass) {
            case strtolower(ilObjEmployeeTalkSeriesGUI::class):
                $gui = new ilObjEmployeeTalkSeriesGUI();
                return $this->controlFlow->forwardCommand($gui);
            case strtolower(ilObjEmployeeTalkGUI::class):
                $gui = new ilObjEmployeeTalkGUI();
                return $this->controlFlow->forwardCommand($gui);
            case strtolower(ilFormPropertyDispatchGUI::class):
                $this->controlFlow->setReturn($this, ControlFlowCommand::INDEX);
                $table = new ilEmployeeTalkTableGUI($this, ControlFlowCommand::INDEX);
                $table->executeCommand();
                break;
            default:
                switch ($command) {
                    case ControlFlowCommand::APPLY_FILTER:
                        $this->applyFilter();
                        return true;
                    case ControlFlowCommand::RESET_FILTER:
                        $this->resetFilter();
                        return true;
                    default:
                        return $this->view();
                }

        }
    }

    private function applyFilter(): void
    {
        $table = new ilEmployeeTalkTableGUI($this, ControlFlowCommand::APPLY_FILTER);
        $table->writeFilterToSession();
        $table->resetOffset();
        $this->view();
    }

    private function resetFilter(): void
    {
        $table = new ilEmployeeTalkTableGUI($this, ControlFlowCommand::RESET_FILTER);
        $table->resetOffset();
        $table->resetFilter();
        $this->view();
    }

    private function view(): bool {
        $this->loadActionBar();
        $this->loadTabs();
        $this->ui->mainTemplate()->setContent($this->loadTable()->getHTML());
        return true;
    }

    private function loadTabs(): void {
        $this->tabs->addTab("view_content", "Content", "#");
        $this->tabs->activateTab("view_content");
        //$this->tabs->addTab("placeholder", "", "#");
        $this->tabs->setForcePresentationOfSingleTab(true);
    }

    private function loadActionBar(): void {
        $gl = new ilGroupedListGUI();
        $gl->setAsDropDown(true, false);

        $templates = new CallbackFilterIterator(
            new ArrayIterator(ilObject::_getObjectsByType("talt")),
            function($item) {
                return $item['offline'] === "0" || $item['offline'] === null;
            }
        );

        foreach ($templates as $item) {
            $type = $item["type"];

            $path = ilObject::_getIcon('', 'tiny', $type);
            $icon = ($path != "")
                ? ilUtil::img($path, "") . " "
                : "";

            $base_url = $this->controlFlow->getLinkTargetByClass(strtolower(ilObjEmployeeTalkSeriesGUI::class), ControlFlowCommand::CREATE);
            $url = $this->controlFlow->appendRequestTokenParameterString($base_url . "&new_type=" . ilObjEmployeeTalkSeries::TYPE);
            $refId = ilObject::_getAllReferences(intval($item['obj_id']));

            // Templates only have one ref id
            $url .= "&template=" . array_pop($refId);
            $url .= "&ref_id=" . ilObjTalkTemplateAdministration::getRootRefId();

            $ttip = ilHelp::getObjCreationTooltipText("tals");

            $gl->addEntry(
                $icon . $item["title"],
                $url,
                "_top",
                "",
                "",
                $type,
                $ttip,
                "bottom center",
                "top center",
                false
            );
        }

        $adv = new ilAdvancedSelectionListGUI();
        $adv->setListTitle($this->language->txt("etal_add_new_item"));
        //$gl->getHTML();
        $adv->setGroupedList($gl);
        $adv->setStyle(ilAdvancedSelectionListGUI::STYLE_EMPH);
        //$this->toolbar->addDropDown($this->language->txt("cntr_add_new_item"), $adv->getHTML());
        $this->ui->mainTemplate()->setVariable("SELECT_OBJTYPE_REPOS", $adv->getHTML());
    }

    private function loadTable(): ilEmployeeTalkTableGUI {
        $table = new ilEmployeeTalkTableGUI($this, ControlFlowCommand::DEFAULT);

        $users = ilMyStaffAccess::getInstance()->getUsersForUser($this->currentUser->getId());
        /**
         * @var EmployeeTalk[] $talks
         */
        $talks = $this->repository->findByEmployees($users);
        $table->setTalkData($talks);

        return $table;
    }
}