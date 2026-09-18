<?php

namespace App\Exceptions;

use Exception;

class BusinessException extends Exception
{
    protected string $errorCode;
    protected int $statusCode;

    public function __construct(
        string $message,
        string $errorCode = 'BUSINESS_ERROR',
        int $statusCode = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    // Factory methods for common business errors
    public static function paymentNotVerified(): self
    {
        return new self(
            'Payment has not been verified yet.',
            'PAYMENT_NOT_VERIFIED',
            422
        );
    }

    public static function subscriptionAlreadyActive(): self
    {
        return new self(
            'An active subscription already exists for this user.',
            'SUBSCRIPTION_ALREADY_ACTIVE',
            422
        );
    }

    public static function subscriptionNotPayable(): self
    {
        return new self(
            'This subscription is not awaiting payment.',
            'SUBSCRIPTION_NOT_PAYABLE',
            422
        );
    }

    public static function pricingJobLocked(): self
    {
        return new self(
            'Another pricing job is currently running for this BOQ. Please wait for it to complete.',
            'PRICING_JOB_LOCKED',
            409
        );
    }

    public static function pricingJobNotFound(): self
    {
        return new self(
            'Pricing job not found.',
            'PRICING_JOB_NOT_FOUND',
            404
        );
    }

    public static function invalidBatchSize(): self
    {
        return new self(
            'Invalid batch size. Allowed values: 10, 20, 25.',
            'INVALID_BATCH_SIZE',
            422
        );
    }

    public static function aiProviderUnavailable(): self
    {
        return new self(
            'AI service is temporarily unavailable. Please try again later.',
            'AI_PROVIDER_UNAVAILABLE',
            503
        );
    }

    public static function hardwarePriceFetchFailed(string $reason): self
    {
        return new self(
            "Failed to fetch hardware prices: {$reason}",
            'HARDWARE_PRICE_FETCH_FAILED',
            502
        );
    }

    public static function csvImportFailed(string $reason): self
    {
        return new self(
            "CSV import failed: {$reason}",
            'CSV_IMPORT_FAILED',
            422
        );
    }

    public static function beneficiaryHasActiveSubscription(): self
    {
        return new self(
            'The selected beneficiary already has an active subscription.',
            'BENEFICIARY_HAS_ACTIVE_SUBSCRIPTION',
            422
        );
    }

    public static function planNotAvailable(): self
    {
        return new self(
            'The selected plan is not available.',
            'PLAN_NOT_AVAILABLE',
            422
        );
    }

    public static function paymentGatewayUnavailable(): self
    {
        return new self(
            'The selected payment method is currently unavailable.',
            'PAYMENT_GATEWAY_UNAVAILABLE',
            503
        );
    }

    public static function currencyNotSupported(): self
    {
        return new self(
            'The selected payment method does not support this currency.',
            'CURRENCY_NOT_SUPPORTED',
            422
        );
    }

    public static function phoneNumberRequired(): self
    {
        return new self(
            'Enter the mobile money phone number to continue.',
            'PHONE_NUMBER_REQUIRED',
            422
        );
    }
}