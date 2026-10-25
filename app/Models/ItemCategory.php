<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company's item category; with a parent it is a subcategory. Two levels at most.
 */
class ItemCategory extends Model
{
    protected $fillable = ['company_id', 'parent_id', 'name', 'description'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function scopeWhereCompany($query)
    {
        $query->where('item_categories.company_id', request()->header('company'));
    }
}
