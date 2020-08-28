<?php


namespace AbstractPaymentPrestashop\Controllers;


use AbstractPaymentPrestashop\Exceptions\AbstractPaymentException;
use Exception;

abstract class ConfirmController extends AbstractPaymentController
{
    protected $transactionIdParam = 'transactionId';

    public function initContent()
    {
        parent::initContent();
        $transaction = $this->verifyStatus($this->transactionStatus->getRequested());
        $order = $this->getOrder();
        $currency = $this->getOrderCurrency($order);
        $amount = $this->orderService->getAmount($order, $currency);
        try {
            $amountConfirm = $this->paymentProxy->confirm($transaction, $order, $currency, $amount);
        }catch (AbstractPaymentException $e) {
            $this->transactionService->changeStatus($transaction, $this->transactionStatus->getFailure());
            $this->orderService->changeStatus($order, $this->orderStatus->getPaymentError());
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->l('The payment failed')
            ], true));
        }
        if(((float) $amount) === $amountConfirm) {
            $this->transactionService->changeStatus($transaction, $this->transactionStatus->getConfirmed());
            $this->orderService->changeStatus($order, $this->orderStatus->getPaymentAccepted());
            $redirectInfos = $this->orderService->createRedirectInfos($order);
            Tools::redirect(Context::getContext()->link->getPageLink('order-confirmation', true, null, $redirectInfos));
        }

        try {
            $this->refund($transaction, $order);
        } catch (AbstractPaymentException $e) {
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->l('The payment failed')
            ], true));
        }
        Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
            'message' => $this->module->l('The payment failed')
        ], true));
    }

    protected function refund($transaction, $order){
        $transactionId = Tools::getValue($this->transactionIdParam, false);
        $currency = $this->getOrderCurrency($order);
        $amount = $this->orderService->getAmount($order, $currency);
        $shipping = $this->orderService->getShippingAmount($order, $currency);
        if(! $this->transactionService->canRefund($transaction, $amount))
            throw new AbstractPaymentException('Can\'t refund');
        $result = $this->paymentProxy->refund($transactionId, $amount);
        $this->transactionService->addRefundInfos($transaction, $result, $amount, $shipping);
        if($this->transactionService->isTotallyRefund($transaction))
            $this->orderService->changeStatus($order, $this->orderStatus->getPaymentError());
    }
}