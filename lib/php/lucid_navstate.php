<?php

class lucid_navstate
{
	private function __construct($area)
	{
		$this->area = $area;
	}
	
	public static function area($area)
	{
		$obj = new lucid_navstate($area);
		return $obj;
	}
	
	public function is($comparison)
	{
		global $lucid;
		$current = (isset($_REQUEST['navState'][$this->area]))?$_REQUEST['navState'][$this->area]:'';
		return ($current == $comparison);
	}

	public function is_not($comparison)
	{
		global $lucid;
		$current = (isset($_REQUEST['navState'][$this->area]))?$_REQUEST['navState'][$this->area]:'';
		return ($current != $comparison);
	}
	
	public static function set_area($selector, $area, $new_state='')
	{
		lucid::replace($selector);
		lucid::javascript('lucid.handlers.setNavState(\''.addslashes($area).'\',\''.addslashes($new_state).'\');');
	}
}

?>