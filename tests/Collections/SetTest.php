<?php declare(strict_types=1);

namespace Google\Generator\Tests;

use PHPUnit\Framework\TestCase;
use Google\Generator\Collections\Set;
use Google\Generator\Collections\Equality;

final class SetTest extends TestCase
{
    public function testNew(): void
    {
        $s = Set::New([1, 2]);
        $this->assertCount(2, $s);
    }

    public function testForeach(): void
    {
        $s = Set::New([1]);
        $found = FALSE;
        foreach ($s as $x) {
            $this->assertEquals(1, $x);
            $found = TRUE;
        }
        $this->assertTrue($found);
    }

    public function testSet(): void
    {
        $s = Set::New([1, 2]);
        $this->assertCount(2, $s);
        $this->assertTrue($s[1]);
        $this->assertTrue($s[2]);
        $this->assertFalse($s[3]);
        $this->assertFalse($s[0]);
        $this->assertFalse($s['1']);
        $this->assertFalse($s['2']);
    }

    public function testAdd(): void
    {
        $s = Set::New();
        $s = $s->add(1);
        $this->assertCount(1, $s);
        $s = $s->add(1);
        $this->assertCount(1, $s);
        $s = $s->add(2);
        $this->assertCount(2, $s);
        $s = $s->add(1);
        $s = $s->add(2);
        $this->assertCount(2, $s);
    }
}