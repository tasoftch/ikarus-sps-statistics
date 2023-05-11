<?php
/*
 * BSD 3-Clause License
 *
 * Copyright (c) 2019, TASoft Applications
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 *  Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

use Ikarus\SPS\Statistics\Backend\Model\DataSet\PolledMirrorDataSet;
use Ikarus\SPS\Statistics\Backend\Model\DataSet\MirrorMultiplyingValueGenerator;
use Ikarus\SPS\Statistics\Backend\Model\DataSet\Unit;
use Ikarus\SPS\Statistics\Backend\Model\MutableStatisticsDescription;
use Ikarus\SPS\Statistics\Backend\Model\StatisticsDescription;
use Ikarus\SPS\Statistics\Exception\DuplicateDataSetException;
use PHPUnit\Framework\TestCase;

class StatisticsEntityTest extends TestCase
{
	public function testUnit() {
		$u = new Unit("kg", 'Kilogramm');

		$this->assertEquals('kg', $u->getCode());
		$this->assertEquals("Kilogramm", $u->getDescription());

		$u = new Unit("Lt");

		$this->assertEquals('Lt', $u->getCode());
		$this->assertNull($u->getDescription());
	}

	public function testEmptyStat() {
		$s = new StatisticsDescription('test', [

		]);

		$this->assertEquals("test", $s->getName());
		$this->assertEmpty($s->getDataSets());
		$this->assertNull($s->getDescription());

		$s = new StatisticsDescription('test', [

		], "description");

		$this->assertEquals("description", $s->getDescription());
	}

	public function testDataSetGenerator() {
		$gen = new MirrorMultiplyingValueGenerator(100, true, 'ENV.RASEN', NULL, 5);

		$this->assertEquals(100.0, $gen->getUpdateInterval());
		$this->assertTrue($gen->isEnabled());
		$this->assertEquals("ENV.RASEN", $gen->getMemoryRegisterAccess());
		$this->assertEquals(35, $gen->getAdjustedValue(7));

		$gen = new MirrorMultiplyingValueGenerator(100, true, 'ENV.RASEN', "", 5);
		$this->assertEquals("ENV.RASEN", $gen->getMemoryRegisterAccess());

		$gen = new MirrorMultiplyingValueGenerator(100, true, 'ENV.RASEN');
		$this->assertEquals("ENV.RASEN", $gen->getMemoryRegisterAccess());
		$this->assertEquals(7, $gen->getAdjustedValue(7));

		$gen = new MirrorMultiplyingValueGenerator(100, true, 'ENV', 'RASEN');
		$this->assertEquals("ENV.RASEN", $gen->getMemoryRegisterAccess());



		$gen = new MirrorMultiplyingValueGenerator(100, function() use (&$mock) { return $mock; }, 'ENV.RASEN');

		$mock = true;
		$this->assertTrue($gen->isEnabled());

		$mock = false;
		$this->assertFalse($gen->isEnabled());
	}

	public function testStatsConsistency() {
		$stat = new StatisticsDescription("test", [
			$p = new PolledMirrorDataSet('regen', 30, true, "ENV.regen")
		]);

		$this->assertSame($stat, $p->getStatisticDescription());
	}

	public function testDuplicateDataSets() {
		$this->expectException(DuplicateDataSetException::class);

		$stat = new StatisticsDescription("test", [
			new PolledMirrorDataSet('regen', 30, true, "ENV.regen"),
			new PolledMirrorDataSet('temperatur', 30, true, "ENV.temperatur"),
			new PolledMirrorDataSet('druck', 30, true, "ENV.druck"),
			new PolledMirrorDataSet('feuchtigkeit', 30, true, "ENV.feuchtigkeit"),
			new PolledMirrorDataSet('regen', 30, true, "ENV.regen")
		]);
	}

	public function testMutableStatistic() {
		$stat = new MutableStatisticsDescription("test", 'Info');
		$this->assertEquals("test", $stat->getName());
		$this->assertSame("Info", $stat->getDescription());
		$this->assertEmpty($stat->getDataSets());

		$stat = new MutableStatisticsDescription("test", 'Info', [
			$p1 = new PolledMirrorDataSet('regen', 30, true, "ENV.regen"),
			$p2 = new PolledMirrorDataSet('temperatur', 30, true, "ENV.temperatur"),
		]);

		$this->assertSame($stat, $p1->getStatisticDescription());
		$this->assertSame($stat, $p2->getStatisticDescription());

		$stat->addDataSet(
			$p3 = new PolledMirrorDataSet('feuchtigkeit', 30, true, "ENV.feuchtigkeit")
		);
		$this->assertSame($stat, $p3->getStatisticDescription());

		$this->assertSame([
			'regen' => $p1,
			'temperatur' => $p2,
			'feuchtigkeit' => $p3
		], $stat->getDataSets());

		$stat->removeDataSet("regen");

		$this->assertSame([
			'temperatur' => $p2,
			'feuchtigkeit' => $p3
		], $stat->getDataSets());

		$this->assertNull($p1->getStatisticDescription());

		$stat->removeDataSet($p3);

		$this->assertSame([
			'temperatur' => $p2
		], $stat->getDataSets());

		$this->assertNull($p3->getStatisticDescription());
	}

	public function testMutableDuplicateDataSets() {
		$stat = new MutableStatisticsDescription("test", 'Info', [
			new PolledMirrorDataSet('regen', 30, true, "ENV.regen"),
			new PolledMirrorDataSet('temperatur', 30, true, "ENV.temperatur"),
		]);

		$this->expectException( DuplicateDataSetException::class );

		$stat->addDataSet(
			new PolledMirrorDataSet('temperatur', 30, true, "ENV.feuchtigkeit")
		);
	}

	public function testMutableReplaceDataSets() {
		$stat = new MutableStatisticsDescription("test", 'Info', [
			new PolledMirrorDataSet('regen', 30, true, "ENV.regen"),
			$p1 = new PolledMirrorDataSet('temperatur', 30, true, "ENV.temperatur"),
		]);

		$stat->replaceDataSet(
			$p2 = new PolledMirrorDataSet('temperatur', 30, true, "ENV.feuchtigkeit")
		);

		$this->assertEquals("ENV.feuchtigkeit", $stat->getDataSet("temperatur")->getDataSetValueGenerator()->getMemoryRegisterAccess());

		$this->assertNull($p1->getStatisticDescription());
		$this->assertSame($stat, $p2->getStatisticDescription());
	}

	public function testInconsistencyDataSets() {
		$stat = new MutableStatisticsDescription("test", 'Info', [
			new PolledMirrorDataSet('regen', 30, true, "ENV.regen"),
			$p1 = new PolledMirrorDataSet('temperatur', 30, true, "ENV.temperatur"),
		]);

		$this->expectException( \Ikarus\SPS\Statistics\Exception\DataConsistencyException::class );

		$stat2 = new MutableStatisticsDescription("test", 'Info', [
			new PolledMirrorDataSet('regen', 30, true, "ENV.regen"),
			$p1
		]);
	}
}
