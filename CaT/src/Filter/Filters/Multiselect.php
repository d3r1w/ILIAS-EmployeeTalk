<?php

/* Copyright (c) 2016 Richard Klees, Extended GPL, see docs/LICENSE */

namespace CaT\Filter\Filters;

class Multiselect extends Filter {
	/**
	 * @var	int[]|string[]
	 */
	private $options;

	/**
	 * @var	int[]|string[]
	 */
	private $default_choice;
	
	public function __construct(\CaT\Filter\FilterFactory $factory, $label, $description, $options,
								$default_choice = array()) {
		assert('is_string($label)');
		assert('is_string($description)');

		$this->setFactory($factory);
		$this->setLabel($label);
		$this->setDescription($description);

		$this->options = $options;
		$this->default_choice = $default_choice;
	}

	/**
	 * @inheritdocs
	 */
	public function content_type() {
		
	}

	/**
	 * @inheritdocs
	 */
	public function input_type() {
		return $this->content_type();
	}

	/**
	 * @inheritdocs
	 */
	public function content(/*...$inputs*/) {

	}

	/**
	 * Get the options that could be selected.
	 *
	 * @return	int[]|string[]
	 */
	public function options() {
		return $this->options;
	}

	/**
	 * Set or get the default choice of options for the multiselect.
	 *
	 * @param	int[]|string[]|null		$options
	 * @return	Multiselect|string[]|int[]
	 */
	public function default_choice(array $options = null) {
		if ($options === null) {
			return $this->default_choice;
		}

		return new Multiselect($this->factory, $this->label(), $this->description(),
						$this->options, $this->default_options);
	}
}
