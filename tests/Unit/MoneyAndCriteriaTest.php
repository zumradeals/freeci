<?php

namespace Tests\Unit;

use App\Modules\Catalog\Data\ServiceSearchCriteria;
use App\Shared\Money;
use PHPUnit\Framework\TestCase;

class MoneyAndCriteriaTest extends TestCase
{
    public function test_money_is_whole_francs_with_narrow_no_break_space(): void
    {
        $this->assertSame("35\u{202F}000", Money::xof(35000)->formatted());
        $this->assertSame('950', Money::xof(950)->formatted());
        $this->assertSame("1\u{202F}200\u{202F}000", Money::xof(1200000)->formatted());
    }

    public function test_criteria_are_normalised_and_bounded(): void
    {
        $c = ServiceSearchCriteria::make("  logo \n  vert  ", '', 'nimporte');
        $this->assertSame('logo vert', $c->query);
        $this->assertNull($c->categorySlug);
        $this->assertSame('pertinence', $c->sort);
        $this->assertSame(100, mb_strlen(ServiceSearchCriteria::make(str_repeat('é', 300), null, null)->query));
        $this->assertNull(ServiceSearchCriteria::make('   ', null, null)->query);
        $this->assertFalse(ServiceSearchCriteria::make(null, null, 'recents')->hasFilters());
    }
}
