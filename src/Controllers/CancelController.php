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

namespace CoquardCyrilleFreelance\AbstractPaymentPrestashop\Controllers;

use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Models\AbstractTransaction;
use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Proxies\Contracts\PaymentProxyInterface;
use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Services\AbstractOrderService;
use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Services\AbstractTransactionService;
use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Status\Contracts\OrderStatusInterface;
use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Status\Contracts\TransactionStatusInterface;
use PrestaShop\PrestaShop\Adapter\Entity\Context;
use PrestaShop\PrestaShop\Adapter\Entity\Tools;

abstract class CancelController extends AbstractPaymentController
{
    public function callInitContent(AbstractTransaction $transaction, AbstractTransactionService $transactionService, AbstractOrderService $orderService, PaymentProxyInterface $paymentProxy, OrderStatusInterface $orderStatus, TransactionStatusInterface $transactionStatus)
    {
        parent::callInitContent($transaction, $transactionService, $orderService, $paymentProxy, $orderStatus, $transactionStatus);
        $transaction = $this->verifyStatus($this->transactionStatus->getRequested());
        $order = $this->getOrder();
        $this->transactionService->changeStatus($transaction, $this->transactionStatus->getCanceled());
        $this->orderService->changeStatus($order, $this->orderStatus->getCanceled());
        $this->restoreCart([
            'transaction' => $transaction,
        ]);
        Tools::redirectLink(Context::getContext()->link->getPageLink('order', true, null, ['step' => '1']));
    }
}
