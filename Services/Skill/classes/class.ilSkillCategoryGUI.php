<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/Skill/classes/class.ilSkillTreeNodeGUI.php");

/**
 * Skill category GUI class
 *
 * @author Alex Killing <alex.killing@gmx.de>
 * @version $Id$
 *
 * @ilCtrl_isCalledBy ilSkillCategoryGUI: ilObjSkillManagementGUI
 * @ingroup ServicesSkill
 */
class ilSkillCategoryGUI extends ilSkillTreeNodeGUI
{

	/**
	 * Constructor
	 */
	function __construct($a_node_id = 0)
	{
		global $ilCtrl;
		
		$ilCtrl->saveParameter($this, "obj_id");
		
		parent::ilSkillTreeNodeGUI($a_node_id);
	}

	/**
	 * Get Node Type
	 */
	function getType()
	{
		return "scat";
	}

	/**
	 * Execute command
	 */
	function &executeCommand()
	{
		global $ilCtrl, $tpl, $ilTabs;
		
		$tpl->getStandardTemplate();
		
		$next_class = $ilCtrl->getNextClass($this);
		$cmd = $ilCtrl->getCmd();

		switch($next_class)
		{
			default:
				$ret = $this->$cmd();
				break;
		}
	}
	
	/**
	 * output tabs
	 */
	function setTabs()
	{
		global $ilTabs, $ilCtrl, $tpl, $lng;

		// properties
		$ilTabs->addTarget("properties",
			 $ilCtrl->getLinkTarget($this,'showProperties'),
			 "showProperties", get_class($this));
			 
		$tpl->setTitleIcon(ilUtil::getImagePath("icon_skmg_b.gif"));
		$tpl->setTitle(
			$lng->txt("skmg_basic_skill").": ".$this->node_object->getTitle());
	}

	/**
	 * Show Sequencing
	 */
	function showProperties()
	{
		global $tpl;
		
		$this->setTabs();
		$this->setLocator();

		$tpl->setContent("Properties");
	}

	/**
	 * Perform drag and drop action
	 */
	function proceedDragDrop()
	{
		global $ilCtrl;

//		$this->slm_object->executeDragDrop($_POST["il_hform_source_id"], $_POST["il_hform_target_id"],
//			$_POST["il_hform_fc"], $_POST["il_hform_as_subitem"]);
//		$ilCtrl->redirect($this, "showOrganization");
	}

	/**
	 * Edit
	 */
	function edit()
	{
		global $tpl;

		$this->initForm();
		$this->getValues();
		$tpl->setContent($this->form->getHTML());
	}

	/**
	 * Init form.
	 *
	 * @param        int        $a_mode        Edit Mode
	 */
	public function initForm($a_mode = "edit")
	{
		global $lng, $ilCtrl;

		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$this->form = new ilPropertyFormGUI();

		// title
		$ti = new ilTextInputGUI($lng->txt("title"), "title");
		$ti->setMaxLength(200);
		$ti->setSize(50);
		$ti->setRequired(true);
		$this->form->addItem($ti);
		
		// order nr
		$ni = new ilNumberInputGUI($lng->txt("skmg_order_nr"), "order_nr");
		$ni->setMaxLength(6);
		$ni->setSize(6);
		$ni->setRequired(true);
		$this->form->addItem($ni);

		// selectable
		$cb = new ilCheckboxInputGUI($lng->txt("skmg_selectable"), "self_eval");
		$cb->setInfo($lng->txt("skmg_selectable_info"));
		$this->form->addItem($cb);

		// save and cancel commands
		if ($a_mode == "create")
		{
			$this->form->addCommandButton("save", $lng->txt("save"));
			$this->form->addCommandButton("cancelSave", $lng->txt("cancel"));
			$this->form->setTitle($lng->txt("skmg_create_skill_category"));
		}
		else
		{
			$this->form->addCommandButton("update", $lng->txt("save"));
			$this->form->addCommandButton("cancelUpdate", $lng->txt("cancel"));
			$this->form->setTitle($lng->txt("skmg_edit_scat"));
		}
		
		$ilCtrl->setParameter($this, "obj_id", $_GET["obj_id"]);
		$this->form->setFormAction($ilCtrl->getFormAction($this));
	}

