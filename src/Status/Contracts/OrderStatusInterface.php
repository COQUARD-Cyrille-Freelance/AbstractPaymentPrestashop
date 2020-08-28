<?php


namespace AbstractPaymentPrestashop\Status\Contracts;


interface OrderStatusInterface
{
    public function getPaymentAccepted(): string;

    public function getPaymentError(): string;

    public function getCanceled(): string;

    public function getInProgress(): string;

    public function getAwaitingPayment(): string;

    public function listStates(): array;
}