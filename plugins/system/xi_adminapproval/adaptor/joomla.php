<?php 
/*
* Author : Team JoomlaXi @ Ready Bytes Software Labs Pvt. Ltd.
* Email  : shyam@joomlaxi.com
* License : GNU-GPL V2
* (C) www.joomlaxi.com
*/

// no direct access

class XIAA_AdaptorJoomla
{
	public $name 	= 'Joomla';
	public $params 	= null;
	
	const PARAM_EMAIL_VERIFIED = 'email_verified';
	const PARAM_ADMIN_APPROVED = 'admin_approved';
	
	public function init($param)
	{
		// core joomla objects
		$this->app 			= JFactory::getApplication();
		$this->db 			= JFactory::getDbo();	
		$this->input 		= JFactory::getApplication()->input;
		
		$this->params = $param;
		
		// com_user configurations
		$this->userconfig 	= JComponentHelper::getParams( 'com_users' );
	}
	
	public function isActivationResendRequest()
	{
		return false;
	}
	
	public function doBlockActivationResendRequest()
	{
		// there is no mechanism of resending activation
		return false;
	}
	
	
	// I am in frontend and user came to activate 
	// index.php?option=com_users&task=registration.activate	
	public function isActivationRequest()
	{
		$option	= $this->input->getCmd('option');
		$task	= $this->input->getCmd('task');
		return ($option == 'com_users' && $task =='registration.activate');
	}
		
	public function isApprovalRequired($user_id)
	{
		return true;	
	}
		
	//user came to verify his email , check, mark and block user, inform admin	
	public function doEmailVerificationAndBlocking()
	{
		$activationKey  = $this->input->get('activation',null,'raw');
		if(is_null($activationKey))
		{
			$activationKey = $this->input->get('token',null,'raw');
		}
		$user_id 		= $this->getUserId($activationKey);
		
		//invalid request, joomla will handle it
		if(!$user_id){
			return;
		}
		
		// do we need approval
		if($this->isApprovalRequired($user_id)==false){
			return;
		}

		// --- mark & block the user
		$user = JUser::getInstance($user_id);
		$user->setParam(self::PARAM_EMAIL_VERIFIED, '1');
		$user->set('block', '1');
			
		jimport('joomla.user.helper');
		// Work for both Joomla 3 and Joomla 2.5 series 
		$newActivationKey= (JVERSION >= '3.0') ? JApplication::getHash(JUserHelper::genRandomPassword()) : JUtility::getHash( JUserHelper::genRandomPassword());
		//$newActivationKey=JUtility::getHash( JUserHelper::genRandomPassword());

		// generate new activation 
		// save new activation key by which our admin can enable user
		$user->set('activation',$newActivationKey);
		//$this->activation =  $newActivationKey;
			
		if(!$user->save()){
			// JError::raiseWarning('', JText::_( $user->getError()));
			$this->app->redirect('index.php',JText::_('PLG_XIAA_USER_SAVE_ERROR'));
		}
					
		// send an email to admin  with a ativation link and profile of user.
		$this->sendMessage($user_id, self::MESSAGE_APPROVAL);
			
		// show message to user
		// XITODO : redirect to given menu page
		$this->app->redirect('index.php', JText::_('PLG_XIAA_USER_EMAIL_VERIFIED_AND_ADMIN_WILL_APPROVE_YOUR_ACCOUNT'));
	}
	
	function isAdminDoingApproval()
	{
		// find activation key, verify user is already verified
		$activationKey  = $this->input->get('token',null,'alnum'); 
		$user_id 		= $this->getUserId($activationKey);
		$user 			= JUser::getInstance($user_id);
		return intval($user->getParam(self::PARAM_EMAIL_VERIFIED));
	}
	
	public function doAdminApprovalAndInformUser()
	{
		// find activation key, verify user is already verified
		$activationKey  = $this->input->get('token',null,'alnum'); 
		$user_id 		= $this->getUserId($activationKey);
	
		$user = JUser::getInstance($user_id);
		$user->setParam(self::PARAM_ADMIN_APPROVED,'1');
		$user->set('block', '0');
		$user->set('activation','');
		
		if (!$user->save()){
			$this->app->redirect('index.php',JText::_('PLG_XIAA_USER_SAVE_ERROR'));
		}else{
			// inform user
			$this->sendMessage($user_id, self::MESSAGE_APPROVED);		
			$this->app->redirect('index.php', JText::_('PLG_XIAA_USER_HAS_BEEN_APPROVED_BY_ADMIN'));
		}
	}
	
	const MESSAGE_APPROVED = 1;
	const MESSAGE_APPROVAL = 2;
	
