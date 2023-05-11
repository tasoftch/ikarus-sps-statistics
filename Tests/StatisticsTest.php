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

use Ikarus\SPS\CyclicEngine;
use Ikarus\SPS\Plugin\StopEngine\StopEngineAfterCycleCountPlugin;
use Ikarus\SPS\Register\InternalMemoryRegister;
use Ikarus\SPS\Statistics\Backend\CallbackStaticBackend;
use Ikarus\SPS\Statistics\Backend\Model\DataSet\DataSetInterface;
use Ikarus\SPS\Statistics\Backend\Model\DataSet\PolledMirrorDataSet;
use Ikarus\SPS\Statistics\Backend\Model\DataSet\PushedFromAllPluginsDataSet;
use Ikarus\SPS\Statistics\Backend\Model\MutableStatisticsDescription;
use Ikarus\SPS\Statistics\Plugin\CallbackCyclicPlugin;
use Ikarus\SPS\Statistics\Plugin\StatisticPlugin;
use PHPUnit\Framework\TestCase;

class StatisticsTest extends TestCase
{
	public function testStatisticsPlugin() {
		$DATA = [];
		$pl = new StatisticPlugin('stat', new CallbackStaticBackend(function(DataSetInterface $dataSet, $value, $date) use (&$DATA) {
			$DATA[] = func_get_args();
		},
			(new MutableStatisticsDescription('wetter'))
				->addDataSet( $ds = new PolledMirrorDataSet("regen", 1.2, true, 'ENV.regen') )
		), 'default');

		$mr = new InternalMemoryRegister();
		$pl->initialize($mr);

		$mr->putValue(13.7, "regen", 'ENV');

		$pl->update($mr);
		$this->assertCount(0, $DATA);

		usleep(5e5);
		$pl->update($mr);
		$this->assertCount(0, $DATA);

		usleep(5e5);
		$pl->update($mr);
		$this->assertCount(0, $DATA);

		usleep(5e5);
		$pl->update($mr);
		$this->assertEquals([
			[$ds, 13.7, NULL]
		], $DATA);

		$mr->putValue(44.3, "regen", 'ENV');

		usleep(5e5);
		$pl->update($mr);
		$this->assertCount(1, $DATA);

		usleep(5e5);
		$pl->update($mr);
		$this->assertCount(1, $DATA);

		usleep(5e5);
		$pl->update($mr);

		$this->assertEquals([
			[$ds, 13.7, NULL],
			[$ds, 44.3, NULL]
		], $DATA);
	}

	public function testPushingStatisticsPlugin() {
		$DATA = [];

		$SPS = new CyclicEngine(100);

		$SPS->setMemoryRegister( $MR = new InternalMemoryRegister() );

		$SPS->addPlugin(
			new StatisticPlugin('stat', new CallbackStaticBackend(function(DataSetInterface $dataSet, $value, $date) use (&$DATA) {
				$DATA[] = func_get_args();
			},
				(new MutableStatisticsDescription('wetter'))
					->addDataSet( $ds = new PushedFromAllPluginsDataSet('regen', true, "ENV.niederschlag") )
			), 'default')
		);

		$SPS->addPlugin(
			$plugin = new CallbackCyclicPlugin('test', function($MR) use (&$plugin) {
				static $count = 0;
				if($count++ % 3 == 0)
					$plugin->pushValue($count, 'regen', $count == 10 ? new DateTime("today") : NULL);
			}, ['niederschlag'], 'ENV')
		);

		$SPS->addPlugin(
			new StopEngineAfterCycleCountPlugin('stopper', 10)
		);

		$SPS->run();

		$this->assertEquals([
			[$ds, 1, NULL],
			[$ds, 4, NULL],
			[$ds, 7, NULL],
			[$ds, 10, new Datetime("today")]
		], $DATA);
	}
}
