<?php

namespace Tests\Unit;

use App\Support\KtaMasker;
use PHPUnit\Framework\TestCase;

/**
 * Pure masking helpers — no database, no framework boot.
 */
class KtaMaskerTest extends TestCase
{
    public function testNameMasksEveryWordKeepingFirstLetter(): void
    {
        $this->assertSame('A*** H***', KtaMasker::name('Achmad Hasanudin'));
    }

    public function testSingleWordName(): void
    {
        $this->assertSame('A***', KtaMasker::name('Achmad'));
    }

    public function testNameCollapsesExtraWhitespace(): void
    {
        $this->assertSame('A*** H***', KtaMasker::name('  Achmad   Hasanudin  '));
    }

    public function testEmptyNameYieldsEmptyString(): void
    {
        $this->assertSame('', KtaMasker::name(null));
        $this->assertSame('', KtaMasker::name('   '));
    }

    public function testMemberIdKeepsLastThree(): void
    {
        $this->assertSame('MZT***119', KtaMasker::memberId('0174011119'));
    }

    public function testMemberIdShortValue(): void
    {
        $this->assertSame('MZT***123', KtaMasker::memberId('123'));
    }

    public function testMemberIdEmpty(): void
    {
        $this->assertSame('', KtaMasker::memberId(''));
    }

    public function testNormalizeNameIsCaseInsensitiveAndCollapsed(): void
    {
        $this->assertSame('achmad hasanudin', KtaMasker::normalizeName('  ACHMAD   Hasanudin '));
    }

    public function testNormalizeMemberIdOnlyTrims(): void
    {
        $this->assertSame('0174011119', KtaMasker::normalizeMemberId('  0174011119 '));
    }

    public function testPhoneLast4IgnoresFormatting(): void
    {
        $this->assertSame('2559', KtaMasker::phoneLast4('0888-388-2559'));
        $this->assertSame('2559', KtaMasker::phoneLast4('+62 888 388 2559'));
    }

    public function testPhoneLast4NullWhenTooShort(): void
    {
        $this->assertNull(KtaMasker::phoneLast4('123'));
        $this->assertNull(KtaMasker::phoneLast4(null));
        $this->assertNull(KtaMasker::phoneLast4(''));
    }
}
