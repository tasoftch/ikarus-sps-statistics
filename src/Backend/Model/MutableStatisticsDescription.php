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
use Ikarus\SPS\Statistics\Exception\DuplicateDataSetException;

class MutableStatisticsDescription extends StatisticsDescription
{
	public function __construct(string $name, string $description = NULL, array $dataSets = [])
	{
		parent::__construct($name, $dataSets, $description);
	}



	public function addDataSet(DataSetInterface $dataSet): MutableStatisticsDescription
	{
		if($this->checkDuplicationAndConsistency($dataSet)) {
			$this->dataSets[$dataSet->getName()] = $dataSet;
			$dataSet->setStatisticDescription($this);
		} else
			throw (new DuplicateDataSetException("Duplicate data set %s", 201, NULL, $dataSet->getName()))->setDataSet($dataSet);
		return $this;
	}

	public function replaceDataSet(DataSetInterface $dataSet): MutableStatisticsDescription
	{
		$this->checkDuplicationAndConsistency($dataSet);
		if(isset($this->dataSets[$dataSet->getName()]))
			$this->dataSets[$dataSet->getName()]->setStatisticDescription(NULL);
		$this->dataSets[$dataSet->getName()] = $dataSet;
		$dataSet->setStatisticDescription($this);
		return $this;
	}

	public function removeDataSet($dataSet): MutableStatisticsDescription
	{
		if($dataSet instanceof DataSetInterface)
			$dataSet = $dataSet->getName();

		if(isset($this->dataSets[$dataSet])) {
			$this->dataSets[$dataSet]->setStatisticDescription(NULL);
			unset($this->dataSets[$dataSet]);
		}


		return $this;
	}
}