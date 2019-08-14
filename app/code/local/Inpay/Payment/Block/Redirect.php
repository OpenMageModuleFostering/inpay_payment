<?php
class Inpay_Payment_Block_Redirect extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('inpay/redirect.phtml');
    }
	
	protected function getOrder()
	{
		$lastPlacedOrder = Mage::getSingleton('checkout/session')->getLastOrderId();
		return Mage::getModel('sales/order')->load($lastPlacedOrder);
	}
	
	public function getFormAction()
    {
        if(Mage::getStoreConfig('payment/inpay/test_mode',Mage::app()->getStore()->getStoreId())==1)
			return "https://test-secure.inpay.com";
		else
			return "https://secure.inpay.com";		
    }	
	
	public function getFormData()
    {		
		$merchant_id = Mage::getStoreConfig('payment/inpay/merchant_id',Mage::app()->getStore()->getStoreId());
		$secret_key = Mage::getStoreConfig('payment/inpay/secret_key',Mage::app()->getStore()->getStoreId());		
		
		$price = number_format($this->getOrder()->getGrandTotal(),2,'.','');
        $currency = $this->getOrder()->getOrderCurrencyCode(); 				 								
		$_totalData = $this->getOrder()->getData();
		
		$order = $this->getOrder();		
		$cartTotalItem = count($order->getAllItems());
		
		if($cartTotalItem > 3)
			$order_text = $cartTotalItem." in your cart";
		else
		{
			$productName = array();			
			foreach ($order->getAllItems() as $item) {
				$productName[] = $item->getName();				
			}
			$tempString = implode(" | ",$productName);
			if(strlen($tempString) <= 255)
				$order_text = $tempString;
			else
				$order_text = $cartTotalItem." in your cart";
		}
    	$params = 	array(
						'merchant_id'		=> $merchant_id,  				
						'order_id' 			=> $this->getOrder()->getRealOrderId(),
						'amount' 			=> $price,
						'currency' 			=> $currency,
						'order_text' 		=> $order_text,						
						'return_url' 		=> Mage::getBaseUrl().'inpay/standard/response',
						'pending_url'		=> Mage::getBaseUrl().'inpay/standard/pending',
						'cancel_url' 		=> Mage::getBaseUrl().'inpay/standard/cancel',						
						'buyer_email'		=> $_totalData['customer_email'],
						'buyer_address'		=> implode(",",$this->getOrder()->getBillingAddress()->getStreet()),
						'buyer_name'		=> $this->getOrder()->getBillingAddress()->getName(),
						'country' 			=> $this->getOrder()->getBillingAddress()->getCountry()
					);	
					
		$checksum = htmlentities(Mage::helper('inpay')->generateinpayCheckSum($params, $secret_key));
		$params['checksum'] = $checksum;		
		return $params;
    }	
}