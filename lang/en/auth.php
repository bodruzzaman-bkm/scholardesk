<?php

/*
 * Authentication messages.
 *
 * Defining this file overrides Laravel's built-in auth translations wholesale
 * for this locale, so the framework's own three keys are repeated here. Adding
 * only 'suspended' would have silently broken 'failed' and 'throttle'.
 */
return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    /*
     * Deliberately says the account is suspended rather than pretending the
     * credentials are wrong. Someone locked out needs to know it was a
     * decision, not a typo, or they will reset their password repeatedly and
     * never get in. It is only ever shown to a person who already proved the
     * password, so it reveals nothing to an outsider.
     */
    'suspended' => 'This account has been suspended. Contact an administrator if you think this is a mistake.',
];
