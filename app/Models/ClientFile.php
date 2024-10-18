<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'file_path',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}

