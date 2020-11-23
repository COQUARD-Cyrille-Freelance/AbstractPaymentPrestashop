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
use Exception;
use PrestaShop\PrestaShop\Adapter\Entity\Context;
use PrestaShop\PrestaShop\Adapter\Entity\Module;
use PrestaShop\PrestaShop\Adapter\Entity\ModuleFrontController;
use PrestaShop\PrestaShop\Adapter\Entity\Tools;

abstract class AbstractPaymentController extends ModuleFrontController
{
    protected $orderIdParam = 'orderId';
    protected $transactionService;
    protected $orderService;
    protected $paymentProxy;
    protected $orderStatus;
    protected $transactionStatus;
    protected $transaction;

    protected function getModule(): Module {
        return $this->module;
    }

    public function callInitContent(AbstractTransaction $transaction, AbstractTransactionService $transactionService, AbstractOrderService $orderService, PaymentProxyInterface $paymentProxy, OrderStatusInterface $orderStatus, TransactionStatusInterface $transactionStatus)
    {
        parent::initContent();
        $this->transaction = $transaction;
        $this->transactionService = $transactionService;
        $this->orderService = $orderService;
        $this->paymentProxy = $paymentProxy;
        $this->orderStatus = $orderStatus;
        $this->transactionStatus = $transactionStatus;

        if (!$this->isActive()) {
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->trans("Unable to use %name% plugin.", ['%name%' => $this->module->name], "Modules.{$this->module->getModuleTranslationDomain()}.Settings"),
            ], true));
        }
    }

    protected function isActive()
    {
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == $this->module->name) {
                return (int) $module['id_module'] == (int) $this->module->id;
            }
        }

        return false;
    }

    protected function restoreCart($args, $force = false)
    {
        $orderId = isset($args['orderId']) ? $args['orderId'] : false;
        $transaction = isset($args['transaction']) ? $args['transaction'] : false;

        if ($orderId && $force) {
            return $this->orderService->restoreCart($orderId);
        }

        if (empty($transaction)) {
            if (!empty($orderId)) {
                $transaction = $this->transactionService->getTransaction($orderId);
            } else {
                return false;
            }
        }

        $result = $this->transactionService->restoreCart($transaction);
        if (!$result) {
            return false;
        }

        return $this->orderService->restoreCart($transaction->order_id);
    }

    protected function verifyStatus($status): AbstractTransaction
    {
        $orderId = $this->getOrderID();
        $transaction = $this->transactionService->getTransaction($orderId);
        if (!$this->transactionService->verifyTransaction($transaction, $status)) {
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->trans("Invalid transaction", [], "Modules.{$this->module->getModuleTranslationDomain()}.Error"),
            ], true));
        }

        return $transaction;
    }

    public function getOrderID()
    {
        return Tools::getValue($this->orderIdParam, false);
    }

    protected function getOrder()
    {
        $orderId = $this->getOrderID();
        try {
            $order = $this->orderService->getOrder($orderId);
        } catch (Exception $e) {
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->trans("Can't find the order", [], "Modules.{$this->module->getModuleTranslationDomain()}.Error"),
            ], true));
        }

        return $order;
    }

    protected function getOrderCurrency($order)
    {
        try {
            $currency = $this->orderService->getOrderCurrency($order);
        } catch (AbstractPaymentException $e) {
            Tools::redirect(Context::getContext()->link->getModuleLink($this->module->name, 'error', [
                'message' => $this->module->trans("The currency is not supported by %name%.", ['%name%' => $this->module->name], "Modules.{$this->module->getModuleTranslationDomain()}.Error"),
            ], true));
        }

        return $currency;
    }
}