	function sendMessage($user_id, $type=self::MESSAGE_APPROVAL)
	{
		//prepare basic varsinit
		$config = 	JFactory::getConfig();
		
		$site_name 			= $config->get('sitename');
		$site_url			= JURI::base();
		
		$email_from 		= $config->get('mailfrom');
		$email_fromname		= $config->get('fromname');
		
		// populate email content
		$data = $this->prepareMessage($user_id,$type);
		$email_subject = $data['subject'];
		$email_body	  = $data['message'];
		
		// decide whom to email
		switch($type)
		{
			case self::MESSAGE_APPROVAL :
				
					$admins = $this->_getAdminEmails();
					// Send mail to all users with users creating permissions and receiving system emails
					foreach($admins as $admin)
					{
						$return = JFactory::getMailer()->sendMail(
										$email_from, $email_fromname, $admin, 
										$email_subject, $email_body, true
									);
	
						// Check for an error.
						if ($return !== true) {
							$this->setError(JText::_('PLG_XIAA_SEND_MAIL_FAILED'));
							continue;
						}
					}
			
				break;
				
			case self::MESSAGE_APPROVED :
				
				$email	= JFactory::getUser($user_id)->email;
				$return = JFactory::getMailer()->sendMail(
										$email_from, $email_fromname, $email, 
										$email_subject, $email_body, true
								);
	
				// Check for an error.
				if ($return !== true) {
					$this->setError(JText::_('PLG_XIAA_SEND_MAIL_FAILED'));
				}
				
				break;
		}
		
		return;
	}
	
	function _getAdminEmails()
	{
		$admins = array();
				
		$emails = $this->params->get('approval_email','');
		$emails = explode(',', $emails);
		
		foreach ($emails as $email){
			jimport('joomla.mail.helper');
			$email = JString::trim($email);
			if(JMailHelper::isEmailAddress($email)){
				$admins[]=$email;
			}
		}

		// user does not 
		if(count($admins) <= 0){
			// get all admin users
			$query = 'SELECT email FROM #__users WHERE sendEmail=1';
			$admins = $this->db->setQuery($query)->loadColumn();
		}
			
		return $admins;	
	}
	
	
	function prepareMessage($user_id, $type=self::MESSAGE_APPROVAL)
	{
		$data = array();
		$obj = array();
		$obj = $this->populateUserData($obj, $user_id);
		
		switch($type)
		{
			case self::MESSAGE_APPROVAL :
				$data['subject'] = JText::sprintf('PLG_XIAA_APPROVAL_REQUIRED_FOR_ACCOUNT',$obj['website']);
				
				ob_start();
					$vars = $obj;
					include(dirname(dirname(__FILE__)).DS.'tmpl'.DS.'email_approval.php' );
				$data['message'] = ob_get_contents();
				ob_end_clean();
				
				break;
				
			case self::MESSAGE_APPROVED :
				$data['subject'] = JText::sprintf('PLG_XIAA_YOUR_ACCOUNT_APPROVED',$obj['website']);
				
				ob_start();
					$vars = $obj;
					include(dirname(dirname(__FILE__)).DS.'tmpl'.DS.'email_approved.php' );
				$data['message'] = ob_get_contents();
				ob_end_clean();
				break;
		}
		
		$data['subject'] 	= html_entity_decode($data['subject'], ENT_QUOTES);
		$data['message'] 	= html_entity_decode($data['message'], ENT_QUOTES);
		return $data; 
	}
	

	/**
	 * populate user's basic data
	 * @param unknown_type $obj
	 * @param unknown_type $user_id
	 */
	public function populateUserData($data=array(), $user_id)
	{
		// common infrmation
		$user 			= JUser::getInstance((int)$user_id);
		$data['profile']['name']		= $user->name;
		$data['profile']['email']		= $user->email;
		$data['profile']['username']	= $user->username;
		$data['link']	= JURI::root().'index.php?option=com_users&task=registration.activate&token='.$user->activation;
		
		$data['website'] = JFactory::getConfig()->get('sitename');
		$data['website_url']= JURI::base();

		return $data;
	}


	//	find user id from activation key
	function getUserId($activationKey) 
	{
		$query = 'SELECT id  FROM #__users'
				. ' WHERE '.$this->db->quoteName('activation').' = '.$this->db->Quote($activationKey)
				. ' AND block = '.$this->db->Quote('1');
				
		return intval($this->db->setQuery( $query )->loadResult());
	}
	
	
	/**
	 * The function will check if the user email is already verfied 
	 */
	function isUserEmailVerified($activationKey) 
	{
		$id = $this->getUser($activationKey);	
		$user = JUser::getInstance((int)$id);
		return $user->getParam(self::PARAM_EMAIL_VERIFIED );
	}
	
	
	/**
	 * Gives debug message for configuration
	 * @param $config
	 */
	function debugConfiguration($config) 
	{
		$return = JText::_('PLG_XI_ADMINAPPROVAL_USER_REGISTRATION_CONFIGURATION_OK');	
		
		//enqueue message that registration is not working 
		if(!$config->get('allowUserRegistration')){
			$return = JText::_('PLG_XI_ADMINAPPROVAL_USER_REGISTRATION_CONFIGURATION_DISABLED');
		}
		
		// enqueu Message : admin approval plugin cannot work without activation
		if(! $config->get('useractivation')){
			$reurn = JText::_('PLG_XI_ADMINAPPROVAL_USER_ACTIVATION_REQUIRED');
		}
		
		return $return;
	}
	
}
