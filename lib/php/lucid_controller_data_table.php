<?php

class lucid_controller_data_table extends lucid_controller
{
    function __construct()
    {
        lucid::log('lucid_controller_data_table->__construct: this should never be called, you should override this in your class.');
    }

    function refresh()
    {
        global $lucid;
        $table = $this->build();
        $lucid->response['special'][$table->identifier] = $table->get_data();
        $lucid->javascript($table->get_refresh_js());
    }
}

?>