<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A pharmacy business. It owns one or more branches and one product catalogue. */
class Organization extends Model
{
    protected $fillable = ['owner_id', 'name', 'phone', 'email'];

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function branches() { return $this->hasMany(Pharmacy::class)->orderByDesc('is_main')->orderBy('name'); }
    public function products() { return $this->hasMany(Product::class); }
}
