<?php


namespace AbstractPaymentPrestashop\Status\Contracts;


interface TransactionStatusInterface
{
    public function getRequested(): string;

    public function getCanceled(): string;

    public function getConfirmed(): string;

    public function getFailure(): string;
}