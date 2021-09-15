<?php declare(strict_types=1);
/* Copyright (c) 1998-2012 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * @author Nadia Matuschek <nmatuschek@databay.de>
 * Class ilForumPostsTree
 */
class ilForumPostsTree
{
    private int $pos_fk = 0;
    private int $parent_pos = 0;
    private int $lft = 0;
    private int $rgt = 0;
    private int $depth = 0;

    private int $source_thread_id = 0;
    private int $target_thread_id = 0;

    private $db;

    public function setDepth(int $depth) : void
    {
        $this->depth = $depth;
    }

    public function getDepth() : int
    {
        return $this->depth;
    }

    public function setLft(int $lft) : void
    {
        $this->lft = $lft;
    }

    public function getLft() : int
    {
        return $this->lft;
    }

    public function setParentPos(int $parent_pos) : void
    {
        $this->parent_pos = $parent_pos;
    }

    public function getParentPos() : int
    {
        return $this->parent_pos;
    }

    public function setPosFk(int $pos_fk) : void
    {
        $this->pos_fk = $pos_fk;
    }

    public function getPosFk() : int
    {
        return $this->pos_fk;
    }

    public function setRgt(int $rgt) : void
    {
        $this->rgt = $rgt;
    }

    public function getRgt() : int
    {
        return $this->rgt;
    }

    public function setSourceThreadId(int $source_thread_id) : void
    {
        $this->source_thread_id = $source_thread_id;
    }

    public function getSourceThreadId() : int
    {
        return $this->source_thread_id;
    }

    public function setTargetThreadId(int $target_thread_id) : void
    {
        $this->target_thread_id = $target_thread_id;
    }

    public function getTargetThreadId() : int
    {
        return $this->target_thread_id;
    }

    public function __construct()
    {
        global $DIC;
        $this->db = $DIC->database();
    }

    public function merge() : void
    {
        $this->db->update(
            'frm_posts_tree',
            [
                'lft' => array('integer', $this->getLft()),
                'rgt' => array('integer', $this->getRgt()),
                'depth' => array('integer', $this->getDepth()),
                'thr_fk' => array('integer', $this->getTargetThreadId()),
                'parent_pos' => array('integer', $this->getParentPos()),
            ],
            [
                'pos_fk' => array('integer', $this->getPosFk()),
                'thr_fk' => array('integer', $this->getSourceThreadId())
            ]
        );
    }

    public static function updateTargetRootRgt(int $root_node_id, int $rgt) : void
    {
        global $DIC;
        $ilDB = $DIC->database();

        $ilDB->update(
            'frm_posts_tree',
            array(
                'rgt' => array('integer', $rgt)
            ),
            array(
                'parent_pos' => array('integer', 0),
                'pos_fk' => array('integer', $root_node_id)
            )
        );
    }
}
