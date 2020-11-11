<?php


namespace AbstractPaymentPrestashop\Controllers;

use AbstractPaymentPrestashop\Proxies\Contracts\PaymentProxyInterface;
use AbstractPaymentPrestashop\Services\AbstractOrderService;
use AbstractPaymentPrestashop\Services\AbstractTransactionService;
use AbstractPaymentPrestashop\Status\Contracts\OrderStatusInterface;
use AbstractPaymentPrestashop\Status\Contracts\TransactionStatusInterface;
use PrestaShop\PrestaShop\Adapter\Entity\Tools;
use PrestaShop\PrestaShop\Adapter\Entity\Context;
use PrestaShop\PrestaShop\Adapter\Entity\Configuration;
use AbstractPaymentPrestashop\Exceptions\AbstractPaymentException;

abstract class RequestController extends AbstractPaymentController
{
    public function callInitContent(AbstractTransactionService $transactionService, AbstractOrderService $orderService, PaymentProxyInterface $paymentProxy, OrderStatusInterface $orderStatus, TransactionStatusInterface $transactionStatus)
    {
        parent::callInitContent($transactionService, $orderService, $paymentProxy, $orderStatus, $transactionStatus);
        $cart = Context::getContext()->cart;
        try {
            $order = $this->orderService->createOrder($cart);
        } catch (AbstractPaymentException $e) {
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->l('The cart is empty')
            ], true));
        }
        $currency = $this->getOrderCurrency($order);
        $amount = $this->orderService->getAmount($order, $currency);
        try{
            $answer = $this->paymentProxy->request($order, $amount, $currency);
        } catch (AbstractPaymentException $exception) {
            $this->orderService->changeStatus($order, Configuration::get($this->orderStatus->getPaymentError()));
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->l('The payment failed')
            ], true));
        }
        $amount = $this->orderService->getAmount($order, $currency);
        $shipping = $this->orderService->getShippingAmount($order, $currency);
        if (!empty($answer)) {
            $this->transactionService->createTransaction($order, $currency, $amount, $shipping, $answer->getIdTransaction(), $answer->getCustomFields());
            Tools::redirect($answer->getUrl());
        }
        Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', array(), true));
    }
}