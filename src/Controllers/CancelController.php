<?php


namespace AbstractPaymentPrestashop\Controllers;


abstract class CancelController extends AbstractPaymentController
{
    public function initContent()
    {
        parent::initContent();
        $transaction = $this->verifyStatus($this->transactionStatus->getRequested());
        $order = $this->getOrder();
        $this->transactionService->changeStatus($transaction,$this->transactionStatus->getCanceled());
        $this->orderService->changeStatus($order, $this->orderStatus->getCanceled());
        $this->restoreCart([
            'transaction' => $transaction,
        ]);
        Tools::redirectLink(Context::getContext()->link->getPageLink('order', true, null, ['step' => '1']));
    }
}