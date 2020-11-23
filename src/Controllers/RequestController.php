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

use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Exceptions\AbstractPaymentException;
use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Models\AbstractTransaction;
use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Proxies\Contracts\PaymentProxyInterface;
use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Services\AbstractOrderService;
use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Services\AbstractTransactionService;
use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Status\Contracts\OrderStatusInterface;
use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Status\Contracts\TransactionStatusInterface;
use PrestaShop\PrestaShop\Adapter\Entity\Configuration;
use PrestaShop\PrestaShop\Adapter\Entity\Context;
use PrestaShop\PrestaShop\Adapter\Entity\Tools;

abstract class RequestController extends AbstractPaymentController
{
    public function callInitContent(AbstractTransaction $transaction, AbstractTransactionService $transactionService, AbstractOrderService $orderService, PaymentProxyInterface $paymentProxy, OrderStatusInterface $orderStatus, TransactionStatusInterface $transactionStatus)
    {
        parent::callInitContent($transaction, $transactionService, $orderService, $paymentProxy, $orderStatus, $transactionStatus);
        $cart = Context::getContext()->cart;
        try {
            $order = $this->orderService->createOrder($cart);
        } catch (AbstractPaymentException $e) {
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->trans("The cart is empty", [], "Modules.{$this->module->getModuleTranslationDomain()}.Error"),
            ], true));
        }
        $currency = $this->getOrderCurrency($order);
        $amount = $this->orderService->getAmount($order, $currency);
        try {
            $answer = $this->paymentProxy->request($order, $amount, $currency);
        } catch (AbstractPaymentException $exception) {
            $this->orderService->changeStatus($order, Configuration::get($this->orderStatus->getPaymentError()));
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->trans("The payment failed", [], "Modules.{$this->module->getModuleTranslationDomain()}.Error"),
            ], true));
        }
        $amount = $this->orderService->getAmount($order, $currency);
        $shipping = $this->orderService->getShippingAmount($order, $currency);
        if (!empty($answer)) {
            $this->transactionService->createTransaction($order, $currency, $amount, $shipping, $answer->getIdTransaction(), $answer->getCustomFields());
            Tools::redirect($answer->getUrl());
        }
        Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [], true));
    }
}
