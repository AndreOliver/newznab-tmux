<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseProcessing extends Model
{
    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function release()
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }
}
