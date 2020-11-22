<?php
/**
* Copyright 2020 COQUARD Cyrille
*
* Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
*
* 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
*
* 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
*
* 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace CoquardCyrilleFreelance\AbstractPaymentPrestashop\Services;

use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Models\AbstractTransaction;

abstract class AbstractTransactionService extends AbstractService
{
    /**
     * Restore the cart to its old state before the transaction
     *
     * @param bool $transaction transaction to revert
     *
     * @return bool success from the revert
     */
    public function restoreCart(bool $transaction): bool
    {
        $trxState = $transaction->trx_state;
        $result = !($trxState == $this->transactionStatus->getRequested() || $trxState == $this->transactionStatus->getFailure() || $trxState == $this->transactionStatus->getCanceled());

        return !(empty($transaction) || $result);
    }

    /**
     * Get the transaction linked to the order id
     *
     * @param int $orderId order id from the transaction
     *
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

    abstract public function createTransaction($order, $currency, $amount, $shipping, $idTransaction, array $customFields = []);

    public function changeStatus(AbstractTransaction $transaction, string $status)
    {
        $transaction->trx_state = $status;
        $transaction->update();
    }

    abstract public function addRefundInfos($transaction, $amount, array $custom = []);

    abstract public function isTotallyRefund($transaction);

    public function canRefund($transaction, $amount)
    {
        $amount = (float) $amount;

        return $amount > 0 && $amount > $transaction->getRemainingAmount();
    }
}
