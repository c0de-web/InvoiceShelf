<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

/**
 * Categories follow the item abilities: seeing items lets you see them, managing items lets you manage them.
 */
class ItemCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return BouncerFacade::can('view-item', Item::class);
    }

    public function view(User $user, ItemCategory $category): bool
    {
        return BouncerFacade::can('view-item', Item::class) && $this->inActiveCompany($user, $category);
    }

    public function create(User $user): bool
    {
        return BouncerFacade::can('create-item', Item::class);
    }

    public function update(User $user, ItemCategory $category): bool
    {
        return BouncerFacade::can('edit-item', Item::class) && $this->inActiveCompany($user, $category);
    }

    public function delete(User $user, ItemCategory $category): bool
    {
        return BouncerFacade::can('delete-item', Item::class) && $this->inActiveCompany($user, $category);
    }

    private function inActiveCompany(User $user, ItemCategory $category): bool
    {
        return (int) $category->company_id === (int) request()->header('company')
            && $user->hasCompany($category->company_id);
    }
}
