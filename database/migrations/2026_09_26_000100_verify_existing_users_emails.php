<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The panel now requires a verified email address. Everyone who signed up before that was
     * never asked to verify, so without this they would be locked out behind a prompt for a
     * link they never received. Accounts that exist now are grandfathered in as verified; only
     * sign-ups from here on go through the check.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    /**
     * Nothing to undo: which accounts were unverified before is not recorded, and clearing
     * every timestamp would lock out people who did verify.
     */
    public function down(): void {}
};
