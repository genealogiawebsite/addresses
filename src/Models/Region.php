<?php

namespace LaravelEnso\Addresses\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LaravelEnso\Countries\Models\Country;
use LaravelEnso\Helpers\Contracts\Activatable;
use LaravelEnso\Helpers\Traits\ActiveState;

class Region extends Model implements Activatable
{
    use ActiveState, HasFactory;

    protected $guarded = ['id'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function localities()
    {
        return $this->hasMany(Locality::class);
    }
}
