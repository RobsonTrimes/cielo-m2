<?php

namespace Az2009\Cielo\Model\Method\Cc\Response;

class Payment extends \Az2009\Cielo\Model\Method\Response
{

    const STATUS_AUTHORIZED = '1';

    const STATUS_CAPTURED = '2';

    const STATUS_PENDING = '12';

    const STATUS_PAYMENT_REVIEW = '0';

    /**
     * @var \Az2009\Cielo\Model\Method\Cc\Transaction\Authorize
     */
    protected $_authorize;

    /**
     * @var \Az2009\Cielo\Model\Method\Cc\Transaction\Capture
     */
    protected $_capture;

    /**
     * @var \Az2009\Cielo\Model\Method\Cc\Transaction\General
     */
    protected $_default;


    public function __construct(
        \Az2009\Cielo\Model\Method\Cc\Transaction\Authorize $authorize,
        \Az2009\Cielo\Model\Method\Cc\Transaction\Capture $capture,
        \Az2009\Cielo\Model\Method\Cc\Transaction\DefaultTransaction $default,
        array $data = []
    ) {
        $this->_authorize = $authorize;
        $this->_capture = $capture;
        $this->_default = $default;

        parent::__construct($data);
    }

    public function process()
    {
        parent::process();

        switch ($this->getStatus()) {
            case Payment::STATUS_AUTHORIZED:
                $this->_authorize
                     ->setPayment($this->getPayment())
                     ->setResponse($this->getResponse())
                     ->process();
            break;
            case Payment::STATUS_CAPTURED:
                $r = '';
                $this->_capture
                     ->setPayment($this->getPayment())
                     ->setResponse($this->getResponse())
                     ->process();
            break;
        }
    }

    /**
     * get status payment
     * @return mixed
     * @throws \Exception
     */
    public function getStatus()
    {
        $body = $this->getBody();
        if (property_exists($body, 'Payment')) {
            return $body->Payment->Status;
        } elseif (property_exists($body, 'Status')) {
            return $body->Status;
        }

        throw new \Exception('invalid payment status');
    }

}