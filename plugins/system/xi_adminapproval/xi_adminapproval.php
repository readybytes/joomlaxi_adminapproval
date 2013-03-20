<?php 
/*
* Author : Team JoomlaXi @ Ready Bytes Software Labs Pvt. Ltd.
* Email  : shyam@joomlaxi.com
* License : GNU-GPL V2
* (C) www.joomlaxi.com
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.plugin.plugin' );
jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder' );	

class plgSystemxi_adminapproval extends JPlugin
{
	const MODE_JOOMLA		= 'joomla';
	const MODE_JOMSOCIAL	= 'jomsocial';
	const MODE_JSPT			= 'jspt';
	
	function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}
	
	/**
	 * 
	 * @param unknown_type $input
	 * @return XIAA_AdaptorJoomla
	 */
	public function getAdaptor($input)
	{
		$mode 	  = $this->params->get('mode', self::MODE_JOOMLA);
		include 'adaptor'.DS.$mode.'.php';
		$className = 'XIAA_Adaptor'.$mode;
		$object = new $className();
		$object->init($this->params);
		
		return $object;
	}

	function onAfterRoute()
	{
		$app = JFactory::getApplication();
		
		// 1. if backend then call showAdminDebugMessage and return
		if($app->isAdmin()){
			//return;
		}

		// get adaptor
		$adaptor = $this->getAdaptor($app->input);
		
		// 2. If activation request, then handle it
		if($adaptor->isActivationRequest()){
			
			// Admin is here to verification ?	
			if($adaptor->isAdminDoingApproval()){
				$adaptor->doAdminApprovalAndInformUser();
				return;		
			}
			
			// check for activation, if eveything is fine
			// block the user and ask admin to approve
			$adaptor->doEmailVerificationAndBlocking();		
		}
		
		// 2. If activation resend request 
		if($adaptor->isActivationResendRequest()){
			$adaptor->doBlockActivationResendRequest();
		}		
		
		// Do nothing
		return;
	}	
}
