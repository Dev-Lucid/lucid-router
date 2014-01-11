<?php

class lucid_error
{
    public static function handle_error($errno,$errstr,$errfile,$errline,$errcontext)
    {
        global $lucid;
        $msg = lucid_error::build_error_string('ERROR',$errno,$errstr,$errfile,$errline);
        lucid_error::send_json_error($msg);
    }
    
    public static function handle_js_error($errstr,$source,$errline)
    {
        global $lucid;
        $msg = lucid_error::build_error_string('JS ERROR',0,$errstr,$source,$errline);
        
        # DO NOT uncomment this. This function is used to record a client-side error,
        # so it's redundant to send it back again to the client
        #lucid_error::send_json_error($msg); 
    }    
    
    public static function handle_exception($exception)
    {
        global $lucid;
        $msg = lucid_error::build_error_string(
            'EXCEPTION',
            $exception->getCode(),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );        
        lucid_error::send_json_error($msg);
    }

    public static function handle_shutdown()
    {
        global $lucid;
        $error = error_get_last();
        if(!is_null($error))
        {
            $msg = lucid_error::build_error_string('SHUTDOWN',500,$error['message'],$error['file'],$error['line']);
            lucid_error::send_json_error($msg);
        }
    }
    
    private static function build_error_string($type,$errno,$errstr,$errfile,$errline)
    {
        $msg = $type.": $errno: $errstr, line $errline\n: $errfile";
        lucid::log($msg,5,'error');
        return $msg;
    }
            
    private static function send_json_error($msg)
    {
        global $lucid;
        lucid::get_clean_buffer();
        if(__LUCID_ENV__ == 'http')
        {
			header('Content-type: application/json');
			http_response_code(200);
			exit(json_encode(array(
				'start_time'=>$lucid->response['start_time'],
				'end_time'=>microtime(true),
				'success'=>false,
				'message'=>($lucid->config['send-errors'])?$msg:null,
			)));
		}
		else
		{
			exit($msg);
		}
    }
}


# setup all the possible error handling we'll ever need
ini_set( "display_errors", "off" );
set_error_handler(array('lucid_error','handle_error'));
set_exception_handler(array('lucid_error','handle_exception'));
register_shutdown_function(array('lucid_error','handle_shutdown'));
?>