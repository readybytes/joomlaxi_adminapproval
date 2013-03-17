<?php
defined('_JEXEC') or die('Restricted access');




echo sprintf(JText::_('PLG_EMAIL_EMAIL_TO_ADMIN_FOR_APPROVAL'),
				$generaldetails['name'],$generaldetails['username'],
				$generaldetails['email'],$generaldetails['actLink']);
if($msg){
	foreach($msg as $name => $value){	
		echo $name ." : ". $value."\n";
	}
}								
