<?php

namespace Tests\Unit;

use App\Support\Doi;
use PHPUnit\Framework\TestCase;

class DoiTest extends TestCase
{
    /**
     * All of these are the same DOI pasted from different places, and must
     * normalise to one value so the per-user uniqueness rule catches them.
     */
    public function test_every_common_doi_form_normalises_to_the_same_value(): void
    {
        $expected = '10.1000/xyz123';

        foreach ([
            '10.1000/xyz123',
            '10.1000/XYZ123',
            '  10.1000/xyz123  ',
            'https://doi.org/10.1000/xyz123',
            'http://doi.org/10.1000/xyz123',
            'https://dx.doi.org/10.1000/xyz123',
            'doi:10.1000/xyz123',
            'DOI: 10.1000/xyz123',
        ] as $input) {
            $this->assertSame($expected, Doi::normalize($input), "failed for: {$input}");
        }
    }

    public function test_blank_input_normalises_to_null(): void
    {
        $this->assertNull(Doi::normalize(null));
        $this->assertNull(Doi::normalize(''));
        $this->assertNull(Doi::normalize('   '));
    }

    public function test_valid_dois_are_recognised(): void
    {
        $this->assertTrue(Doi::isValid('10.1000/xyz123'));
        $this->assertTrue(Doi::isValid('https://doi.org/10.5555/3295222'));
    }

    public function test_invalid_dois_are_rejected(): void
    {
        $this->assertFalse(Doi::isValid(null));
        $this->assertFalse(Doi::isValid('not-a-doi'));
        $this->assertFalse(Doi::isValid('11.1000/xyz'));   // must start with 10.
        $this->assertFalse(Doi::isValid('10.1/x'));        // registrant too short
        $this->assertFalse(Doi::isValid('10.1000/'));      // no suffix
    }
}
