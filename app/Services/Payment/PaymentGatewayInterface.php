<?php

interface PaymentGatewayInterface 
{
    public function initiatePayment(float $amount, array $metadata): array;
    public function verifyPayment(string $transactionId): bool;
}