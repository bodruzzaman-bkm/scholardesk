<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Enables $this->authorize(...) so controllers delegate access decisions
    // to the policies in App\Policies instead of repeating ownership checks.
    use AuthorizesRequests;
}
