<?php


namespace AbstractPaymentPrestashop\Services;


use AbstractPaymentPrestashop\Currencies\AbstractCurrency;
use AbstractPaymentPrestashop\Status\Contracts\OrderStatusInterface;
use AbstractPaymentPrestashop\Status\Contracts\TransactionStatusInterface;
use PrestaShop\PrestaShop\Adapter\Entity\PaymentModule;

abstract class AbstractService
{
    /**
     * @var PaymentModule
     */
    protected $module;
    /**
     * @var OrderStatusInterface
     */
    protected $orderStatus;
    /**
     * @var TransactionStatusInterface
     */
    protected $transactionStatus;

    /**
     * @var AbstractCurrency
     */
    protected $currency;

    /**
     * AbstractService constructor.
     * @param PaymentModule $module
     * @param OrderStatusInterface $orderStatus
     * @param TransactionStatusInterface $transactionStatus
     * @param AbstractCurrency $currency
     */
    public function __construct(PaymentModule $module, OrderStatusInterface $orderStatus, TransactionStatusInterface $transactionStatus, AbstractCurrency $currency)
    {
        $this->module = $module;
        $this->orderStatus = $orderStatus;
        $this->transactionStatus = $transactionStatus;
        $this->currency = $currency;
    }

}