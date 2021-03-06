<?php 

define('__LUCID_ENV__',(php_sapi_name() == 'cli' or PHP_SAPI == 'cli')?'cli':'http');

class lucid
{
    # never invoked directly, always use ::init()
	private function __construct()
	{
		$this->config = array(
            'send-errors'=>true,
            'default-content-mode'=>'replace',
            'default-content-position'=>'#center',
        	'log-handle'=>null,
		);
		$this->validators = array();
		$this->paths      = array();
		$this->response   = array(
			'start_time'=>microtime(true),
            'success'=>true,
            'message'=>null,
			'append'=>array(),
			'prepend'=>array(),
			'replace'=>array(),
			'javascript'=>null,
			'title'=>null,
			'description'=>null,
			'keywords'=>null,
            'special'=>array(),
		);
        $this->commands = array(
            'pre-request'=>array(),
            'request'=>array(),
            'post-request'=>array(),
        );
	}
	
    # this starts up everything
	public static function init($config=array())
	{
		global $lucid;
		
        # create the main object
		if(!is_object($lucid) || get_class($lucid) != 'lucid')
		{
			$lucid = new lucid();
            require(__DIR__.'/lucid_controller.php');
			require(__DIR__.'/lucid_controller_data_table.php');
			require(__DIR__.'/lucid_navstate.php');
		}

		# apply the config
		foreach($config as $setting=>$value)
		{
			$lucid->config[$setting] = $value;
		}
        
        # startup misc things by default
		if(!defined('__LUCID_NO_START_SESSION__' && __LUCID_ENV__ != 'cli'))	
		{
			session_start();
		}
		if(!defined('__LUCID_NO_START_BUFFER__' && __LUCID_ENV__ != 'cli'))	
		{
			ob_start();
		}
        if(!defined('__LUCID_EXIT_ON_ERROR__'))
        {
            include(__DIR__.'/lucid_error.php');
        }
		
        # put the url request into the list of commands
        if(isset($_REQUEST['todo']) && $_REQUEST['todo'] != '')
        {
            $lucid->commands['request'][] = $_REQUEST['todo'];
        }
        
        //lucid_db_adaptor::init();
       
       	lucid::log(''); 
		lucid::log('---------------------------------------------------------------');
		lucid::log('Parameters: '.print_r($_REQUEST,true));
	}
    
    # will process everything in the $lucid->commands arrays
    public static function process()
    {
        global $lucid;
        
        $lists = array('pre-request','request','post-request');
        lucid::log('processing commands: '.implode(',',array_unique(array_map(function($commands){return implode(',',$commands);},$lucid->commands))));

        foreach($lists as $list)
        {
            foreach($lucid->commands[$list] as $command)
            {
                $com_parts  = explode('/',$command);
                $controller = $com_parts[0];
                $view = (isset($com_parts[1]))?$com_parts[1]:'default_view';
                $obj  = lucid_controller::instantiate($controller);
                $obj->$view();
                
                $content = lucid::get_clean_buffer();
                if($content != '')
                {
					lucid::place_content(
						$lucid->config['default-content-position'],
						$lucid->config['default-content-mode'],
						$content
					);
				}
            }
        } 
    }

    public function __call($controller,$params)
    {
    	return lucid_controller::instantiate($controller);
    }
    
	private static function place_content($location,$mode,$content)
	{
		global $lucid;
		if(!isset($lucid->response[$mode][$location]))
		{
			$lucid->response[$mode][$location] = '';
		}
		$lucid->response[$mode][$location] .= $content;
	}
	
	public static function replace($location,$content=null)
	{
		$content = (is_null($content))?lucid::get_clean_buffer():$content;
		lucid::place_content($location,'replace',$content);
	}
	
	public static function append($location,$content=null)
	{
		$content = (is_null($content))?lucid::get_clean_buffer():$content;
		lucid::place_content($location,'append',$content);
	}

	public static function prepend($location,$content=null)
	{
		$content = (is_null($content))?lucid::get_clean_buffer():$content;
		lucid::place_content($location,'prepend',$content);
	}
	
	public static function start_content($position=null,$mode=null)
	{
		global $lucid;
		if(is_null($position))
		{
			$position = $lucid->config['default-content-position'];
		}
		if(is_null($mode))
		{
			$mode = $lucid->config['default-content-mode'];
		}
		$lucid->config['current-content-position'] = $position;
		$lucid->config['current-content-mode'] = $mode;
	}
	
	public static function end_content()
	{
		global $lucid;
		$position = $lucid->config['current-content-position'];
		$mode = $lucid->config['current-content-mode'];
		
		if(is_null($position))
		{
			$position = $lucid->config['default-content-position'];
		}
		if(is_null($mode))
		{
			$mode = $lucid->config['default-content-mode'];
		}
		
		if($mode != 'append' && $mode != 'replace' && $mode != 'prepend')
		{
			throw new Exception('Invalid content mode: '.$mode.' in lucid::end_content',51);
		}
		
		lucid::$mode($position,lucid::get_clean_buffer());
		
		$lucid->config['current-content-position'] = null;
		$lucid->config['current-content-mode'] = null;
	}
	
	public static function title($content)
	{
		global $lucid;
		$lucid->response['title'] = $content;
	}
	
	public static function description($content)
	{
		global $lucid;
		$lucid->response['description'] = $content;
	}
	
	public static function keywords($content)
	{
		global $lucid;
		$lucid->response['keywords'] = $content;
	}
	
	public static function javascript($content)
	{
		global $lucid;
		$lucid->response['javascript'] .= $content;
	}

	public static function get_clean_buffer()
	{
		if(__LUCID_ENV__ == 'cli')
		{
			return '';
		}
		$return = ob_get_clean();
		ob_start();
		return $return;
	}
	
	public static function deinit($emit_json=true)
	{
		global $lucid;
		lucid::log('---------------------------------------------------------------');
		
		if($emit_json && __LUCID_ENV__ == 'http')
		{
			header('Content-type: application/json');
			$lucid->response['end_time'] = microtime(true);
			$result = json_encode($lucid->response);
			exit($result);
		}
		else
		{
			exit("\n");
		}
	}

	public static function log($string_to_log,$severity=1,$type='debug')
	{
		global $lucid;
		
		# format some basic info.
		$ip	  = str_pad(((isset($_SERVER['REMOTE_ADDR']))?$_SERVER['REMOTE_ADDR']:'127.0.0.1'),15,' ');
		$session = (session_id() == '')?'[nosession]':session_id();
		
		# tidy up the string a bit
		if(is_array($string_to_log))
		{
			$string_to_log = print_r($string_to_log,true);
		}
		$string_to_log = strtr($string_to_log,"\n",' ');
		
		# construct the final string
		$out  = 'ip:'.$ip.'|sess:'.$session.'|'.'sev:'.$severity.'|';
		$out .= str_pad('type:'.$type,18,' ').'| ';
		$out .= $string_to_log."\n";
		
		# log either to a specified file, or to the error_log
		if(isset($lucid->config['log_path']))
		{
			if(!isset($lucid->config['log-handle']) or is_null($lucid->config['log-handle']))
			{
				$lucid->config['log-handle'] = fopen($lucid->config['log_path'],'a');
			}
			fwrite($lucid->config['log-handle'],$out);
		}
		else
		{
			error_log($out);
		}
	}
}

?>