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
	public $name = 'Jomsocial';
	
	public function isApprovalRequired($args)
	{
		
	}
	
	// I am in frontend and user came to activate 
	// index.php?option=com_users&task=registration.activate	
	public function isActivationRequest()
	{
		$option	= $this->input->getCmd('option');
		$task	= $this->input->getCmd('task');
		
		$result = ($option == 'com_users' && $task =='registration.activate');
		
		return($result || parent::isActivationRequest($option, $task));
	}
	
	public function isActivationResendRequest()
	{
		$option	= $this->input->getCmd('option');
		$task	= $this->input->getCmd('task');
		
		$result = ($option =='com_community' && $task =='activationResend');
		return($result || parent::isPasswordResendRequest());
	}
	
	public function doBlockActivationResendRequest()
	{
		$email  =  $this->input->get('jsemail',null, 'STRING');

		$query	= ' SELECT `id` FROM `#__users` '
				. ' WHERE `email` = '.$this->db->quote($email);
					
		$id  = $this->db->setQuery($query)->loadResult();

		// user exist & email is verified => block it
		if($id && JUser::getInstance((int)$id)->getParam('email_verified')){						
			// admins approval is pending, so no resets
			// 	and tell user to wait for admin approval
			$this->app->redirect('index.php', JText::_('PLG_MSG_WAIT_FOR_ADMIN_APPROVE_YOUR_ACCOUNT'));			
		}
		
		// else do nothing, joomla will take care
		return;
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