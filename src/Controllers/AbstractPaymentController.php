<?php


namespace AbstractPaymentPrestashop\Controllers;

use AbstractPaymentPrestashop\Exceptions\AbstractPaymentException;
use AbstractPaymentPrestashop\Models\AbstractTransaction;
use AbstractPaymentPrestashop\Proxies\Contracts\PaymentProxyInterface;
use AbstractPaymentPrestashop\Services\AbstractOrderService;
use AbstractPaymentPrestashop\Services\AbstractTransactionService;
use AbstractPaymentPrestashop\Status\Contracts\OrderStatusInterface;
use AbstractPaymentPrestashop\Status\Contracts\TransactionStatusInterface;
use Exception;
use PrestaShop\PrestaShop\Adapter\Entity\ModuleFrontController;
use PrestaShop\PrestaShop\Adapter\Entity\Tools;
use PrestaShop\PrestaShop\Adapter\Entity\Context;
use PrestaShop\PrestaShop\Adapter\Entity\Module;

abstract class AbstractPaymentController  extends ModuleFrontController
{
    protected $orderIdParam = 'orderId';
    protected $transactionService;
    protected $orderService;
    protected $paymentProxy;
    protected $orderStatus;
    protected $transactionStatus;
    protected $transaction;
    public function callInitContent(AbstractTransaction $transaction, AbstractTransactionService $transactionService, AbstractOrderService $orderService, PaymentProxyInterface $paymentProxy, OrderStatusInterface $orderStatus, TransactionStatusInterface $transactionStatus) {
        parent::initContent();
        $this->transaction = $transaction;
        $this->transactionService = $transactionService;
        $this->orderService = $orderService;
        $this->paymentProxy = $paymentProxy;
        $this->orderStatus = $orderStatus;
        $this->transactionStatus = $transactionStatus;

        if (! $this->isActive()) {
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->l("Unable to use {$this->module->name} plugin."),
            ], true));
        }
    }

    protected function isActive(){
        foreach (Module::getPaymentModules() as $module)
        {
            if ($module['name'] == $this->module->name)
            {
                return ((int) $module['id_module'] == (int) $this->module->id);
            }
        }
        return false;
    }

    protected function restoreCart($args, $force = false) {
        $orderId = isset($args['orderId'])?$args['orderId']:false;
        $transaction = isset($args['transaction'])?$args['transaction']:false;

        if ($orderId && $force)
            return $this->orderService->restoreCart($orderId);

        if (empty ($transaction))
            if (! empty ($orderId))
                $transaction = $this->transactionService->getTransaction($orderId);
            else
                return false;

        $result = $this->transactionService->restoreCart($transaction);
        if(! $result)
            return false;
        return $this->orderService->restoreCart($transaction->order_id);
    }

    protected function verifyStatus($status): AbstractTransaction {
        $orderId = Tools::getValue($this->orderIdParam, false);
        $transaction = $this->transactionService->getTransaction($orderId);
        if(!$this->transactionService->verifyTransaction($transaction, $status))
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->l('Invalid transaction')
            ], true));
        return $transaction;
    }

    protected function getOrder() {
        $orderId = Tools::getValue($this->orderIdParam, false);
        try {
            $order = $this->orderService->getOrder($orderId);
        } catch (Exception $e) {
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->l('Cant\'t find the order')
            ], true));
        }
        return $order;
    }

    protected function getOrderCurrency($order) {
        try {
            $currency = $this->orderService->getOrderCurrency($order);
        } catch (AbstractPaymentException $e) {
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->l("The currency is not supported by {$this->module->name}.")
            ], true));
        }
        return $currency;
    }
}