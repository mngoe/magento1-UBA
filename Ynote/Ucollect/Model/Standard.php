<?php
 
class Ynote_Ucollect_Model_Standard extends Mage_Payment_Model_Method_Abstract
{
 
	protected $_code = 'ucollect';
	 
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = false;
	protected $_canUseForMultishipping  = false;
 
	/**
	* Return Order place redirect url
	*
	* @return string
	*/
	public function getOrderPlaceRedirectUrl(){
		return Mage::getUrl('ucollect/index/redirect', array('_secure' => true));
	}
 
}