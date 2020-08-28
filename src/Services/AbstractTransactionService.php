<?php


namespace AbstractPaymentPrestashop\Services;


use AbstractPaymentPrestashop\Models\AbstractTransaction;

abstract class AbstractTransactionService extends AbstractService
{

    public function restoreCart(bool $transaction): bool
    {
        $trxState = $transaction->trx_state;
        $result = ! ($trxState == $this->transactionStatus->getRequested() || $trxState == $this->transactionStatus->getFailure() || $trxState == $this->transactionStatus->getCanceled());
        return !(empty ($transaction) || $result);
    }

    public function getTransaction(bool $orderId): AbstractTransaction
    {
        return AbstractTransaction::getLatestByOrderId($orderId);
    }

    public function verifyTransaction(AbstractTransaction $transaction, $status): bool
    {
        return $transaction->trx_state === $status;
    }

    public abstract function createTransaction($order, $currency, $amount, $shipping, $idTransaction);

    public function changeStatus(AbstractTransaction $transaction, string $status)
    {
        $transaction->trx_state = $status;
        $transaction->update();
    }

    public abstract function addRefundInfos($transaction, $result, $amount, $shipping);

    public abstract function isTotallyRefund($transaction);

    public function canRefund($transaction, $amount)
    {
        $amount = (float) $amount;
        return ($amount > 0 && $amount > $transaction->getRemainingAmount());
    }
}