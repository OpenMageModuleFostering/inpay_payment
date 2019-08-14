<?php
class Inpay_Payment_Block_Inpay extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('inpay/inpay.phtml');
    }
}