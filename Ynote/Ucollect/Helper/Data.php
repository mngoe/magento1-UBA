<?php

class Ynote_Ucollect_Helper_Data extends Mage_Core_Helper_Abstract
{
   

    protected $_response = null;
    protected $_message = null;
    protected $_error = false;
    protected $_config;
    protected $_order;
    protected $_quote;


//    abstract public function callRequest();

    /**
     * Get Payment Means
     *
     * @return string
     */
//    abstract protected function _getPaymentMeans();

    /**
     * Get normal return URL
     *
     * @return string
     */
//    abstract protected function _getNormalReturnUrl();

    /**
     * Get cancel return URL
     *
     * @return string
     */
//    abstract protected function _getCancelReturnUrl();

    /**
     * Get automatic response URL
     *
     * @return string
     */
//    abstract protected function _getAutomaticResponseUrl();

    /**
     * Return Order place redirect url
     *
     * @return string
     */
//    abstract function getOrderPlaceRedirectUrl();


    /**
     * Instantiate state and set it to state object
     * @param string $paymentAction
     * @param Varien_Object
     */
    public function initialize($paymentAction, $stateObject)
    {
        switch ($paymentAction) {
            case Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE:
            case Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE:
                $stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
                $stateObject->setStatus('pending_payment');
                $stateObject->setIsNotified(false);
                break;
            default:
                break;
        }
    }

    /**
     * Get redirect block type
     *
     * @return string
     */
    public function getRedirectBlockType()
    {
        return $this->_redirectBlockType;
    }

    /**
     * Get system response
     *
     * @return string
     */
    public function getSystemResponse()
    {
        return $this->_response;
    }

    /**
     * Get system message
     *
     * @return string
     */
    public function getSystemMessage()
    {
        return $this->_message;
    }

    /**
     * Has system error
     *
     * @return boolean
     */
    public function hasSystemError()
    {
        return $this->_error;
    }

    /**
     * Get config model
     *
     * @return Quadra_Atos_Model_Config
     */
    public function getConfig()
    {
        if (empty($this->_config)) {
            $config = Mage::getSingleton('atos/config');
            $this->_config = $config->initMethod($this->_code);
        }
        return $this->_config;
    }

    /**
     * Get Atos API Request Model
     *
     * @return Quadra_Atos_Model_Api_Request
     */
    public function getApiRequest()
    {
        return Mage::getSingleton('atos/api_request');
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote|boolean
     */
    protected function _getQuote()
    {
        if (empty($this->_quote)) {
            $quoteId = Mage::getSingleton('atos/session')->getQuoteId();
            $this->_quote = Mage::getModel('sales/quote')->load($quoteId);
        }
        return $this->_quote;
    }

    /**
     * Get current order
     *
     * @return Mage_Sales_Model_Order|boolean
     */
    public function _getOrder()
    {
        if (empty($this->_order)) {
            $session = Mage::getSingleton('checkout/session');
            $this->_order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
        }

        return $this->_order;
    }

    public function _getNbrLineOrder()
    {
    	$total=0;
    	if($this->_getOrder()){
    		$total=$this->_getOrder()->getItemsCollection()->getSize();
    	}
    	return $total;
    }

    /**
     * Get order amount
     *
     * @return string
     */
    public function _getAmount()
    {
        if ($this->_getOrder())
            $total = $this->_getOrder()->getTotalDue();
        else
            $total = 0;
        return number_format($total, 0, '', '');
    }

    /**
     * Get customer ID
     *
     * @return int
     */
    public function _getCustomerId()
    {
        if ($this->_getOrder())
            return (int) $this->_getOrder()->getCustomerId();
        else
            return 0;
    }

    /**
     * Get customer e-mail
     *
     * @return string
     */
    public function _getCustomerEmail()
    {
        if ($this->_getOrder())
            return $this->_getOrder()->getCustomerEmail();
        else
            return 'undefined';
    }

    /**
     * Get customer Firstname
     *
     * @return string
     */
    public function _getCustomerFirstName()
    {
    	$firstname = "undefined";
        if ($this->_getOrder()){
        	$firstname = $this->_getOrder()->getCustomerFirstname();
        }
        return $firstname;
    }


    /**
     * Get customer Firstname
     *
     * @return string
     */
    public function _getCustomerLastName()
    {
        $firstname = "undefined";
        if ($this->_getOrder()){
        	$firstname = $this->_getOrder()->getCustomerLastname();
        }
        return $firstname;
    }


     /**
     * Get customer phone number
     *
     * @return string
     */
    public function _getCustomerPhoneNumber()
    {
    	$firstname = "undefined";
        if ($this->_getCustomerId()){
		$customerData = Mage::getModel('customer/customer')->load($this->_getCustomerId());
		$address  = Mage::getModel('customer/address')->load($customerData->getDefaultBilling());
		//Zend_Debug::dump($customerData);
            	if($customerData->getPrimaryBillingAddress()!=false){
            		$firstname = $customerData->getPrimaryBillingAddress()->getTelephone();
            	}else{
               		$firstname = 12345;
            	}
        }
        return $firstname;
    }


    /**
     * Get customer IP address
     *
     * @return string
     */
    public function _getCustomerIpAddress()
    {
        return $this->_getQuote()->getRemoteIp();
    }

    /**
     * Get order inrement id
     *
     * @return string
     */
    public function _getOrderId()
    {
        return $this->_getOrder()->getIncrementId();
    }

    

    public function debugRequest($data)
    {
        $this->debugData(array('type' => 'request', 'parameters' => $data));
    }

    public function debugResponse($data, $from = '')
    {
        ksort($data);
        $this->debugData(array('type' => "{$from} response", 'parameters' => $data));
    }

    public function reorder($incrementId)
    {
        $cart = Mage::getSingleton('checkout/cart');
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);

        if ($order->getId()) {
            $items = $order->getItemsCollection();
            foreach ($items as $item) {
                try {
                    $cart->addOrderItem($item);
                } catch (Mage_Core_Exception $e) {
                    if (Mage::getSingleton('checkout/session')->getUseNotice(true)) {
                        Mage::getSingleton('checkout/session')->addNotice($e->getMessage());
                    } else {
                        Mage::getSingleton('checkout/session')->addError($e->getMessage());
                    }
                } catch (Exception $e) {
                    Mage::getSingleton('checkout/session')->addException($e, Mage::helper('checkout')->__('Cannot add the item to shopping cart.'));
                }
            }
        }

        $cart->save();
    }

}
