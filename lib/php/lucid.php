<?php 

define('__LUCID_ENV__',(php_sapi_name() == 'cli' or PHP_SAPI == 'cli')?'cli':'http');

class lucid
{
    # never invoked directly, always use ::init()
	private function __construct()
	{
		$this->config = array(
            'send-errors'=>true,
            'db'=>array(
				'type'=>'mysql',
				'hostname'=>'localhost',
				'database'=>'test',
				'username'=>'root',
				'password'=>'root',
            ),
            'model-dir'=>__DIR__.'/../../../../db/models/',
            'log-file'=>'/tmp/debug.log',
            'default-content-mode'=>'replace',
            'default-content-position'=>'#center',
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
			include(__DIR__.'/lucid_controller.php');
			/*
			include(__DIR__.'/lucid_validator.php');
			include(__DIR__.'/lucid_jserror.php');
			include(__DIR__.'/lucid_model_sqlclause.php');
			include(__DIR__.'/lucid_model_arrayaccess.php');
			include(__DIR__.'/lucid_model_iterator.php');
			include(__DIR__.'/lucid_model.php');
			include(__DIR__.'/lucid_db_adaptor.php');
            include(__DIR__.'/lucid_utility.php');
            */
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
        
		lucid::log('init complete');
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
                $obj  = lucid_controller::$controller();
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
		lucid::log('deinit complete');
		
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
		
		$ip	  = (isset($_SERVER['REMOTE_ADDR']))?$_SERVER['REMOTE_ADDR']:'127.0.0.1';
		$session = (session_id() == '')?'[nosession]':session_id();
		
		if(is_array($string_to_log))
		{
			$string_to_log = print_r($string_to_log,true);
		}
		
		$string_to_log = strtr($string_to_log,"\n",' ');
		
		$out	 = 'type:'.$type.'|sev:'.$severity.'|ip:'.$ip.'|sess:'.$session.'|'.$string_to_log."\n";
		
		#error_log($out,3,$lucid->config['log-file']);
		error_log($out);
	}
}

?>