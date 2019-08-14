<?php
class Inpay_Payment_Model_Inpay extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'inpay';	
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = false;
	protected $_canUseForMultishipping  = false;
	protected $_formBlockType = 'inpay/inpay';
	
	public function getOrderPlaceRedirectUrl()
	{
		return Mage::getUrl('inpay/standard/redirect', array('_secure' => true));
	}
	
	protected function _getFieldRenderer()
    {
        if (empty($this->_fieldRenderer)) {
            $this->_fieldRenderer = "sdfsdf";
        }
        return $this->_fieldRenderer;
    }
}
?>