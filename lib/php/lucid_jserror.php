<?php

class lucid_controller__lucid_jserror extends lucid_controller
{
	function record_error()
	{
		lucid_error::handle_js_error($_REQUEST['message'],$_REQUEST['source'],$_REQUEST['line']);
	}
}

?>