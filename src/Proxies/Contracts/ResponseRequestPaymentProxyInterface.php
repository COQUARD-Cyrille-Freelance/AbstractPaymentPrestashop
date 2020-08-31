<?php


namespace AbstractPaymentPrestashop\Proxies\Contracts;


interface ResponseRequestPaymentProxyInterface
{
    public function getIdTransaction();

    public function getUrl(): string;

    public function getCustomFields(): array;
}