	/**
	 * Save item
	 */
	function saveItem()
	{
		$it = new ilSkillCategory();
		$it->setTitle($this->form->getInput("title"));
		$it->setOrderNr($this->form->getInput("order_nr"));
		$it->setSelfEvaluation($_POST["self_eval"]);
		$it->create();
		ilSkillTreeNode::putInTree($it, (int) $_GET["obj_id"], IL_LAST_NODE);
	}

	/**
	 * Get current values for from
	 */
	public function getValues()
	{
		$values = array();
		$values["title"] = $this->node_object->getTitle();
		$values["order_nr"] = $this->node_object->getOrderNr();
		$values["self_eval"] = $this->node_object->getSelfEvaluation();
		$this->form->setValuesByArray($values);
	}

	/**
	 * Update form
	 */
	function updateSkillCategory()
	{
		global $lng, $ilCtrl, $tpl;

		$this->initForm("edit");
		if ($this->form->checkInput())
		{
			// perform update
			$this->node_object->setSelfEvaluation($_POST["self_eval"]);
			$this->node_object->update();

			ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);
			$ilCtrl->redirect($this, "edit");
		}

		$this->form->setValuesByPost();
		$tpl->setContent($this->form->getHtml());
	}

	/**
	 * List items
	 *
	 * @param
	 * @return
	 */
	function listItems()
	{
		global $tpl;
		
		self::addCreationButtons();
		
		include_once("./Services/Skill//classes/class.ilSkillCatTableGUI.php");
		$table = new ilSkillCatTableGUI($this, "listItems", (int) $_GET["obj_id"],
			ilSkillCatTableGUI::MODE_SCAT);
		
		$tpl->setContent($table->getHTML());
	}
	
	/**
	 * Add creation buttons
	 *
	 * @param
	 * @return
	 */
	static function addCreationButtons()
	{
		global $ilCtrl, $lng, $ilToolbar;
		
		// skill
		$ilCtrl->setParameterByClass("ilbasicskillgui",
			"obj_id", (int) $_GET["obj_id"]);
		$ilToolbar->addButton($lng->txt("skmg_create_skll"),
			$ilCtrl->getLinkTargetByClass("ilbasicskillgui", "create"));

		// skill template reference
		$ilCtrl->setParameterByClass("ilskilltemplatereferencegui",
			"obj_id", (int) $_GET["obj_id"]);
		$ilToolbar->addButton($lng->txt("skmg_create_skill_template_reference"),
			$ilCtrl->getLinkTargetByClass("ilskilltemplatereferencegui", "create"));
		
		// skill category
		$ilCtrl->setParameterByClass("ilskillcategorygui",
			"obj_id", (int) $_GET["obj_id"]);
		$ilToolbar->addButton($lng->txt("skmg_create_skill_category"),
			$ilCtrl->getLinkTargetByClass("ilskillcategorygui", "create"));
	}

	/**
	 * Cancel
	 *
	 * @param
	 * @return
	 */
	function cancel()
	{
		global $ilCtrl;

		$ilCtrl->redirectByClass("ilobjskillmanagementgui", "editSkills");
	}
	
	/**
	 * Redirect to parent (identified by current obj_id)
	 *
	 * @param
	 * @return
	 */
	function redirectToParent()
	{
		global $ilCtrl;
		
		$t = ilSkillTreeNode::_lookupType((int) $_GET["obj_id"]);

		switch ($t)
		{
			case "skrt":
				$ilCtrl->setParameterByClass("ilskillrootgui", "obj_id", (int) $_GET["obj_id"]);
				$ilCtrl->redirectByClass("ilskillrootgui", "listSkills");
				break;
		}
		
		parent::redirectToParent();
	}

}

?>