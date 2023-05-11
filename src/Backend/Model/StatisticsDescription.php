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

namespace Ikarus\SPS\Statistics\Backend\Model;

use Ikarus\SPS\Statistics\Backend\Model\DataSet\DataSetInterface;
use Ikarus\SPS\Statistics\Exception\DataConsistencyException;
use Ikarus\SPS\Statistics\Exception\DuplicateDataSetException;

class StatisticsDescription implements StatisticsDescriptionInterface
{
	/** @var string */
	private $name;
	/** @var string|null */
	private $description;
	/** @var DataSetInterface[] */
	protected $dataSets = [];

	/**
	 * @param string $name
	 * @param string|null $description
	 * @param DataSetInterface[] $dataSets
	 */
	public function __construct(string $name, array $dataSets, string $description = NULL)
	{
		$this->name = $name;
		$this->description = $description;

		foreach($dataSets as $dataSet) {
			if($this->checkDuplicationAndConsistency($dataSet)) {
				$this->dataSets[$dataSet->getName()] = $dataSet;
				$dataSet->setStatisticDescription($this);
			}
			else
				throw (new DuplicateDataSetException("Duplicate data set %s", 201, NULL, $dataSet->getName()))->setDataSet($dataSet);
		}
	}

	/**
	 * Intern consistency check
	 *
	 * @param DataSetInterface $dataSet
	 * @return bool
	 */
	protected function checkDuplicationAndConsistency(DataSetInterface $dataSet): bool
	{
		if($dataSet->getStatisticDescription() !== NULL && $dataSet->getStatisticDescription() !== $this) {
			throw (new DataConsistencyException("Data set %s is already part of another statistic %s", 99, NULL, $dataSet->getName(), $this->getName()))->setDataSet($dataSet);
		}

		return !isset($this->dataSets[ $dataSet->getName() ]);
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return string|null
	 */
	public function getDescription(): ?string
	{
		return $this->description;
	}

	/**
	 * @return DataSetInterface[]
	 */
	public function getDataSets(): array
	{
		return $this->dataSets;
	}

	/**
	 * @param string $name
	 * @return DataSetInterface|null
	 */
	public function getDataSet(string $name): ?DataSetInterface {
		return $this->dataSets[$name] ?? NULL;
	}
}