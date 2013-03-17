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
	
	public function isApprovalRequired($args)
	{
		
	}
	
	// I am in frontend and user came to activate 
	// index.php?option=com_users&task=registration.activate	
	public function isActivationRequest($option, $task)
	{
		$result = ($option == 'com_users' && $task =='registration.activate');
		
		return($result || parent::isActivationRequest($option, $task));
	}
	
	public function isPasswordResendRequest($option, $task)
	{
		$result = ($option =='com_users' && $task =='reset.request');
		
		return($result || parent::isActivationRequest($option, $task));
	}
}