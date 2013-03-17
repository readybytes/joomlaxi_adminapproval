<?php 
/*
* Author : Team JoomlaXi @ Ready Bytes Software Labs Pvt. Ltd.
* Email  : shyam@joomlaxi.com
* License : GNU-GPL V2
* (C) www.joomlaxi.com
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );
jimport('joomla.filesystem.file');

defined( 'XIAA_JOOMLA' ) or define('XIAA_JOOMLA', '1');
defined( 'XIAA_JOMSOCIAL' ) or define('XIAA_JOMSOCIAL', '2');
defined( 'XIAA_JSPT' ) or define('XIAA_JSPT', '3');
$version = new JVersion();
defined('XIAA_JOOMLA_16') or define('XIAA_JOOMLA_16',($version->RELEASE === '1.6'));
defined('XIAA_JOOMLA_15') or define('XIAA_JOOMLA_15',($version->RELEASE === '1.5'));

jimport( 'joomla.filesystem.folder' );	

class plgSystemxi_adminapproval extends JPlugin
{
	//var $mainframe;
	
	var $debugMode = 1;
	var $isAdmin = 'false';
	var $mode = 1;
	var $user;
	var $db;
	var $params;
	var $activation;
	var $parameterLoaded = false;
	var $activationUserID;
	var $jsptExist;
	var $xiptExist;
	
	function plgSystemxi_adminapproval( &$subject, $params )
	{
		 parent::__construct( $subject, $params );
	}
	
	//Get message and display it
	function displayMessage($message){
		$mainframe = JFactory::getApplication();
		$mainframe->enqueueMessage($message);
		return;
	}
	
	//rediect to specified url and enqueue message
	function redirectUrl($url,$msg=''){
		$mainframe = JFactory::getApplication();
		$mainframe->redirect($url,$msg);
		return;
	}
	
	//load initial parameters
	function loadParameter() 
	{
		// if params already loaded then simply return
		if($this->parameterLoaded)
			return;

		//load langauge
		JPlugin::loadLanguage( 'plg_xi_adminapproval', JPATH_ADMINISTRATOR );
		$this->db		= JFactory::getDBO();
		
		$plugin 		= JPluginHelper::getPlugin('system', 'xi_adminapproval');
		
		if(XIAA_JOOMLA_15)
			$this->params   = new JParameter($plugin->params);
		else
			$this->params   = new JRegistry($plugin->params);
			
		$this->debugMode  = $this->params->get('debugMode', 0);
		$this->mode 	  = $this->params->get('Mode', 1);
		
		if(XIAA_JOOMLA_15)
			$this->activation = JRequest::getVar('activation', '', '', 'alnum' );
		else
			$this->activation = JRequest::getVar('token', '', '', 'alnum' );
			
		$this->activation = $this->db->getEscaped( $this->activation );
		
		$usersConfig 					= JComponentHelper::getParams( 'com_users' );
		$this->userActivation			= $usersConfig->get('useractivation');
		$this->allowUserRegistration	= $usersConfig->get('allowUserRegistration');
		
		$this->activationUserID = $this->getUserIDFromActivationKey($this->activation);

		// set params already loaded
		$this->parameterLoaded 	= true;
	}
	
	// when admin is logged in backend and plugin is enabled
	// if joomla configuration not proper then show warning message
	
	function showAdminDebugMessage() 
	{
		$this->loadParameter();
		
		//enqueue message that registration is not working 
		if( ! $this->allowUserRegistration){
			$this->displayMessage(JText::_('PLG_MSG_REGISTRATION_NOT_WORKING'));
			// and return false
			return false;
		}
		
		// enqueu Message : admin approval plugin cannot work without activation
		if( ! $this->userActivation){
			$this->displayMessage(JText::_('PLG_MSG_PLUGIN_NOT_WORK_WITHOUT_ACTIVATION'));
			// return false
			return false;
		}
		
		return true;
	}
	
	function blockActivationResend($email)
	{
		jimport('joomla.mail.helper');
		jimport('joomla.user.helper');
			
		// Make sure the e-mail address is valid
		if (!JMailHelper::isEmailAddress($email))
			return;

		//find the user by email ID
		$db 	= JFactory::getDBO();
		$query	= ' SELECT `id` FROM `#__users` '
				. ' WHERE `email` = '.$db->Quote($email);
					
		$db->setQuery($query);
		$id = $db->loadResult();
		// Check the results
		if($id === false)
			return;
						
		//now check if it was set to activate by admin
		$emailVerified 	= JUser::getInstance((int)$id)->getParam('emailVerified');

		//if email already verified, then no need to do discard
		if($emailVerified == false)
			return;
			
		// email was already Verified, so discard user request, 
		// and tell user to wait for admin approval
		$this->displayMessage(JText::_('PLG_MSG_WAIT_FOR_ADMIN_APPROVE_YOUR_ACCOUNT'));
		$this->redirectUrl('index.php');
	}
	
	function onAfterRoute()
	{
		//get option & task from URL
		$option	= JRequest::getCmd('option');
		$task	= JRequest::getCmd('task');
		
		// 1. if backend then call showAdminDebugMessage and return
		$mainframe = JFactory::getApplication();
		if($mainframe->isAdmin()){
			$this->showAdminDebugMessage();
		}
		
		
		// 2. I am in frontend and user came to activate 
		//  index.php?option=com_user&task=activate&activation=
		if(XIAA_JOOMLA_15)
		{
			if(($option !='com_user' || $task !='activate')
				&& ($option !='com_community' || $task !='activationResend') ) 
				return;
		}
		
		else
		{
			if(($option !='com_users' || $task !='registration.activate')
				&& ($option !='com_community' || $task !='activationResend') ) 
				return;
		}
		
		//Security Fix
		if($option =='com_community' && $task =='activationResend')
		{	
			$email	=  JRequest::getVar( 'jsemail', '', 'REQUEST');
			$this->blockActivationResend($email);
		}
		
		// 3. load all params and related information e.g. language/js/css etc.
		$this->loadParameter();
		
		// 4. verify that system configuration is correct, 
		//    show admin debug message again
		//    false -> redirect to index.php
		if($this->sanitizeAndVerifyInputs()==false)
		{
			//TODO: redirect to index.php
			return;
		}
			
		//5. user / Admin is here to verification	
		if($this->isAdminDoingApproval()){
			// 6. is a admin, we should enable a blocked user				
			$user = JUser::getInstance($this->activationUserID);
			// activate user and enable
			$user->setParam('emailVerified','0');
			$user->set('block', '0');
			$user->set('activation','');
			if (!$user->save()){
					JError::raiseWarning('', JText::_( $user->getError()));
					$this->redirectUrl('index.php',JText::_('PLG_DEBUG_USER_SAVE_ERROR'));
					exit();
			}
			// inform user
			$this->sendEmails('PLG_EMAIL_ACCOUNT_ACTIVATION_EMAIL_TO_USER');

			// show a message to admin
			$this->displayMessage(JText::_('PLG_MSG_USER_HAS_BEEN_APPROVED_BY_ADMIN'));
		}
		else{
			// 7. user came to verify his email , check, mark and block user, inform admin
			jimport('joomla.user.helper');

			// this plugin should also work without JS Profile Types
			$MY_PATH_ADMIN_JSPT	  = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_jsprofiletypes';
			$this->jsptExist 	  = JFolder::exists($MY_PATH_ADMIN_JSPT);
					
			if($this->jsptExist && $this->mode==3)
			{				
				require_once (JPATH_BASE. DS.'components'.DS.'com_community'.DS.'libraries'.DS.'profiletypes.php');
				// some issue here
				$pID = CProfiletypeLibrary::getUserProfiletypeFromUserID($this->activationUserID);
				$profiletypeName = CProfiletypeLibrary::getProfileTypeNameFrom($pID);
				// TODO : what to do for $pId =0 
				
				// if admin approval NOT required, then do nothing let the joomla handle
				if($pID && CProfiletypeLibrary::getProfileTypeData($pID,'approve') == false)
				{
					if($this->debugMode)
						$this->displayMessage('ProfileType='.$pID.' and approval not required');
					return;
				}
			}
			
			$MY_PATH_ADMIN_XIPT	  = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_xipt';
			$this->xiptExist 	  = JFolder::exists($MY_PATH_ADMIN_XIPT);
			
			if($this->xiptExist && $this->mode==3){
				require_once (JPATH_BASE. DS.'components'.DS.'com_xipt'.DS.'api.xipt.php');
				// some issue here 
				$pID = XiptAPI::getUserProfiletype($this->activationUserID);
				$profiletypeName = XiptAPI::getUserProfiletype($this->activationUserID,'name');
				// TODO : what to do for $pId =0 
				$allCondition = array();
				$allCondition = XiptAPI::getProfiletypeInfo($pID);
				// if admin approval NOT required, then do nothing let the joomla handle
				if($allCondition){
					if($pID &&  $allCondition[0]->approve == false){
						if($this->debugMode)
							$this->displayMessage('ProfileType='.$pID.' and approval not required');
						return;
					}
				}		
			}
			
			// --- mark
			$user = JUser::getInstance($this->activationUserID);
			$user->setParam('emailVerified','1');
			
			// --- also block the user
			$user->set('block', '1');
			
			$newActivationKey=JUtility::getHash( JUserHelper::genRandomPassword());
			// generate new activation 
			// save new activation key by which our admin can enable user
			$user->set('activation',$newActivationKey);
			$this->activation =  $newActivationKey;
			
			if(!$user->save()){
				JError::raiseWarning('', JText::_( $user->getError()));
				$this->redirectUrl('index.php',JText::_('PLG_DEBUG_USER_SAVE_ERROR'));
				exit();
			}
					
			// send an email to admin  with a ativation link
			// and profile of user.
			$this->sendEmails('PLG_EMAIL_EMAIL_TO_ADMIN_FOR_APPROVAL');
			
			// show message to user
			$this->displayMessage(JText::_('PLG_MSG_EMAIL_VERIFIED_AND_ADMIN_WILL_APPROVE_YOUR_ACCOUNT'));
		}
		
		// 8. always redirect to index.php
		$this->redirectUrl('index.php');
		return;
	}	
	// check and verify inputs
	// if some issue then halt the process
	function sanitizeAndVerifyInputs() 
	{
		// do checks and if things found not fine, then add a message and return false
		// else return true
		if (empty( $this->activation ) || $this->allowUserRegistration == '0' 
			 || $this->userActivation == '0' )
			 return false;
			 
		// check userID
		if(XIAA_JOOMLA_15){
			if ($this->activationUserID < 62 )
			{
				//	Is it a valid user to activate?
				if($this->debugMode)
					$this->displayMessage(JText::_('PLG_MSG_NOT_A_VALID_USER_TO_ACTIVATE'));	
				return false;
			}
		}
		else{
		if ($this->activationUserID < 42 )
			{
				//	Is it a valid user to activate?
				if($this->debugMode)
					$this->displayMessage(JText::_('PLG_MSG_NOT_A_VALID_USER_TO_ACTIVATE'));	
				return false;
			}
		}
		// no error
		return true;
	}
	
	// return user id from activation key
	function getUserIDFromActivationKey() 
	{
		
		$query = 'SELECT id'
			. ' FROM #__users'
			. ' WHERE '.$this->db->nameQuote('activation').' = '.$this->db->Quote($this->activation)
			. ' AND block = '.$this->db->Quote('1')
			. ' AND lastvisitDate = '.$this->db->Quote('0000-00-00 00:00:00');
		$this->db->setQuery( $query );
		$id = intval( $this->db->loadResult() );		
		return $id;
	}
	/**
	 * The function will check if the user is admin or not 
	 */
	function isAdminDoingApproval() 
	{
		// Lets get the id of the user we want to activate
		$id = $this->getUserIDFromActivationKey($this->activation);	
		
		
		$this->user 	= JUser::getInstance((int)$id);
		$this->isAdmin 	= $this->user->getParam('emailVerified') ? true : false;
		
		if($this->debugMode && !$this->isAdmin)
			$this->displayMessage(JText::_('PLG_MSG_NOT_AN_ACTIVATION_BY_ADMIN'));

		return $this->isAdmin;		
	}
	
	function get_jomsocial_profile_fields()
	{
		require_once (JPATH_BASE. DS.'components'.DS.'com_community'.DS.'libraries'.DS.'core.php');
		$pModel  = CFactory::getModel('profile');
		$profile = $pModel->getEditableProfile($this->activationUserID);
		return $profile['fields'];
	}

	function generate_message_for_profile_fields()
	{	
		require_once (JPATH_BASE. DS.'components'.DS.'com_community'.DS.'libraries'.DS.'core.php');
		$fields 	= $this->get_jomsocial_profile_fields($this->activationUserID);
		$pModel 	= CFactory::getModel('profile');
		$xiJsDetails=array();
		//$message = " List of profile fileds of user :- \n ";
		if($this->debugMode)
				$this->displayMessage('userid = '.$this->activationUserID);
		foreach($fields as $name => $fieldGroup)
		{
			foreach($fieldGroup as $field)
			{
				if($this->debugMode)
					$this->displayMessage('field code = '.$field['fieldcode']);
				$count=1;	
				//if($pModel->_fieldValueExists($field['fieldcode'],$this->activationUserID))
				//{
					$fieldId = $pModel->getFieldId($field['fieldcode']);
					$query = 'SELECT value FROM #__community_fields_values' . ' '
					. 'WHERE `field_id`=' .$this->db->Quote( $fieldId ) . ' '
					. 'AND `user_id`=' . $this->db->Quote( $this->activationUserID );
					
					$this->db->setQuery( $query );
					$result = $this->db->loadresult();
					
					if(!empty($result)){
						$xiJsDetails[$field['name']]=$result;
						
						if($field['fieldcode']=='XIPT_PROFILETYPE'){
							require_once (JPATH_BASE. DS.'components'.DS.'com_xipt'.DS.'api.xipt.php');
							$profiletypeName = XiptAPI::getUserProfiletype($this->activationUserID,'name');
							$xiJsDetails[$field['name']]= $profiletypeName;	
						}						
					}
										//}
			}
			
		}
		return $xiJsDetails;
	}
	
	function generalInformation() {
		// common infrmation
		$user 			= JUser::getInstance((int)$this->activationUserID);
		$var['name']	= $user->name;
		$var['email']	= $user->email;
		$var['username']= $user->username;
		
		// activation link
		if(XIAA_JOOMLA_15)
			$link = JRoute::_(JURI::Root()."index.php?option=com_user&task=activate&activation="
						.$this->activation, false);
						
		else
			$link = JRoute::_(JURI::Root()."index.php?option=com_users&task=registration.activate&token="
						.$this->activation, false);
						
		$var['actLink']	= $link;
						
		return $var;
	}
	
	function sendEmails($msgID) {
		$mainframe 		= JFactory::getApplication();
		$usersConfig 	= JComponentHelper::getParams( 'com_users' );
		$sitename 		= $mainframe->getCfg( 'sitename' );
		$mailfrom 		= $mainframe->getCfg( 'mailfrom' );
		$fromname 		= $mainframe->getCfg( 'fromname' );
		$siteURL		= JURI::base();
		
		// send approval email
		switch($msgID)
		{
			case 'PLG_EMAIL_ACCOUNT_ACTIVATION_EMAIL_TO_USER' :
				$data		= array();
				$data		= $this->generalInformation();
				$email		= $data['email'];
				$subject 	= "You are Approved";
				$subject 	= html_entity_decode($subject, ENT_QUOTES);			
				$message 	= sprintf(JText::_('PLG_EMAIL_ACCOUNT_ACTIVATION_EMAIL_TO_USER'),$data['name']);	
				$message 	= html_entity_decode($message, ENT_QUOTES);
				JUtility::sendMail($mailfrom, $fromname, $email, $subject, $message);
			
				if($this->debugMode)
					$this->displayMessage($message);
				return;
				
			case 'PLG_EMAIL_EMAIL_TO_ADMIN_FOR_APPROVAL' :
				$getval = array();
				$getval = $this->getMessage();
				//get all super administrator
				if(XIAA_JOOMLA_15)
				{
					$query = 'SELECT id, name, email, sendEmail' .
							' FROM #__users' .
							' WHERE LOWER( usertype ) = "super administrator"';
				}
				else
				{
					$query = 'SELECT id, name, email, sendEmail' .
							' FROM #__users' .
							' WHERE id IN (' .
							' SELECT user_id' .
							' FROM #__user_usergroup_map' .
							' WHERE group_id = 8)';
				}
				$this->db->setQuery( $query );
				$rows = $this->db->loadObjectList();
		
				// Send mail to all  superadministrators id
				foreach( $rows as $row ){
					if ($row->sendEmail){
						JUtility::sendMail($mailfrom, $fromname, $row->email, $getval['subject'], $getval['message']);
					if($this->mode==2 || $this->mode==3)
						$this->sendJomsocialMessage($mailfrom, $fromname, $row->id, $getval['subject'], $getval['message']);
					}
				}
				
				if($this->debugMode)
					$this->displayMessage($getval['message']);
				return;
		}
	}
	
	function getMessage() {

		//send request to approve to ADMIN
		$val['subject'] 	= "Email for Approval";
		$val['subject'] 	= html_entity_decode($val['subject'], ENT_QUOTES); 

		if(XIAA_JOOMLA_15)
			require_once (JPATH_ROOT.DS.'plugins'.DS.'system'.DS
								.'xi_adminapproval'.DS.'tmpl'.DS.'mail-to-admin.php' );

		else
			require_once (JPATH_ROOT.DS.'plugins'.DS.'system'.DS
								.'xi_adminapproval'.DS.'xi_adminapproval'.DS.'tmpl'.DS.'mail-to-admin.php' );
		$generaldetails		= array();
		$generaldetails		= $this->generalInformation();
		
		switch ($this->mode)
		{
			case XIAA_JOOMLA :
				break;			
			
			case XIAA_JSPT :
			case XIAA_JOMSOCIAL : 
				$msg = $this->generate_message_for_profile_fields();
				if($msg == '' && $this->debugMode){
					$this->displayMessage(JText::_('PLG_MSG_NOT_JOMSOCIAL_AND_JSPT_USER'));
				}
				break;
		
			default:
				assert(0);
		}
		
		$mailJs 		= new mailtoadmin();
		$val['message']	= $mailJs->display($generaldetails,$msg);
		$val['message'] = html_entity_decode($val['message'], ENT_QUOTES);
		return $val;
	}
	
	function sendJomsocialMessage($mailfrom, $fromname, $to, $subject, $message)
	{	    
		$db 	= JFactory::getDBO();
		//$my	= JFactory::getUser($to)->id;
		$date	= JFactory::getDate(); //get the time without any offset!
		$cDate	= $date->toMySQL(); 
		$model 	= CFactory::getModel( 'inbox' );
		$obj 	= new stdClass();
		$obj->id 		= null;
		$obj->from		= 0;
		$obj->posted_on = $date->toMySQL();
		$obj->from_name	= $fromname;
		$obj->subject	= $subject;
		$obj->body		= $message;
		
		$db->insertObject('#__community_msg', $obj, 'id');
		
		// Update the parent
		$obj->parent = $obj->id;
		$db->updateObject('#__community_msg', $obj, 'id');
		
		$model->addReceipient($obj, $to);    
		
		return $obj->id;
	}
	
}


