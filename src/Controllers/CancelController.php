<?php


namespace AbstractPaymentPrestashop\Controllers;

use AbstractPaymentPrestashop\Proxies\Contracts\PaymentProxyInterface;
use AbstractPaymentPrestashop\Services\AbstractOrderService;
use AbstractPaymentPrestashop\Services\AbstractTransactionService;
use AbstractPaymentPrestashop\Status\Contracts\OrderStatusInterface;
use AbstractPaymentPrestashop\Status\Contracts\TransactionStatusInterface;
use PrestaShop\PrestaShop\Adapter\Entity\Tools;
use PrestaShop\PrestaShop\Adapter\Entity\Context;

abstract class CancelController extends AbstractPaymentController
{
    public function callInitContent(AbstractTransactionService $transactionService, AbstractOrderService $orderService, PaymentProxyInterface $paymentProxy, OrderStatusInterface $orderStatus, TransactionStatusInterface $transactionStatus)
    {
        parent::callInitContent($transactionService, $orderService, $paymentProxy, $orderStatus, $transactionStatus);
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