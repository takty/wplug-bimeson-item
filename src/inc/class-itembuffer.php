<?php
/**
 * Item Buffer
 *
 * @package Wplug Bimeson Item
 * @author Takuto Yanagida
 * @version 2021-08-31
 */

namespace wplug\bimeson_item;

/**
 * Item buffer
 */
class ItemBuffer {

	/**
	 * Stored items.
	 *
	 * @var 1.0
	 */
	private $items = array();

	/**
	 * Language.
	 *
	 * @var 1.0
	 */
	private $lang;

	/**
	 * Constructor.
	 *
	 * @param string $lang Language.
	 */
	public function __construct( string $lang ) {
		$this->lang = $lang;
	}

	/**
	 * Adds an item.
	 *
	 * @param array $item An item.
	 */
	public function add( array $item ) {
		$this->items[] = $item;
	}

	/**
	 * Displays stored items.
	 */
	public function echo() {
		if ( ! empty( $this->items ) ) {
			_echo_list_element( $this->items, $this->lang );
			$this->items = array();
		}
	}

	/**
	 * Check whether this has any items.
	 *
	 * @return bool True if this has any items.
	 */
	public function has_item(): bool {
		return ! empty( $this->items );
	}

}
