<?php

namespace LaravelEnso\Addresses\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LaravelEnso\Addresses\Services\Coordinates;
use LaravelEnso\Countries\Models\Country;
use LaravelEnso\Helpers\Traits\AvoidsDeletionConflicts;
use LaravelEnso\Helpers\Traits\UpdatesOnTouch;
use LaravelEnso\Rememberable\Traits\Rememberable;
use LaravelEnso\TrackWho\Traits\CreatedBy;

class Address extends Model
{
    use AvoidsDeletionConflicts, CreatedBy, HasFactory, UpdatesOnTouch, Rememberable;

    protected $guarded = ['id'];

    protected $casts = [
        'is_default' => 'boolean', 'is_billing' => 'boolean',
        'is_shipping' => 'boolean',
    ];

    protected $touches = ['addressable'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function locality()
    {
        return $this->belongsTo(Locality::class);
    }

    public function addressable()
    {
        return $this->morphTo();
    }

    public function isDefault()
    {
        return $this->is_default;
    }

    public function getLocalityNameAttribute()
    {
        return optional($this->locality)->name;
    }

    public function getCountyNameAttribute()
    {
        return optional($this->region)->name;
    }

    public function label()
    {
        $locality = optional($this->locality)->name ?? $this->city;

        return (new Collection([optional($this->region)->name, $locality, $this->street]))
            ->filter()->implode(', ');
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

    public function store()
    {
        DB::transaction(function () {
            $defaultAddress = $this->addressable->address;

            if ($this->is_default) {
                optional($defaultAddress)->update(['is_default' => false]);
            } elseif (! $defaultAddress) {
                $this->is_default = true;
            }

            if ($this->is_billing) {
                $this->addressable->addresses()
                    ->whereIsBilling(true)
                    ->update(['is_billing' => false]);
            }

            $this->save();
        });
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

    public function toggleBilling()
    {
        if (! $this->is_billing) {
            return $this->makeBilling();
        }

        return $this->update(['is_billing' => false]);
    }

    public function makeBilling()
    {
        DB::transaction(function () {
            $this->addressable->addresses()
                ->whereIsBilling(true)
                ->update(['is_billing' => false]);

            $this->update(['is_billing' => true]);
        });
    }

    public function toggleShipping()
    {
        $this->update(['is_shipping' => ! $this->is_shipping]);
    }

    public function localize()
    {
        $this->update((new Coordinates($this))->get());

        return $this;
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
            && $this->addressable->addresses()->where('id', '<>', $this->id)->exists();
    }

    public function isLocalized(): bool
    {
        return $this->lat !== null && $this->long !== null;
    }

    private function canBeMultiple(): bool
    {
        return method_exists($this->addressable, 'addresses');
    }
}
