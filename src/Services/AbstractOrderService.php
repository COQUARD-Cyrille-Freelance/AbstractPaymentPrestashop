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

use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Exceptions\AbstractPaymentException;
use Cart;
use Currency;
use Exception;
use PrestaShop\PrestaShop\Adapter\Entity\CartRule;
use PrestaShop\PrestaShop\Adapter\Entity\Configuration;
use PrestaShop\PrestaShop\Adapter\Entity\Context;
use PrestaShop\PrestaShop\Adapter\Entity\Customer;
use PrestaShop\PrestaShop\Adapter\Entity\Order;
use PrestaShop\PrestaShop\Adapter\Entity\OrderHistory;
use PrestaShop\PrestaShop\Adapter\Entity\StockAvailable;
use PrestaShop\PrestaShop\Adapter\Entity\Validate;

abstract class AbstractOrderService extends AbstractService
{
    public function restoreCart($orderId): bool
    {
        try {
            if (is_string($orderId)) {
                $orderId = (int) $orderId;
            }

            $context = Context::getContext();

            $cartId = Order::getCartIdStatic($orderId, $context->customer->id);
            $oldCart = new Cart($cartId);
            $duplication = $oldCart->duplicate();
            $newCart = $duplication['cart'];

            if (!$duplication || !Validate::isLoadedObject($newCart) || !$duplication['success']) {
                throw new AbstractPaymentException();
            }

            $context->cookie->id_cart = $newCart->id;
            $context->cart = $newCart;
            CartRule::autoAddToCart($context);
            $context->cookie->write();

            return true;
        } catch (AbstractPaymentException $e) {
            return false;
        }
    }

    public function getAmount($order, $currency)
    {
        $amount = $order->total_paid_tax_incl;

        return $this->currency->standardizeAmount($amount, $currency);
    }

    /**
     * @param Order $order
     * @param string $status
     *
     * @return void
     */
    public function changeStatus(Order $order, string $status): void
    {
        $orderStateId = Configuration::get($status);
        try {
            $useExistingsPayment = false;
            if (!$order->hasInvoice()) {
                $useExistingsPayment = true;
            }

            $curOrderState = $order->getCurrentOrderState();
            $curOrderStateId = $curOrderState->id;

            if ($curOrderStateId === $orderStateId) {
                return;
            }

            $history = new OrderHistory();
            $history->id_order = $order->id;

            $history->changeIdOrderState($orderStateId, $order, $useExistingsPayment);

            $result = (bool) $history->addWithemail(true);

            if ($result && Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                foreach ($order->getProducts() as $product) {
                    if (StockAvailable::dependsOnStock($product['product_id'])) {
                        StockAvailable::synchronize($product['product_id'], (int) $product['id_shop']);
                    }
                }
            }

            return;
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * Return the shipping amount of the order
     *
     * @param Order $order
     * @param string $currency
     *
     * @return float
     *
     * @throws AbstractPaymentException
     */
    public function getShippingAmount($order, $currency)
    {
        $shippingAmount = ($order->getTaxCalculationMethod() == PS_TAX_INC) ? $order->total_shipping_tax_incl : $order->total_shipping_tax_excl;

        return $this->currency->standardizeAmount($shippingAmount, $currency);
    }

    /**
     * Create an order from the cart
     *
     * @param Cart $cart
     *
     * @return Order
     *
     * @throws AbstractPaymentException
     */
    public function createOrder(Cart $cart): Order
    {
        if (!$cart instanceof Cart || empty($cart->id)) {
            throw new AbstractPaymentException('Unable to find the cart info.');
        }

        $addrDeliveryId = $cart->id_address_delivery;
        $addrInvoiceId = $cart->id_address_invoice;
        if ($cart->id_address_delivery === 0 || $addrInvoiceId === 0) {
            throw new AbstractPaymentException('Unable to find the address info.', sprintf('[addr delivery id: %d][addr invoice id: %d] - {dp-msg}', $addrDeliveryId, $addrInvoiceId));
        }

        $customerId = $cart->id_customer;
        $customer = new Customer($customerId);
        if (!Validate::isLoadedObject($customer)) {
            throw new AbstractPaymentException('Unable to find the customer\'s info.', sprintf('[customer id: %d] - {dp-msg}', $customerId));
        }

        $currency = Context::getContext()->currency;
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $this->module->validateOrder(
            $cart->id,
            Configuration::get($this->orderStatus->getAwaitingPayment()),
            $total,
            $this->module->displayName,
            null,
            null,
            (int) $currency->id,
            false,
            $customer->secure_key);

        return $this->getOrder($this->module->currentOrder);
    }

    /**
     * Return an order matching the order id
     *
     * @param $orderId
     *
     * @return Order
     *
     * @throws Exception
     */
    public function getOrder($orderId): Order
    {
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            throw new AbstractPaymentException('Unable to find the order info.');
        }
        if ($order->module !== $this->module->name) {
            throw new AbstractPaymentException("The order is not paid by {$this->module->name}.", sprintf('[payment name: %s] - {dp-msg}', $orderId, $order->module));
        }

        return $order;
    }

    /**
     * Return the currency of the order
     *
     * @param Order $order
     *
     * @return string
     *
     * @throws AbstractPaymentException
     */
    public function getOrderCurrency(Order $order): string
    {
        $currencyId = (int) $order->id_currency;
        $currency = new Currency($currencyId);

        if (!$currency instanceof Currency || empty($currency->iso_code) || !in_array(strtoupper($currency->iso_code), $this->currency->getEnabledCurrencies($this->module->id))) {
            throw new AbstractPaymentException();
        }

        return $currency->iso_code;
    }

    /**
     * @param Order $order
     *
     * @return array
     */
    public function createRedirectInfos($order)
    {
        return [
            'id_cart' => $order->id_cart,
            'id_order' => $order->id,
            'id_module' => $this->module->id,
            'key' => Context::getContext()->customer->secure_key,
        ];
    }
}
