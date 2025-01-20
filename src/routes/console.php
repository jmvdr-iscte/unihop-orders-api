<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('stripe:process-jobs')->weekly();
