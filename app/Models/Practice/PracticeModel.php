<?php

namespace App\Models\Practice;

use Illuminate\Database\Eloquent\Model;

abstract class PracticeModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function getConnectionName()
    {
        return (string) config('wms_runtime.connection', config('database.default'));
    }
}
