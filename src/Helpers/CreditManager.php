<?php

namespace Webkul\B2BSuite\Helpers;

use Illuminate\Support\Facades\DB;
use Webkul\B2BSuite\Models\CompanyCredit;
use Webkul\B2BSuite\Models\CompanyCreditTransaction;
use Webkul\B2BSuite\Repositories\CompanyCreditRepository;
use Webkul\B2BSuite\Repositories\CompanyCreditTransactionRepository;

class CreditManager
{
    /**
     * Create a new credit manager instance.
     *
     * @return void
     */
    public function __construct(
        protected CompanyCreditRepository $companyCreditRepository,
        protected CompanyCreditTransactionRepository $companyCreditTransactionRepository,
    ) {}

    /**
     * Whether the company-credit feature is enabled.
     */
    public function isActive(): bool
    {
        return (bool) core()->getConfigData('b2b.credit.settings.active');
    }

    /**
     * The credit account for a company (or null when it has none).
     */
    public function find($companyId): ?CompanyCredit
    {
        return $this->companyCreditRepository->findOneByField('company_id', $companyId);
    }

    /**
     * Resolve the company (a `customers` row with type = company) a customer belongs to:
     * the customer itself when it is the company, otherwise its parent company.
     */
    public function companyOf($customer)
    {
        if (! $customer) {
            return null;
        }

        if ($customer->type === 'company') {
            return $customer;
        }

        return $customer->companies->first();
    }

    /**
     * The credit account that applies to a customer (resolved through its company).
     */
    public function companyCreditFor($customer): ?CompanyCredit
    {
        $company = $this->companyOf($customer);

        return $company ? $this->find($company->id) : null;
    }

    /**
     * The credit account a customer may spend from: both the company and its credit must be active.
     */
    public function activeCompanyCreditFor($customer): ?CompanyCredit
    {
        $company = $this->companyOf($customer);

        if (! $company?->status) {
            return null;
        }

        $credit = $this->find($company->id);

        return $credit?->status ? $credit : null;
    }

    /**
     * Get the company's credit account, creating an empty one on first use.
     */
    public function getOrCreate($companyId): CompanyCredit
    {
        if ($credit = $this->find($companyId)) {
            return $credit;
        }

        return $this->companyCreditRepository->create([
            'company_id' => $companyId,
            'credit_currency_code' => core()->getBaseCurrencyCode(),
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'allow_exceed_limit' => false,
            'status' => false,
        ]);
    }

    /**
     * Available credit = limit - what is currently owed (can be negative if a purchase
     * was allowed to exceed the limit).
     */
    public function availableCredit(CompanyCredit $credit): float
    {
        return (float) $credit->credit_limit - (float) $credit->outstanding_balance;
    }

    /**
     * Whether the company can charge the given amount to its credit.
     */
    public function canAfford(CompanyCredit $credit, float $amount): bool
    {
        if (! $credit->status) {
            return false;
        }

        if ($credit->allow_exceed_limit) {
            return true;
        }

        return $this->availableCredit($credit) >= $amount;
    }

    /**
     * Set / update the credit limit, currency and flags (admin). Writes an "allocated"
     * entry the first time and "updated" thereafter.
     */
    public function setLimit(CompanyCredit $credit, array $data, array $actor = []): CompanyCredit
    {
        return DB::transaction(function () use ($credit, $data, $actor) {
            $credit = $this->lock($credit);

            $isFirst = $credit->transactions()->count() === 0;

            $previousLimit = (float) $credit->credit_limit;

            $newLimit = (float) ($data['credit_limit'] ?? $credit->credit_limit);

            $credit->credit_limit = $newLimit;

            if (! empty($data['credit_currency_code'])) {
                $credit->credit_currency_code = $data['credit_currency_code'];
            }

            if (array_key_exists('allow_exceed_limit', $data)) {
                $credit->allow_exceed_limit = (bool) $data['allow_exceed_limit'];
            }

            if (array_key_exists('status', $data)) {
                $credit->status = (bool) $data['status'];
            }

            $credit->save();

            /**
             * The first grant is recorded as an allocation; every later limit change is
             * recorded as an update — capturing the signed delta and the resulting
             * available-credit snapshot — so the ledger always explains how available
             * credit moved. A reduction below the outstanding balance simply pushes
             * available negative (over-limit) without touching what the company owes.
             */
            if ($isFirst) {
                $this->ledger(
                    $credit,
                    CompanyCreditTransaction::OPERATION_ALLOCATED,
                    $newLimit,
                    ['comment' => $data['comment'] ?? null],
                    $actor
                );
            } elseif (abs($newLimit - $previousLimit) >= 0.0001) {
                $this->ledger(
                    $credit,
                    CompanyCreditTransaction::OPERATION_UPDATED,
                    $newLimit - $previousLimit,
                    ['comment' => $data['comment'] ?? null],
                    $actor
                );
            }

            return $credit->fresh();
        });
    }

