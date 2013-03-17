<?php
defined('_JEXEC') or die('Restricted access');

	class mailtoadmin 
	{
		function display($generaldetails,$msg)
		{
			ob_start();						
				echo sprintf(JText::_('PLG_EMAIL_EMAIL_TO_ADMIN_FOR_APPROVAL'),
								$generaldetails['name'],$generaldetails['username'],
								$generaldetails['email'],$generaldetails['actLink']);
				if($msg){
					foreach($msg as $name => $value){	
						echo $name ." : ". $value."\n";
					}
				}								
		    $content=ob_get_contents();
			ob_clean();
			return $content;
	    } 
	}
