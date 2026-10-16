<?php

namespace Tests\Unit;

use App\Support\AmountInWords;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    /** @dataProvider numbers */
    public function test_numbers_follow_french_rules(int $n, string $expected): void
    {
        $this->assertSame($expected, AmountInWords::number($n));
    }

    public static function numbers(): array
    {
        return [
            [0, 'zéro'], [1, 'un'], [16, 'seize'], [17, 'dix-sept'], [21, 'vingt et un'], [22, 'vingt-deux'],
            [60, 'soixante'], [71, 'soixante et onze'], [77, 'soixante-dix-sept'], [80, 'quatre-vingts'],
            [81, 'quatre-vingt-un'], [91, 'quatre-vingt-onze'], [99, 'quatre-vingt-dix-neuf'], [100, 'cent'],
            [101, 'cent un'], [200, 'deux cents'], [280, 'deux cent quatre-vingts'], [1000, 'mille'],
            [1001, 'mille un'], [2000, 'deux mille'], [80000, 'quatre-vingt mille'], [200000, 'deux cent mille'],
            [18480, 'dix-huit mille quatre cent quatre-vingts'], [1000000, 'un million'],
            [2500000, 'deux millions cinq cent mille'], [1200000000, 'un milliard deux cents millions'],
        ];
    }

    public function test_money_in_dirhams_with_centimes(): void
    {
        $this->assertSame('un dirham', AmountInWords::money(1, 'MAD'));
        $this->assertSame('dix-huit mille quatre cent quatre-vingts dirhams', AmountInWords::money(18480, 'MAD'));
        $this->assertSame('vingt-deux mille cent soixante-seize dirhams et cinquante centimes', AmountInWords::money(22176.5, 'MAD'));
        $this->assertSame('cent euros et un centime', AmountInWords::money(100.01, 'EUR'));
    }
}