    /**
     * Charge an order to the company's credit (increases what it owes).
     */
    public function purchase(CompanyCredit $credit, float $amount, $orderId = null, array $actor = []): CompanyCredit
    {
        return $this->apply($credit, CompanyCreditTransaction::OPERATION_PURCHASED, $amount, 1, ['order_id' => $orderId], $actor);
    }

    /**
     * Record an offline payment received against the balance (admin reimbursement).
     */
    public function reimburse(CompanyCredit $credit, float $amount, array $extra = [], array $actor = []): CompanyCredit
    {
        return $this->apply($credit, CompanyCreditTransaction::OPERATION_REIMBURSED, $amount, -1, $extra, $actor);
    }

    /**
     * Reduce the balance because an order was refunded.
     */
    public function refund(CompanyCredit $credit, float $amount, $orderId = null, array $actor = []): CompanyCredit
    {
        return $this->apply($credit, CompanyCreditTransaction::OPERATION_REFUNDED, $amount, -1, ['order_id' => $orderId], $actor);
    }

    /**
     * Reverse a purchase because an order was cancelled before payment.
     */
    public function revert(CompanyCredit $credit, float $amount, $orderId = null, array $actor = []): CompanyCredit
    {
        return $this->apply($credit, CompanyCreditTransaction::OPERATION_REVERTED, $amount, -1, ['order_id' => $orderId], $actor);
    }

    /**
     * Apply a balance change transactionally and append a ledger entry. `$direction` is
     * +1 to increase what is owed (purchase) or -1 to decrease it (payment/refund).
     */
    protected function apply(CompanyCredit $credit, string $operation, float $amount, int $direction, array $extra, array $actor): CompanyCredit
    {
        $amount = abs((float) $amount);

        return DB::transaction(function () use ($credit, $operation, $amount, $direction, $extra, $actor) {
            $credit = $this->lock($credit);

            /**
             * A payment larger than what is owed is allowed: the balance goes negative,
             * which the available-credit calculation turns into extra spendable credit
             * (an advance / prepayment held for the company).
             */
            $credit->outstanding_balance = (float) $credit->outstanding_balance + ($direction * $amount);

            $credit->save();

            $this->ledger($credit, $operation, $amount, $extra, $actor);

            return $credit->fresh();
        });
    }

    /**
     * Append an audit entry capturing the ledger state after a change.
     */
    protected function ledger(CompanyCredit $credit, string $operation, float $amount, array $extra, array $actor): void
    {
        $this->companyCreditTransactionRepository->create([
            'company_credit_id' => $credit->id,
            'operation' => $operation,
            'amount' => $amount,
            'outstanding_balance_after' => $credit->outstanding_balance,
            'available_credit_after' => $this->availableCredit($credit),
            'credit_limit_after' => $credit->credit_limit,
            'order_id' => $extra['order_id'] ?? null,
            'reference' => $extra['reference'] ?? null,
            'comment' => $extra['comment'] ?? null,
            'actor_type' => $actor['type'] ?? 'system',
            'actor_id' => $actor['id'] ?? null,
        ]);
    }

    /**
     * Re-read the credit row with a row lock for safe concurrent updates.
     */
    protected function lock(CompanyCredit $credit): CompanyCredit
    {
        return $this->companyCreditRepository->findForUpdate($credit->id);
    }
}
