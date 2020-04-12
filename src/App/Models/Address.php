<?php

namespace LaravelEnso\Addresses\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LaravelEnso\Countries\App\Models\Country;
use LaravelEnso\Helpers\App\Traits\AvoidsDeletionConflicts;
use LaravelEnso\Helpers\App\Traits\UpdatesOnTouch;

class Address extends Model
{
    use AvoidsDeletionConflicts, UpdatesOnTouch;

    protected $fillable = [
        'addressable_id', 'addressable_type', 'country_id', 'is_default', 'apartment', 'floor',
        'entry', 'building', 'building_type', 'number', 'street', 'street_type',
        'sub_administrative_area', 'city', 'administrative_area', 'postal_area',
        'obs', 'lat', 'long',
    ];

    protected $casts = ['is_default' => 'boolean'];

    protected $touches = ['addressable'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function addressable()
    {
        return $this->morphTo();
    }

    public function isDefault()
    {
        return $this->is_default;
    }

    public function getLabelAttribute()
    {
        return $this->label();
    }

    public function label()
    {
        return (new Collection(config('enso.addresses.label.attributes')))
            ->map(fn ($attribute) => $this->$attribute)
            ->filter()
            ->implode(config('enso.addresses.label.separator'));
    }

    public function scopeDefault($query)
    {
        return $query->whereIsDefault(true);
    }

    public function scopeNotDefault($query)
    {
        return $query->whereIsDefault(false);
    }

    public function scopeFor($query, int $addressable_id, string $addressable_type)
    {
        return $query->whereAddressableId($addressable_id)
            ->whereAddressableType($addressable_type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('is_default');
    }

    public function makeDefault()
    {
        DB::transaction(function () {
            $this->addressable->addresses()
                ->whereIsDefault(true)
                ->update(['is_default' => false]);

            $this->update(['is_default' => true]);
        });
    }

    public function getLoggableMorph()
    {
        return config('enso.addresses.loggableMorph');
    }

    public function shouldBeSingle(): bool
    {
        return ! $this->canBeMultiple()
            && $this->addressable->address()->exists();
    }

    public function isNotSingle(): bool
    {
        return $this->canBeMultiple()
            && $this->addressable->address()->where('id', '<>', $this->id)->exists();
    }

    private function canBeMultiple(): bool
    {
        return method_exists($this->addressable, 'addresses');
    }
}