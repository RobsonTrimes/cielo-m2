<?php

namespace Az2009\Cielo\Helper;

use Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\Collection;
use MEQP2\Tests\NamingConventions\true\object;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    const CHANGE_TYPE = 1;

    const CARD_TOKEN = 'new';

    const ID_DENY = 'deny_payment';

    const ID_ACCEPT = 'accept_payment';

    /**
     * @var \Magento\Framework\View\Asset\Repository
     */
    protected $_asset;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $_orderCollection;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var \Magento\Customer\Model\Session
     */
    public $_session;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory
     */
    protected $_transaction;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    protected $_item = null;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollection,
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory $transaction,
        \Magento\Customer\Model\Session $session,
        \Magento\Framework\View\Asset\Repository $asset,
        \Magento\Framework\ObjectManagerInterface $objectManager
    ) {
        $this->_asset = $asset;
        $this->_orderCollection = $orderCollection;
        $this->_order = $order;
        $this->_session = $session;
        $this->_transaction = $transaction;
        $this->_objectManager = $objectManager;

        parent::__construct($context);
    }

    public function getMerchantId()
    {
        $config = $this->scopeConfig->getValue(
            'payment/az2009_cielo_core/merchant_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $config;
    }

    public function getMerchantKey()
    {
        $config = $this->scopeConfig->getValue(
            'payment/az2009_cielo_core/merchant_key',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $config;
    }

    public function getMode()
    {
        $config = $this->scopeConfig->getValue(
            'payment/az2009_cielo_core/mode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $config;
    }

    public function getUriRequest()
    {
        $config = $this->scopeConfig->getValue(
            'payment/az2009_cielo_core/uri_request_production'
        );

        if ($this->getMode() == \Az2009\Cielo\Model\Source\Mode::MODE_STAGE) {
            $config = $this->scopeConfig->getValue(
                'payment/az2009_cielo_core/uri_request_stage'
            );
        }

        return (string)$config;
    }

    public function getUriQuery()
    {
        $config = $this->scopeConfig->getValue(
            'payment/az2009_cielo_core/uri_query_production'
        );

        if ($this->getMode() == \Az2009\Cielo\Model\Source\Mode::MODE_STAGE) {
            $config = $this->scopeConfig->getValue(
                'payment/az2009_cielo_core/uri_query_stage'
            );
        }

        return (string)$config;
    }

    public function getCardTypesAvailable()
    {
        $config = $this->scopeConfig->getValue(
            'payment/az2009_cielo/cctypes',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $config = explode(',', $config);

        return $config;
    }

    public function getKeyRequest()
    {
        $key = urlencode(mt_rand(0, 999) .
               mt_rand(1000, 1999) .
               time() .
               $_SERVER['SERVER_ADDR']);

        return $key;
    }

    /**
     * remove placeholders of uri
     * @param $uri
     * @return mixed
     */
    public function sanitizeUri($uri)
    {
        $uri = str_replace('//', '/', $uri);
        $uri = str_replace(':/', '://', $uri);
        $uri = str_replace(
            [
                '-capture',
                '-refund',
                '-order',
                '-offline',
                '-authorize'
            ],
            '',
            $uri
        );

        return $uri;
    }

    /**
     * get cards saved by customerId
     * @param $customerId int
     * @return array
     */
    public function getCardSavedByCustomer($customerId = null)
    {
        $tokens = [];

        if (is_null($customerId) && $this->_session->isLoggedIn()) {
            $customerId = $this->_session->getCustomerId();
        }

        if (empty($customerId)) {
            return $tokens;
        }

        $collection = $this->_orderCollection->create();
        $collection->addAttributeToSelect('entity_id');
        $collection->addAttributeToFilter(
            'customer_id',
            array(
                'eq' => $customerId
            )
        );
        $collection->getSelect()
            ->join(
                array('sop' => $collection->getTable('sales_order_payment')),
                'main_table.entity_id = sop.parent_id AND sop.card_token IS NOT NULL',
                []
            )
            ->group('sop.card_token');


        foreach ($collection as $order) {
            $tokens[$order->getPayment()->getData('card_token')] = [
                'brand' => $order->getPayment()->getAdditionalInformation('cc_type'),
                'last_four' => $this->getCardLabel($order->getPayment()),
                'month_due' => $order->getPayment()->getAdditionalInformation('cc_exp_month'),
                'year_due' => $order->getPayment()->getAdditionalInformation('cc_exp_year'),
                'cardholder' => $order->getPayment()->getAdditionalInformation('cc_name'),
            ];
        }

        return $tokens;
    }

    public function getCardLabel(\Magento\Sales\Model\Order\Payment\Interceptor $payment)
    {
        $firstFour = substr($payment->getAdditionalInformation('cc_number') ?: $payment->getAdditionalInformation('cc_number_enc'),0, 4);
        $lastFour = substr($payment->getAdditionalInformation('cc_number') ?: $payment->getAdditionalInformation('cc_number_enc'), -4);

        return $firstFour. ' ****  **** '.$lastFour;
    }

    /**
     * sanitize string
     * @param $value
     * @param $maxlength
     * @param null $init
     * @return bool|string
     */
    public function prepareString($value, $maxlength, $init = null)
    {
        if (!is_null($init)) {
            return substr(trim($value), (int)$init, $maxlength);
        }

        return substr(trim($value), $maxlength);
    }

    public function canDebug()
    {
        $config = $this->scopeConfig->getValue(
            'payment/az2009_cielo_core/debug',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return (boolean)$config;
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    /**
     * get instance postback of payment
     * @param $transactionId
     * @return bool | object
     */
    public function getPostbackByTransId($transactionId)
    {
        $instance = false;
        $collection = $this->_transaction->create();

        $collection->addAttributeToSelect('order_id')
                   ->addAttributeToFilter('txn_id',
                       array(
                           array('eq' => $transactionId),
                           array('eq' => $transactionId . '-order'),
                       )
                   );

        if ($collection->getSize() <= 0) {
            return $instance;
        }

        $orderId = $collection->getFirstItem()
                              ->getOrderId();

        if ((int)$orderId && (int)$this->_order->load($orderId)->getId()) {
            $instance = $this->_order
                             ->getPayment()
                             ->getMethodInstance()
                             ->getPostbackInstance();
        }

        if ($instance !== null) {
            $instance = $this->_objectManager->get($instance);
        }

        return $instance;
    }

    public function getCardDataByToken($token)
    {
        if ($this->_item !== null
            && $this->_item->getData('card_token') == $token
        ) {
            return $this->_item;
        }

        /** @var \Magento\Sales\Model\Order\Payment $payment*/
        $payment = $this->_objectManager->get(\Magento\Sales\Model\Order\Payment::class);
        $this->_item = $payment->load($token, 'card_token');

        if ($this->_item->getId()) {
            return $this->_item;
        }

        return false;
    }

    /**
     * @return \Magento\Framework\Message\ManagerInterface
     */
    public function getMessage()
    {
        return $this->_objectManager->get(\Magento\Framework\Message\ManagerInterface::class);
    }

    public function isOrderAndPayment($order)
    {
        if ($order instanceof \Magento\Sales\Model\Order
            && in_array($order->getPayment()->getMethod(), $this->getCodesPayment())
            && $order->isPaymentReview()
        ) {
            return true;
        }

        return false;
    }

    public function getCodesPayment()
    {
        return [
            \Az2009\Cielo\Model\Method\Cc\Cc::CODE_PAYMENT,
            \Az2009\Cielo\Model\Method\BankSlip\BankSlip::CODE_PAYMENT
        ];
    }
}