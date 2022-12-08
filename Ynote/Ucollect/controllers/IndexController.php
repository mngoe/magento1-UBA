<?php

define('CIPG_PROTOCOL', 'https');
define('CIPG_HOST', 'ucollect.ubagroup.com');
define('CIPG_CONTEXT', 'cipg-payportal');
define('CIPG_MERCHANTID', 'XXXXXX');
define('CIPG_SERVICEKEY', 'XXXXX');
define('CIPG_URL', CIPG_PROTOCOL . '://' . CIPG_HOST . '/' . CIPG_CONTEXT);
define('CIPG_URL_REGISTER_JSON', CIPG_PROTOCOL . '://' . CIPG_HOST . '/' . CIPG_CONTEXT . "/regjtran");
define('CIPG_URL_REGISTER_XML', CIPG_PROTOCOL . '://' . CIPG_HOST . '/' . CIPG_CONTEXT . "/regxtran");
define('CIPG_URL_REGISTER_POST_PARAM', CIPG_PROTOCOL . '://' . CIPG_HOST . '/' . CIPG_CONTEXT . "/regptran");
define('CIPG_URL_VERIFY', CIPG_PROTOCOL . '://' . CIPG_HOST . '/' . CIPG_CONTEXT . "/confirmation/verify");
define('CIPG_URL_PAY', CIPG_PROTOCOL . '://' . CIPG_HOST . '/' . CIPG_CONTEXT . "/paytran");

class Ynote_Ucollect_IndexController extends Mage_Core_Controller_Front_Action
{

    protected $_responseUcollect = null;
    protected $_invoice = null;
    protected $_invoiceFlag = false;
    protected $_order = null;
    protected $_post = null;

    /**
     * When has error in treatment
     */
    public function failureAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Get checkout session
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function redirectAction()
    {
        $helper = Mage::helper('ucollect');
        $this->_post = array(
            "merchantId" => CIPG_MERCHANTID,
            "description" => "Commande eCommerce VisionConfort",
            "total" => $helper->_getAmount(),
            "date" => date("d/m/Y H:i:s"),
            "countryCurrencyCode" => "950",
            "noOfItems" => $helper->_getNbrLineOrder(),
            "customerFirstName" => $helper->_getCustomerFirstName(),
            "customerLastname" => $helper->_getCustomerLastName(),
            "customerEmail" => $helper->_getCustomerEmail(),
            "customerPhoneNumber" => $helper->_getCustomerPhoneNumber(),
            "referenceNumber" => $helper->_getOrderId(),
            "serviceKey" => CIPG_SERVICEKEY,
        );

        $ch = curl_init();

        $REGISTER_CIPG_TXN_URL = CIPG_URL_REGISTER_POST_PARAM;
        curl_setopt($ch, CURLOPT_URL, $REGISTER_CIPG_TXN_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS,  $this->_post); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


        $response = curl_exec($ch);
        $returnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
         
        Mage::log('----------',null,'ucollect.log');
        Mage::log('ReturnCode: '.$returnCode,null,'ucollect.log');
        Mage::log('Response: '.$response,null,'ucollect.log');

        //Check if there are no errors ie httpresponse == 200 -OK
        if ($returnCode == 200) {
            // $order->addStatusHistoryComment($this->__('Renvoi du client vers la page de paiement UBA. En attente de retour du client sur le site.'.$transactionid))->save();

            //If there are no errors, the transaction ID is returned
            $transactionid = $response;
            //This line declares the Link to pay for this transaction
            $paylink = CIPG_URL_PAY ."?id=" . $transactionid;
            Mage::log('Paylink: '.$paylink,null,'ucollect.log');
            //header("Location: $paylink");
            echo '<script type="text/javascript">';
            echo 'document.location.href="'.$paylink.'";';
            echo '</script>';
            echo 'Redirection vers la <a href="'.$paylink.'">plateforme de paiement UBA /</a>';
            //return $paylink;
        } else {
            //Get return Error Code, If there was an error during call
            //
            switch($returnCode){
                //200 is OK so, this should be insignificant if all is well
                case 200:

                    break;
                default:
                //Declare the Request Error
                    $result = 'HTTP ERROR -> ' . $returnCode;

                    break;
            }
            echo $result;

        }
    }

