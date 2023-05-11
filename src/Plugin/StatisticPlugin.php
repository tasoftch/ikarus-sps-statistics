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

namespace Ikarus\SPS\Statistics\Plugin;

use Ikarus\SPS\EngineDependencyInterface;
use Ikarus\SPS\EngineInterface;
use Ikarus\SPS\Plugin\AbstractPlugin;
use Ikarus\SPS\Plugin\PluginInterface;
use Ikarus\SPS\Register\MemoryRegisterInterface;
use Ikarus\SPS\Statistics\Backend\BackendInterface;
use Ikarus\SPS\Statistics\Backend\Model\DataSet\DataSetInterface;
use Ikarus\SPS\Statistics\Backend\Model\DataSet\PollValueGeneratorInterface;
use Ikarus\SPS\Statistics\Backend\Model\DataSet\PushValueGeneratorInterface;
use Ikarus\SPS\Statistics\Plugin\Observer\PushObserverInterface;

class StatisticPlugin extends AbstractPlugin implements EngineDependencyInterface
{
	/** @var BackendInterface */
	private $backend;

	/** @var EngineInterface */
	private $engine;

	private $pushed_generators = [];
	private $polled_generators = [];

	private $timeout = 0;
	private $next_generators = [];

	public function __construct(string $identifier, BackendInterface $statisticBackend, string $domain = NULL)
	{
		parent::__construct($identifier, $domain);
		$this->backend = $statisticBackend;

		foreach($statisticBackend->getStatisticDescriptions() as $description) {
			foreach($description->getDataSets() as $axis) {
				$gen = $axis->getDataSetValueGenerator();

				if($gen instanceof PushValueGeneratorInterface) {
					$this->pushed_generators[] = [$axis, $gen];
				}
				if($gen instanceof PollValueGeneratorInterface) {
					list($domain, $key) = explode(".", $gen->getMemoryRegisterAccess(), 2);
					$this->polled_generators[] = [$axis, $gen, $domain, $key];
				}
			}
		}
	}

	public function initialize(MemoryRegisterInterface $memoryRegister)
	{
		parent::initialize($memoryRegister);

		$observers = [];
		/**
		 * @var PushValueGeneratorInterface $generator
		 * @var DataSetInterface $axis
		 */
		foreach($this->pushed_generators as $gen) {
			list($axis, $generator) = $gen;

			@ list($domain, $key) = explode(".", $generator->getMemoryRegisterAccess(), 2);
			if($domain && $key) {
				/** @var PluginInterface $plugin */
				foreach($this->engine->getPlugins() as $plugin) {
					if($plugin instanceof PushStatisticPluginInterface) {
						if($plugin->getDomain() == $domain && $plugin->acceptsDataSet($axis, $key)) {
							if($generator->acceptsFromPlugin($plugin)) {
								$observers[ $plugin->getIdentifier() ]["plugin"] = $plugin;
								$observers[ $plugin->getIdentifier() ]["gen"] = $generator;
								$observers[ $plugin->getIdentifier() ]['axis'][$axis->getName()] = $axis;
							}
						}
					}
				}
			}
		}

		foreach($observers as $observer) {
			/** @var PushStatisticPluginInterface $p */
			$p = $observer["plugin"];

			$p->registerPushObserver(new class($this, $observer["axis"], $observer["gen"]) implements PushObserverInterface {
				private $ref, $axis, $gen;

				/**
				 * @param $ref
				 * @param $axis
				 */
				public function __construct($ref, $axis, $gen)
				{
					$this->ref = $ref;
					$this->axis = $axis;
					$this->gen = $gen;
				}


				public function getWatchingAxis(): array
				{
					return $this->axis;
				}

				public function pushValue($value, $axis, \DateTime $date = NULL)
				{
					if(is_string($axis))
						$axis = $this->axis[$axis];
					if($axis instanceof DataSetInterface)
						$this->ref->_triggerPush($this->gen, $axis, $value, $date);
				}
			});
		}
	}

	public function _triggerPush(PushValueGeneratorInterface $generator, DataSetInterface $axis, $value, $date) {
		if($generator->isEnabled()) {
			$this->backend->insertNewValue($axis, $value, $date);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function update(MemoryRegisterInterface $memoryRegister)
	{
		if($this->timeout < microtime(true)) {
			$this->_updateNextPollGenerators($memoryRegister);

			// Determine next poll cycle
			/** @var PollValueGeneratorInterface $generator */
			$intervals = [];
			$min = PHP_INT_MAX;
			foreach($this->polled_generators as $gen) {
				list(,$generator) = $gen;
				if($generator->isEnabled()) {
					$intv = round( $generator->getUpdateInterval(), 1);
					$min = min($min, $intv);
					$intervals[$intv][] = $gen;
				}
			}

			if($ints = $intervals[$min] ?? NULL) {
				$this->timeout = microtime(true) + $min;
				$this->next_generators = $ints;
			} else {
				$this->timeout = time() + 60;
			}
		}
	}

	private function _updateNextPollGenerators(MemoryRegisterInterface $memoryRegister) {
		/** @var PollValueGeneratorInterface $generator */
		/** @var DataSetInterface $axis */
		foreach($this->next_generators as $gen) {
			list($axis, $generator, $domain, $key) = $gen;

			if($generator->isEnabled()) {
				if($domain)
					$value = $memoryRegister->fetchValue($domain, $key);
				else
					$value = NULL;

				$value = $generator->getAdjustedValue($value);

				$this->backend->insertNewValue($axis, $value);
			}
		}
		$this->next_generators = [];
	}

	public function setEngine(?EngineInterface $engine)
	{
		$this->engine = $engine;
	}
}