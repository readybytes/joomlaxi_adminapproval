<?php 
/*
* Author : Team JoomlaXi @ Ready Bytes Software Labs Pvt. Ltd.
* Email  : shyam@joomlaxi.com
* License : GNU-GPL V2
* (C) www.joomlaxi.com
*/

// no direct access

require_once 'jomsocial.php';
class XIAA_AdaptorJspt extends XIAA_AdaptorJomSocial
{
	public $name = 'JomSocial';
	
	public function isApprovalRequired($user_id)
	{
		$file = JPATH_BASE. DS.'components'.DS.'com_xipt'.DS.'api.xipt.php';

		if(JFile::exists($file) && include_once $file){

			$pID = XiptAPI::getUserProfiletype($user_id);

			// TODO : what to do for $pId =0 
			$allCondition = array();
			$allCondition = XiptAPI::getProfiletypeInfo($pID);
			
			// if admin approval NOT required, then do nothing let the joomla handle
			if($allCondition && $pID){
				return $allCondition[0]->approve;
			}		
		}
		
		return false;
	}
}