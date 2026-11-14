<?php

namespace App\Enums\Resident;

enum HouseResidentStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
}
