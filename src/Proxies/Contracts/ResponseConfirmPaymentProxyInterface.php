<?php


namespace AbstractPaymentPrestashop\Proxies\Contracts;


interface ResponseConfirmPaymentProxyInterface
{
    public function getAmount(): float;

    public function getCustomFields(): array;
}