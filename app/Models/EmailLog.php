<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'recipient_email',
        'subject',
        'email_type',
        'status',
        'error_message',
    ];
}
