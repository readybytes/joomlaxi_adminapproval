<?php 
/*
* Author : Team JoomlaXi @ Ready Bytes Software Labs Pvt. Ltd.
* Email  : shyam@joomlaxi.com
* License : GNU-GPL V2
* (C) www.joomlaxi.com
*/

// no direct access

require_once 'joomla.php';
class XIAA_AdaptorJomSocial extends XIAA_AdaptorJoomla
{
	public $name = 'JomSocial';
	
	public function isApprovalRequired($args)
	{
		
	}
	
	// I am in frontend and user came to activate 
	// index.php?option=com_users&task=registration.activate	
	public function isActivationRequest()
	{
		$option	= $input->getCmd('option');
		$task	= $input->getCmd('task');
		
		$result = ($option == 'com_users' && $task =='registration.activate');
		
		return($result || parent::isActivationRequest($option, $task));
	}
	
	public function isPasswordResendRequest()
	{
		$option	= $input->getCmd('option');
		$task	= $input->getCmd('task');
		
		$result = ($option =='com_community' && $task =='activationResend');
		return($result || parent::isPasswordResendRequest());
	}
	
	public function populateUserData($obj,$user_id)
	{
		// joomla fills the info
		parent::populateUserData($obj,$user_id);
		
		//populate jomsocial
		require_once (JPATH_BASE. DS.'components'.DS.'com_community'.DS.'libraries'.DS.'core.php');
		$pModel  = CFactory::getModel('profile');
		$profile = $pModel->getEditableProfile($this->activationUserID);
		$fields  = $profile['fields'];
		
		$obj->jomsocial = array();
		
		foreach($fields as $name => $fieldGroup){
			foreach($fieldGroup as $field){

				$name = $field['name'];
				
				if($field['fieldcode']=='XIPT_PROFILETYPE'){
					require_once (JPATH_BASE. DS.'components'.DS.'com_xipt'.DS.'api.xipt.php');
					$profiletypeName = XiptAPI::getUserProfiletype($user_id,'name');
					$obj[$name]= $profiletypeName;
					continue;	
				}
				
				$fieldId = $pModel->getFieldId($field['fieldcode']);
					
				$query = 'SELECT value FROM #__community_fields_values' . ' '
						. 'WHERE `field_id`=' .$this->db->Quote( $fieldId ) . ' '
						. 'AND `user_id`=' . $this->db->Quote( $user_id );
						
				$result  = $this->db->setQuery( $query )->loadresult();
				
				if(!empty($result)){
					$obj[$name] =$result;
				}
			}
		}
	}
}