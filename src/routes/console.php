<?php

use Illuminate\Support\Facades\Schedule;

//TODO change this 
Schedule::command('stripe:process-jobs')->everyFifteenMinutes();
