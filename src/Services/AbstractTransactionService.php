<?php


namespace AbstractPaymentPrestashop\Services;


use AbstractPaymentPrestashop\Models\AbstractTransaction;

abstract class AbstractTransactionService extends AbstractService
{

    /**
     * Restore the cart to its old state before the transaction
     * @param bool $transaction transaction to revert
     * @return bool success from the revert
     */
    public function restoreCart(bool $transaction): bool
    {
        $trxState = $transaction->trx_state;
        $result = ! ($trxState == $this->transactionStatus->getRequested() || $trxState == $this->transactionStatus->getFailure() || $trxState == $this->transactionStatus->getCanceled());
        return !(empty ($transaction) || $result);
    }

    /**
     * Get the transaction linked to the order id
     * @param int $orderId order id from the transaction
     * @return AbstractTransaction transaction found
     */
    public function getTransaction(int $orderId): AbstractTransaction
    {
        return $this->transaction->getLatestByOrderId($orderId);
    }

    public function verifyTransaction(AbstractTransaction $transaction, $status): bool
    {
        return $transaction->trx_state === $status;
    }

    public abstract function createTransaction($order, $currency, $amount, $shipping, $idTransaction, array $customFields = []);

    public function changeStatus(AbstractTransaction $transaction, string $status)
    {
        $transaction->trx_state = $status;
        $transaction->update();
    }

    public abstract function addRefundInfos($transaction, $amount, array $custom = []);

    public abstract function isTotallyRefund($transaction);

    public function canRefund($transaction, $amount)
    {
        $amount = (float) $amount;
        return ($amount > 0 && $amount > $transaction->getRemainingAmount());
    }
}