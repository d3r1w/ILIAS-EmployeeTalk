<?php
/* Copyright (c) 1998-2016 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilBPMN2ElementLoader
 *
 * @author Maximilian Becker <mbecker@databay.de>
 * @version $Id$
 *
 * @ingroup Services/WorkflowEngine
 */
class ilBPMN2ElementLoader
{
    /** @var array $bpmn2_array */
    protected array $bpmn2_array;

    /**
     * ilBPMN2ElementLoader constructor.
     *
     * @param $bpmn2_array
     */
    public function __construct($bpmn2_array)
    {
        $this->bpmn2_array = $bpmn2_array;
    }

    public function load(string $element_name)
    {
        preg_match('/[A-Z]/', $element_name, $matches, PREG_OFFSET_CAPTURE);
        $type = strtolower(substr($element_name, (int) ($matches[0][1] ?? 0)));
        if ($type === 'basedgateway') {
            // Fixing a violation of the standards naming convention by the standard here.
            $type = 'gateway';
        }

        if ($type === 'objectreference') {
            // Fixing a violation of the standards naming convention by the standard here.
            $type = 'object';
        }

        $classname = 'il' . ucfirst($element_name) . 'Element';
        $instance = new $classname;
        $instance->setBPMN2Array($this->bpmn2_array);

        return $instance;
    }
}