    public function ipnAction()
    {

        $helper = Mage::helper('ucollect');
        $params = $this->getRequest()->getParams();
        $bank = $params['bnk'];
        $refNo = $params["refNo"];
        $transactionId = $params["transactionId"];
        $status = $params["status"];
        $messages = array();

        Mage::log('++++++++++++++ IPN +++++++++++++++',null,'ucollect.log');
        Mage::log('transactionId: '.$transactionId,null,'ucollect.log');
        Mage::log('status: '.$status,null,'ucollect.log');
        Mage::log('refNo: '.$refNo,null,'ucollect.log');

        $order = Mage::getModel('sales/order');
        if ($refNo) {
            $order->loadByIncrementId($refNo);
            $this->_order=$order;
        }

        switch ($status) {
            case 'Approved':
                // On fait une seconde vérification asynchrone pour être sur que quelqu'un a pas simplement renvoyé la bonne URL avec son numéro de commande...

                $ch = curl_init();
                $REGISTER_CIPG_TXN_URL = CIPG_URL_VERIFY.'?cipgtxnref='.$transactionId.'&mytxnref=';
                $REGISTER_CIPG_TXN_URL = $REGISTER_CIPG_TXN_URL.$refNo.'&cipgid='.CIPG_MERCHANTID;
                curl_setopt($ch, CURLOPT_URL, $REGISTER_CIPG_TXN_URL);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS,  $this->_post); 
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $reponse = curl_exec($ch);
                $returnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $this->_responseUcollect = explode(';',$reponse);

                Mage::log('++++++++++++++ IPN Recheck Approved+++++++++++++++',null,'ucollect.log');
                Mage::log('transactionId: '.$transactionId,null,'ucollect.log');
                Mage::log('response: '.$this->_responseUcollect[0],null,'ucollect.log');

                $response = [];
                // Bien entendu ce contrôle est a remplacé une fois que je saurais ce que réponds cette URL...
                if($this->_responseUcollect[0]=="Approved Transaction"){
                    $order->addStatusHistoryComment($this->__('Paiement valide par la plateforme UBA.<br/> Reference Transaction:'.$transactionId))
                            ->save();
                    $this->getCheckoutSession()->getQuote()->setIsActive(false)->save();
                    // Set redirect URL
                    $response['redirect_url'] = 'checkout/onepage/success';

                    // Get sips return data
                    $messages[] = $helper->__('Payment accepted by Ucollect') . '<br /><br />' . $transactionId;


                    // Update payment
                    $this->_processOrderPayment($transactionId);

                    // Create invoice
                    if ($this->_invoiceFlag) {
                        $invoiceId = $this->_processInvoice();
                        $messages[] = $helper->__('Invoice #%s created', $invoiceId);
                    }

                    // Add messages to order history
                    foreach ($messages as $message) {
                        $this->_order->addStatusHistoryComment($message);
                    }

                    // Save order
                    $this->_order->save();

                    // Send order confirmation email
                    if (!$this->_order->getEmailSent() && $this->_order->getCanSendNewEmailFlag()) {
                        try {
                            if (method_exists($this->_order, 'queueNewOrderEmail')) {
                                $this->_order->queueNewOrderEmail();
                            } else {
                                $this->_order->sendNewOrderEmail();
                            }
                        } catch (Exception $e) {
                            Mage::logException($e);
                        }
                    }
                    // Send invoice email
                    if ($this->_invoiceFlag) {
                        try {
                            $this->_invoice->sendEmail();
                        } catch (Exception $e) {
                            Mage::logException($e);
                        }
                    }
                }else{
                    $order->addStatusHistoryComment($this->__('Paiement invalide par la plateforme UBA.<br/> Reference Transaction:'.$this->_responseUcollect[0]." - ".$this->_responseUcollect[1]))
                            ->save();
                    $response['redirect_url'] = '*/*/failure';
                }
                break;
            default:
                // Log error
                $errorMessage = $this->__('Paiement non valide par UBA:  %s.<br />Reference Transaction : %s', $status, $transactionId);
                // Add error on order message, cancel order and reorder
                if ($order->getId()) {
                    if ($order->canCancel()) {
                        try {
                            $order->registerCancellation($errorMessage)->save();
                        } catch (Mage_Core_Exception $e) {
                            Mage::logException($e);
                        } catch (Exception $e) {
                            Mage::logException($e);
                            $errorMessage .= '<br/><br/>';
                            $errorMessage .= $this->__('The order has not been cancelled.'). ' : ' . $e->getMessage();
                            $order->addStatusHistoryComment($errorMessage)->save();
                        }
                    } else {
                        $errorMessage .= '<br/><br/>';
                        $errorMessage .= $this->__('The order was already cancelled.');
                        $order->addStatusHistoryComment($errorMessage)->save();
                    }

                    // Refill cart
                    Mage::helper('ucollect')->reorder>($refNo);

                }

                // Set redirect URL
                $response['redirect_url'] = '*/*/failure';
                break;
        }
        $this->_redirect($response['redirect_url'], array('_secure' => true));
    }

    /**
     * Update order payment
     */
    protected function _processOrderPayment($transactionId)
    {
        try {
            // Set transaction
            $payment = $this->_order->getPayment();
            $payment->setTransactionId($transactionId);
            $data = $this->_responseUcollect;
            $payment->addData($data);
            $payment->save();
            // Add authorization transaction
            $this->_invoiceFlag = true;
            $this->_order->save();
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::app()->getResponse()
                    ->setHeader('HTTP/1.1', '503 Service Unavailable')
                    ->sendResponse();
            exit;
        }
    }

    /**
     * Create invoice
     *
     * @return string
     */
    protected function _processInvoice()
    {
        try {
            $this->_invoice = $this->_order->prepareInvoice();
            $this->_invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $this->_invoice->register();

            $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($this->_invoice)->addObject($this->_invoice->getOrder())
                    ->save();
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::app()->getResponse()
                    ->setHeader('HTTP/1.1', '503 Service Unavailable')
                    ->sendResponse();
            exit;
        }

        return $this->_invoice->getIncrementId();
    }


}

