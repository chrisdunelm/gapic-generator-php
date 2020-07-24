<?php declare(strict_types=1);

namespace Google\Generator\Tests;

use PHPUnit\Framework\TestCase;
use Google\Generator\Collections\Vector;

final class VectorTest extends TestCase
{
    public function testNew(): void
    {
        $a = Vector::New(['one', 'two']);
        $this->assertEquals('one', $a[0]);
        $this->assertEquals('two', $a[1]);
    }

    public function testZip(): void
    {
        $v = Vector::Zip(
            Vector::New([1, 2, 3]),
            Vector::New(['a', 'b']),
        );
        $this->assertCount(2, $v);
        $this->assertEquals(1, $v[0][0]);
        $this->assertEquals(2, $v[1][0]);
        $this->assertEquals('a', $v[0][1]);
        $this->assertEquals('b', $v[1][1]);
    }

    public function testMap(): void
    {
        $a = Vector::New(['one', 'two']);
        $a = $a->map(fn($x) => "{$x}{$x}");
        $this->assertEquals('oneone', $a[0]);
        $this->assertEquals('twotwo', $a[1]);
    }

    public function testFlatMap(): void
    {
        $a = Vector::New([1, 2]);
        $a = $a->flatMap(fn($x) => Vector::New([$x, $x]));
        $this->assertEquals(4, count($a));
        $this->assertEquals(1, $a[0]);
        $this->assertEquals(1, $a[1]);
        $this->assertEquals(2, $a[2]);
        $this->assertEquals(2, $a[3]);
    }

    public function testGroupBy(): void
    {
        $v = Vector::New(['1:a', '2:b', '2:c', '3:d', '3:e', '3:f']);
        $g = $v->groupBy(fn($x) => intval(explode(':', $x)[0]), fn($x) => explode(':', $x)[1]);
        $this->assertEquals(3, count($g));
        $this->assertEquals(1, count($g[1]));
        $this->assertEquals(2, count($g[2]));
        $this->assertEquals(3, count($g[3]));
        $this->assertEquals('a', $g[1][0]);
        $this->assertEquals('b', $g[2][0]);
        $this->assertEquals('c', $g[2][1]);
        $this->assertEquals('d', $g[3][0]);
        $this->assertEquals('e', $g[3][1]);
        $this->assertEquals('f', $g[3][2]);
    }

    public function testDistinct(): void
    {
        $v = Vector::New([1, 1, 1, 2, 3, 3]);
        $v = $v->distinct();
        $this->assertCount(3, $v);
        $this->assertEquals(1, $v[0]);
        $this->assertEquals(2, $v[1]);
        $this->assertEquals(3, $v[2]);
    }

    public function testAny(): void
    {
        $v = Vector::New([1, 2]);
        $this->assertTrue($v->any(fn($x) => $x === 1));
        $this->assertFalse($v->any(fn($x) => $x === 11));
    }

    public function testJoin(): void
    {
        $v = Vector::New(['a', 'b', 'c']);
        $this->assertEquals('a:b:c', $v->join(':'));
    }

    public function testContains(): void
    {
        $v = Vector::New([1, 2]);
        $this->assertFalse($v->contains(0));
        $this->assertTrue($v->contains(1));
        $this->assertTrue($v->contains(2));
        $this->assertFalse($v->contains(3));
    }

    public function testToMap(): void
    {
        $v = Vector::New(['1:one', '2:two']);
        $m = $v->toMap(
            fn($x) => intval(explode(':', $x)[0]),
            fn($x) => explode(':', $x)[1]);
        $this->assertCount(2, $m);
        $this->assertEquals('one', $m[1]);
        $this->assertEquals('two', $m[2]);
    }

    public function testToSet(): void
    {
        $v = Vector::New([1, 2, 3]);
        $s = $v->toSet();
        $this->assertCount(3, $s);
        $this->assertFalse($s[0]);
        $this->assertTrue($s[1]);
        $this->assertTrue($s[2]);
        $this->assertTrue($s[3]);
        $this->assertFalse($s[4]);
    }

    public function testForeach(): void
    {
        $v = Vector::New([1]);
        $found = FALSE;
        foreach ($v as $x) {
            $this->assertEquals(1, $x);
            $found = TRUE;
        }
        $this->assertTrue($found);
    }
}
