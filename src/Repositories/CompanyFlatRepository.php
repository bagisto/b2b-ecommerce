<?php

namespace Webkul\B2BSuite\Repositories;

use Illuminate\Support\Facades\Event;
use Webkul\B2BSuite\Contracts\CompanyFlat as CompanyFlatContract;
use Webkul\Core\Eloquent\Repository;

class CompanyFlatRepository extends Repository
{
    /**
     * Specify model class name.
     */
    public function model(): string
    {
        return CompanyFlatContract::class;
    }

    /**
     * Create customer flat record.
     */
    public function create(array $data): CompanyFlatContract
    {
        Event::dispatch('customer.flat.create.before');

        $customerFlat = $this->model->create($data);

        Event::dispatch('customer.flat.create.after', $customerFlat);

        return $customerFlat;
    }

    /**
     * Update customer flat record.
     */
    public function update(array $data, $id, $attribute = 'id'): CompanyFlatContract
    {
        Event::dispatch('customer.flat.update.before', $id);

        $customerFlat = $this->find($id);

        $customerFlat->update($data);

        Event::dispatch('customer.flat.update.after', $customerFlat);

        return $customerFlat;
    }

    /**
     * Delete customer flat record.
     */
    public function delete($id): bool
    {
        Event::dispatch('customer.flat.delete.before', $id);

        $result = parent::delete($id);

        Event::dispatch('customer.flat.delete.after', $id);

        return $result;
    }

    /**
     * Get customer flat by customer ID and locale.
     */
    public function findByCustomerAndLocale(int $customerId, string $locale): ?CompanyFlatContract
    {
        return $this->model
            ->where('customer_id', $customerId)
            ->where('locale', $locale)
            ->first();
    }

    /**
     * Get customer flat by customer ID, locale and channel.
     */
    public function findByCustomerLocaleAndChannel(int $customerId, string $locale, string $channel): ?CompanyFlatContract
    {
        return $this->model
            ->where('customer_id', $customerId)
            ->where('locale', $locale)
            ->where('channel', $channel)
            ->first();
    }

    /**
     * Get all customer flat records for a customer.
     */
    public function getByCustomer(int $customerId)
    {
        return $this->model
            ->where('customer_id', $customerId)
            ->get();
    }

    /**
     * Paginate companies (with their currently assigned catalog) for the catalog picker,
     * searchable by business name / email and scoped to a sales rep when provided.
     */
    public function searchCompaniesForPicker(?string $query, $repId, string $sort, string $order, int $perPage = 10)
    {
        return $this->model
            ->leftJoin('customers', 'b2b_company_flat.customer_id', '=', 'customers.id')
            ->leftJoin('b2b_company_catalogs', 'customers.company_catalog_id', '=', 'b2b_company_catalogs.id')
            ->where('customers.type', 'company')
            ->where('b2b_company_flat.locale', app()->getLocale())
            ->when($repId, fn ($builder) => $builder->where('customers.sales_rep_id', $repId))
            ->when((string) $query !== '', function ($builder) use ($query) {
                $builder->where(function ($sub) use ($query) {
                    $sub->where('b2b_company_flat.business_name', db_grammar()->caseInsensitiveLike(), '%'.$query.'%')
                        ->orWhere('b2b_company_flat.email', db_grammar()->caseInsensitiveLike(), '%'.$query.'%');
                });
            })
            ->select(
                'b2b_company_flat.customer_id as id',
                'b2b_company_flat.business_name',
                'b2b_company_flat.email',
                'b2b_company_catalogs.name as current_catalog'
            )
            ->orderBy($sort, $order)
            ->paginate($perPage);
    }

    /**
     * Create or update customer flat record.
     */
    public function createOrUpdate(array $data): CompanyFlatContract
    {
        $customerFlat = $this->findByCustomerLocaleAndChannel(
            $data['customer_id'],
            $data['locale'],
            $data['channel']
        );

        if ($customerFlat) {
            return $this->update($data, $customerFlat->id);
        }

        return $this->create($data);
    }

    /**
     * Sync customer flat records for all locales and channels.
     */
    public function syncForCustomer(int $customerId, array $data): void
    {
        $locales = core()->getAllLocales();

        $channels = core()->getAllChannels();

        foreach ($locales as $locale) {
            foreach ($channels as $channel) {
                $flatData = array_merge($data, [
                    'customer_id' => $customerId,
                    'locale' => $locale->code,
                    'channel' => $channel->code,
                ]);

                $this->createOrUpdate($flatData);
            }
        }
    }
}
