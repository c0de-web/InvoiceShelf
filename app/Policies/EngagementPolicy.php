<?php

namespace App\Policies;

use App\Models\Engagement;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Engagements follow the invoice abilities, within the active company.
 */
class EngagementPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-invoice', Invoice::class);
    }

    public function view(User $user, Engagement $engagement): bool
    {
        return BouncerFacade::can('view-invoice', Invoice::class) && $this->inActiveCompany($user, $engagement);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-invoice', Invoice::class);
    }

    public function update(User $user, Engagement $engagement): bool
    {
        return BouncerFacade::can('edit-invoice', Invoice::class) && $this->inActiveCompany($user, $engagement);
    }

    public function delete(User $user, Engagement $engagement): bool
    {
        return BouncerFacade::can('delete-invoice', Invoice::class) && $this->inActiveCompany($user, $engagement);
    }

    /**
     * Turning a milestone into an invoice needs the right to create invoices.
     */
    public function invoice(User $user, Engagement $engagement): bool
    {
        return BouncerFacade::can('create-invoice', Invoice::class) && $this->inActiveCompany($user, $engagement);
    }

    private function inActiveCompany(User $user, Engagement $engagement): bool
    {
        return (int) $engagement->company_id === (int) request()->header('company')
            && $user->hasCompany($engagement->company_id);
    }
}
