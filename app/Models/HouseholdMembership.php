<?php

namespace App\Models;

use Laravel\Jetstream\Membership as JetstreamMembership;

class HouseholdMembership extends JetstreamMembership
{
    protected $table = 'household_user';

    public $incrementing = true;
}
