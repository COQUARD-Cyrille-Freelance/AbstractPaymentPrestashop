<?php


namespace AbstractPaymentPrestashop\Proxies\Contracts;


interface ResponseResponsePaymentProxyInterface
{
    public function getIdTransaction();

    public function getUrl(): string;

    public function getCustomFields(): array;
}