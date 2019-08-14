<?php
class Inpay_Payment_Helper_Data extends Mage_Core_Helper_Abstract
{	
	public function getPendingPaymentStatus()
    {
        if (version_compare(Mage::getVersion(), '1.4.0', '<')) {
            return Mage_Sales_Model_Order::STATE_HOLDED;
        }
        return Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
    }
	
	public function http_build_query($data,$prefix=null,$sep='',$key='') 
	{
		$ret = array();
		foreach((array)$data as $k => $v) {
			$k = urlencode($k);
			
			if(is_int($k) && $prefix != null) {
				$k = $prefix.$k;
			};
			
			if(!empty($key)) {
				$k = $key."[".$k."]";
			};

			if(is_array($v) || is_object($v)) {
				array_push($ret,http_build_query($v,"",$sep,$k));
			}
			else {
				array_push($ret,$k."=".urlencode($v));
			};
		};

        if(empty($sep)) {
            $sep = ini_get("arg_separator.output");
        };
        return implode($sep, $ret);
    }
	
	public function generateinpayCheckSum($params, $secret_key) 
	{
		if(!function_exists('http_build_query')) 
		{			
			$params['secret_key'] = $secret_key;  
			ksort($params);
			return strtolower(md5($this->http_build_query($params, '' , "&")));
		}
		else
		{			
			$params['secret_key'] = $secret_key;
			ksort($params);
			return strtolower(md5(http_build_query($params, '' , "&")));
		}
	}
	
	public function verifychecksum($params, $secret_key)
	{
		$systemCheckSum = $params['checksum'];
		if(function_exists('http_build_query')) 
		{
			$params = array(
						  "api_version" => $params["api_version"],
						  "bank_owner_name" => $params["bank_owner_name"],
						  "order_id" => $params["order_id"],
						  "invoice_reference" => $params["invoice_reference"],
						  "invoice_amount" => $params["invoice_amount"],
						  "invoice_currency" => $params["invoice_currency"],
						  "invoice_updated_at" => $params["invoice_updated_at"],
						  "invoice_status" => $params["invoice_status"],
						  "merchant_id" => Mage::getStoreConfig('payment/inpay/merchant_id',Mage::app()->getStore()->getStoreId()),
						  "received_sum" => $params["received_sum"],
						  "secret_key" => $secret_key
						  );
			
			ksort($params);
			$checksum = md5(http_build_query($params, '', '&'));	
		}
		else
		{			
			$params = array(
						  "api_version" => $params["api_version"],
						  "bank_owner_name" => $params["bank_owner_name"],
						  "order_id" => $params["order_id"],
						  "invoice_reference" => $params["invoice_reference"],
						  "invoice_amount" => $params["invoice_amount"],
						  "invoice_currency" => $params["invoice_currency"],
						  "invoice_updated_at" => $params["invoice_updated_at"],
						  "invoice_status" => $params["invoice_status"],
						  "merchant_id" => Mage::getStoreConfig('payment/inpay/merchant_id',Mage::app()->getStore()->getStoreId()),
						  "received_sum" => $params["received_sum"],
						  "secret_key" => $secret_key
						  );
			
			ksort($params);
			$checksum = md5($this->http_build_query($params, '', '&'));
		}		
		return ($systemCheckSum == $checksum);		
	}
}