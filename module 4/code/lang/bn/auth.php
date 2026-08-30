<?php

/*
 * প্রমাণীকরণ বার্তা — the Bangla half of the auth strings.
 *
 * Keys must match lang/en/auth.php exactly; the suite asserts that, so a
 * string added to one locale and forgotten in the other fails rather than
 * silently falling back to English.
 */
return [
    'failed' => 'এই তথ্য আমাদের রেকর্ডের সাথে মেলে না।',
    'password' => 'দেওয়া পাসওয়ার্ডটি সঠিক নয়।',
    'throttle' => 'অনেকবার চেষ্টা করা হয়েছে। :seconds সেকেন্ড পরে আবার চেষ্টা করুন।',
    'suspended' => 'এই অ্যাকাউন্টটি স্থগিত করা হয়েছে। ভুল হয়ে থাকলে প্রশাসকের সাথে যোগাযোগ করুন।',
];
