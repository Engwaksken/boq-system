<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoqItemPriceSuggestion extends Model
{
    protected $fillable = ['boq_item_id', 'location', 'suggested_rate', 'confidence', 'explanation', 'provider', 'currency'];
}
