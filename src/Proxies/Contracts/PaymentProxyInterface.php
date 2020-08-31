<?php


namespace AbstractPaymentPrestashop\Proxies\Contracts;


use AbstractPaymentPrestashop\Models\AbstractTransaction;

interface PaymentProxyInterface
{

    public function confirm(AbstractTransaction $transaction, $order, $currency, $amount): ResponseResponsePaymentProxyInterface;

    public function refund($transactionId, $amount);

    public function request($order, $amount, $currency);
}