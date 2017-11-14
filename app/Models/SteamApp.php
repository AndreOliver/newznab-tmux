<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SteamApp extends Model
{
    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $guarded =[];
}
