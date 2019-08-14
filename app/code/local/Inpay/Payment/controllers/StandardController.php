<?php
class Inpay_Payment_StandardController extends Mage_Core_Controller_Front_Action
{			
	protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }
	
	protected function _getPendingPaymentStatus()
    {
        return Mage::helper('inpay')->getPendingPaymentStatus();
    }
	
	protected function _checkReturnedPost()
    {            
        if (!$this->getRequest()->isPost())
            Mage::throwException('Wrong request type.');
            
        $request = $this->getRequest()->getPost();
        if (empty($request))
            Mage::throwException('Request doesn\'t contain POST elements.');			
            
        if (empty($request['order_id']) )
            Mage::throwException('Missing or invalid order ID');
            
        $order = Mage::getModel('sales/order')->loadByIncrementId($request['order_id']);
        if (!$order->getId())
            Mage::throwException('Order not found');

        return $request;
    }
	
	protected function _processSale($request)
    {				
		$order = Mage::getModel('sales/order');
		$order->loadByIncrementId($request['order_id']);            		      
		
		try {
			if($order->canInvoice()) {				
				$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
				if (!$invoice->getTotalQty()) {
					Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
				}
				$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
				$invoice->register();
				$transactionSave = Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder());
				$transactionSave->save();
				$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING,true,Mage::helper('inpay')->__('Payment successful through Inpay'));
				$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING,true,Mage::helper('inpay')->__('Invoice Reference:'.$request['invoice_reference']));
				$order->save();
			}			
		} catch (Mage_Core_Exception $e) {}											    
    }
	
	protected function _processCancel($request)
    {        
        $order = Mage::getModel('sales/order')->loadByIncrementId($request['order_id']);
		if ($order->canCancel()) {
            $order->cancel();
            $order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, Mage::helper('inpay')->__('Payment was canceled'));
            $order->save();
        }
    }
	
	protected function _processFail($request)
    {        
		$request = $this->getRequest()->getPost();
        $order = Mage::getModel('sales/order')->loadByIncrementId($request['order_id']);
		if ($order->canCancel()) {
            $order->cancel();
            $order->addStatusToHistory(Mage_Sales_Model_Order::STATUS_FRAUD, Mage::helper('inpay')->__('Payment failed'));
            $order->save();
        }

    }
	
	public function callbackAction()
	{				
		$session = $this->_getCheckout();        	
		$request = $this->_checkReturnedPost();
		
		$order = Mage::getModel('sales/order');			
        $order->loadByIncrementId($request['order_id']); 
		
        try {            		
			$parameters = array();
			foreach($request as $key=>$value)
			{
				$parameters[$key] = $request[$key];
			}
			
			$session = $this->_getCheckout();
			$isValidChecksum = false;
			$txnstatus = false;			
			
			$secret_key = Mage::getStoreConfig('payment/inpay/secret_key',Mage::app()->getStore()->getStoreId());						                    							
			if(isset($request['checksum']))			
				$isValidChecksum = Mage::helper('inpay')->verifychecksum($parameters,$secret_key);									
			
			if($request['invoice_status'] == "approved")
				$txnstatus = true;					

			if($txnstatus && $isValidChecksum){																		
				$this->_processSale($request);				
				try{
					$order->sendNewOrderEmail();
					echo "Ok";
				}    
				catch (Exception $ex) {  }					
            }
			else
				echo "Failed";
			
        } catch (Mage_Core_Exception $e) {           
			$this->_processFail($request);			
        }
	}
	
	public function responseAction()
	{
		try {			
			if(isset($_GET['inpay_invoice_status']) && $_GET['inpay_invoice_status'] == 'approved')
			{
				$session = $this->_getCheckout();
				$session->unsinpayRealOrderId();
				$session->setQuoteId($session->getinpayQuoteId(true));
				$session->setLastSuccessQuoteId($session->getinpaySuccessQuoteId(true));
				$this->_redirect('checkout/onepage/success');
				return;
			}
			else if(isset($_GET['inpay_invoice_status']) && $_GET['inpay_invoice_status'] == 'sum_too_low')
			{
				$session = $this->_getCheckout();
				$order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastOrderId());
				$order->setState(
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    $this->_getPendingPaymentStatus(),
                    Mage::helper('inpay')->__('We have registered insufficient funds on the payment.')
                )->save();
			}
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch(Exception $e) {
            $this->_debug('inpay error: ' . $e->getMessage());
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
	}
	
	public function redirectAction()
    {						
		try {
            $session = $this->_getCheckout();			
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($session->getLastRealOrderId());
            if (!$order->getId()) {
                Mage::throwException('No order for processing found');
            }
			
            if ($order->getState() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $order->setState(
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    $this->_getPendingPaymentStatus(),
                    Mage::helper('inpay')->__('Customer was redirected to inpay.')
                )->save();
            }
			
			 if ($session->getQuoteId() && $session->getLastSuccessQuoteId()) {
                $session->setinpayQuoteId($session->getQuoteId());
                $session->setinpaySuccessQuoteId($session->getLastSuccessQuoteId());
                $session->setinpayRealOrderId($session->getLastRealOrderId());
                $session->getQuote()->setIsActive(false)->save();
                $session->clear();
            }
			
            $this->loadLayout();
            $this->renderLayout();
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch(Exception $e) {
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
		
    }
	
	public function pendingAction()
	{
		$session = $this->_getCheckout();
		$order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
		$order->setState(
			Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
			$this->_getPendingPaymentStatus(),
			Mage::helper('inpay')->__('Your order has not yet been approved.')
		)->save();
		$this->_getCheckout()->addError("Your order has not yet been approved");
		$this->_redirect('checkout/cart');
	}

	public function cancelAction()
    {        
        $session = $this->_getCheckout();
		$order = Mage::getModel('sales/order');
		$order->loadByIncrementId($session->getLastRealOrderId());
		if (!$order->getId()) {
			Mage::throwException('No order for processing found');
		}
		$order->setState(Mage_Sales_Model_Order::STATE_CANCELED,true)->save();
		
        if ($quoteId = $session->getinpayQuoteId()) {
            $quote = Mage::getModel('sales/quote')->load($quoteId);
            if ($quote->getId()) {
                $quote->setIsActive(true)->save();
                $session->setQuoteId($quoteId);
            }
        }
		
        $session->addError(Mage::helper('inpay')->__('The order has been canceled.'));
        $this->_redirect('checkout/cart');		
    }
}