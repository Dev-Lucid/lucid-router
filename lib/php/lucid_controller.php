<?php

class lucid_controller
{
	public static function __callStatic($controller,$params)
	{
		$class_name = 'lucid_controller__'.$controller;
		
		if(!class_exists($class_name))
		{
			# the class doesn't exist, we need to load the controller file
			if(file_exists('controllers/'.$controller.'/'.$controller.'.php'))
			{
				include('controllers/'.$controller.'/'.$controller.'.php');
			}
			else
			{
				lucid::log('controller class '.$controller.' did not exist, will use generic lucid_controller class instead');
			}
		}
		
		if(!class_exists($class_name))
		{
			$obj = new lucid_controller();
		}
		else
		{
			$obj = new $class_name();
		}
		$obj->name = $controller;
		
		return $obj;
	}
	
	public function load_view($view_name)
	{
		if(file_exists('controllers/'.$this->name.'/views/'.$view_name.'.php'))
		{
			include('controllers/'.$this->name.'/views/'.$view_name.'.php');
		}
		else
		{
			throw new Exception('Could not find view '.$view_name.' in controller '.$this->name,99);
		}
	}
	
	public function __call($view_name,$params)
	{
		$this->load_view($view_name,$params);
	}
}

?>