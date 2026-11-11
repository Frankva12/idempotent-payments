<?php

use Illuminate\Support\Facades\Schedule;

// Idempotency keys only need to outlive the client's retry window.
Schedule::command('model:prune')->daily();

Schedule::command('payouts:reconcile')->everyFiveMinutes()->withoutOverlapping();
