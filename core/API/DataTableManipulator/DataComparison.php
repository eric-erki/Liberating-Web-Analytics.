<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\API\DataTableManipulator;

use Piwik\API\DataTableManipulator;
use Piwik\Archive\DataTableFactory;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Period;
use Piwik\Plugin\Report;
use Piwik\Plugin\ReportsProvider;

/**
 * This class is responsible for setting the metadata property 'totals' on each dataTable if the report
 * has a dimension. 'Totals' means it tries to calculate the total report value for each metric. For each
 * the total number of visits, actions, ... for a given report / dataTable.
 */
class DataComparison extends DataTableManipulator
{
    /**
     * @var Report
     */
    private $report;

    /**
     * Constructor
     *
     * @param bool $apiModule
     * @param bool $apiMethod
     * @param array $request
     * @param Report $report
     */
    public function __construct($apiModule = false, $apiMethod = false, $request = array(), $report = null)
    {
        parent::__construct($apiModule, $apiMethod, $request);
        $this->report = $report;
    }

    /**
     * @param  DataTable $table
     * @return \Piwik\DataTable|\Piwik\DataTable\Map
     */
    public function calculate($table)
    {
        if (empty($this->apiModule) || empty($this->apiMethod)) {
            return $table;
        }

        try {
            return $this->manipulate($table);
        } catch (\Exception $e) {
            // eg. requests with idSubtable may trigger this exception
            // (where idSubtable was removed in
            // ?module=API&method=Events.getNameFromCategoryId&idSubtable=1&secondaryDimension=eventName&format=XML&idSite=1&period=day&date=yesterday&flat=0
            return $table;
        }
    }

    /**
     * Adds ratio metrics if possible.
     *
     * @param  DataTable $dataTable
     * @return DataTable
     */
    protected function manipulateDataTable($dataTable)
    {
        if (!empty($this->report) && !$this->report->getDimension()) {
            // we currently do not calculate the total value for reports having no dimension
            return $dataTable;
        }

        if (1 != Common::getRequestVar('compare', 1, 'integer', $this->request)) {
            return $dataTable;
        }

        // todo we need to apply flattener to $dataTable or only support root table I suppose.

        $compareDateReport = $this->fetchCompareDateReport();

        if ($compareDateReport && !$compareDateReport->getRowsCount()) {
            // no rows to merge
            return $dataTable->getEmptyClone($keepFilters = true);
        } elseif ($compareDateReport) {

            $compareSegmentReportDates1 = $this->fetchCompareSegmentReports($dataTable);
            $compareSegmentReportDates2 = $this->fetchCompareSegmentReports($compareDateReport);

            $dataTable->filter(function (DataTable $dataTable) use ($compareDateReport, $compareSegmentReportDates1, $compareSegmentReportDates2) {
                foreach ($dataTable->getRows() as $index => $row) {
                    $label = $row->getColumn('label');
                    $compareRow = $compareDateReport->getRowFromLabel($label);
                    if (!$compareRow) {
                        $dataTable->deleteRow($index);
                        continue;
                    }

                    $subTable = $dataTable->getEmptyClone(true);

                    /** @var Period $period */
                    $period = $dataTable->getMetadata(DataTableFactory::TABLE_METADATA_PERIOD_INDEX);

                    $col1 = $row->getColumns();
                    $col1['label'] = $period->getPrettyString();
                    $col2 = $compareRow->getColumns();
                    $col2['label'] = $compareDateReport->getMetadata(DataTableFactory::TABLE_METADATA_PERIOD_INDEX)->getPrettyString();
                    $row1 = new DataTable\Row(array(DataTable\Row::COLUMNS => $col1));
                    $row2 = new DataTable\Row(array(DataTable\Row::COLUMNS => $col2));
                    $subTable->addRow($row1);
                    $subTable->addRow($row2);

                    $row->setSubtable($subTable);
                    $row->setColumns(array('label' => $label)); // unset all other metrics

                    if ($compareSegmentReportDates1 && $compareSegmentReportDates2) {
                        $this->compareSegmentRow($row1, $index, $label, $dataTable, $compareSegmentReportDates1);
                        $this->compareSegmentRow($row2, $index, $label, $dataTable, $compareSegmentReportDates2);
                    }

                }
            });
        } else {

            $compareSegmentDataTables = $this->fetchCompareSegmentReports($dataTable);
            if ($compareSegmentDataTables) {
                $dataTable->filter(function (DataTable $dataTable) use ($compareSegmentDataTables) {
                    foreach ($dataTable->getRows() as $index => $row) {
                        $this->compareSegmentRow($row, $index, $row->getColumn('label'), $dataTable, $compareSegmentDataTables);
                    }
                });
            }
        }

        return $dataTable;
    }

    private function compareSegmentRow(DataTable\Row $row, $index, $label, DataTable $dataTable, $compareSegmentDataTables)
    {
        foreach ($compareSegmentDataTables as $compareSegmentDataTable) {
            /** @var DataTable $compareSegmentDataTable */

            // label needs to be present in all segments
            $compareRow = $compareSegmentDataTable->getRowFromLabel($label);
            if (!$compareRow) {
                $dataTable->deleteRow($index);
                return;
            }
        }

        $subTable = $dataTable->getEmptyClone(true);


        $col1 = $row->getColumns();
        $col1['label'] = $dataTable->getMetadata('segmentCompare'); // todo use name of saved segment or fallback to segment human readable
        $row1 = new DataTable\Row(array(DataTable\Row::COLUMNS => $col1));
        $subTable->addRow($row1);

        foreach ($compareSegmentDataTables as $compareSegmentDataTable) {
            /** @var DataTable $compareSegmentDataTable */
            $compareRow = $compareSegmentDataTable->getRowFromLabel($label);
            if ($compareRow) {
                $col2 = $compareRow->getColumns();
                $col2['label'] = $compareSegmentDataTable->getMetadata('segmentCompare'); // todo use name of saved segment or fallback to segment human readable
                $row2 = new DataTable\Row(array(DataTable\Row::COLUMNS => $col2));
                $subTable->addRow($row2);
            }
        }

        $row->setSubtable($subTable);
        $row->setColumns(array('label' => $label)); // unset all other metrics
    }

    private function fetchCompareDateReport()
    {
        $date1 = Common::getRequestVar('date1', '', 'string', $this->request);
        $period1 = Common::getRequestVar('period1', '', 'string', $this->request);

        if (empty($date1) || empty($period1)) {
            return;
        }

        $firstLevelReport = $this->findFirstLevelReport();

        if (empty($firstLevelReport)) {
            // it is not a subtable report
            $module = $this->apiModule;
            $action = $this->apiMethod;
        } else {
            $module = $firstLevelReport->getModule();
            $action = $firstLevelReport->getAction();
        }

        $request = $this->request;
        $request['compare'] = 0;
        $request['flat'] = 1;
        unset($request['idSubtable']); // to make sure we work on first level table
        unset($request['date1']);
        unset($request['perod1']);

        // we want a dataTable, not a dataTable\map
        if (Period::isMultiplePeriod($request['date'], $request['period'])) {
            $request['date']   = $date1;
            $request['period'] = 'range';
        } else {
            $request['date']   = $date1;
            $request['period'] = $period1;
        }

        $table = $this->callApiAndReturnDataTable($module, $action, $request);

        if ($table instanceof DataTable\Map) {
            $table = $table->mergeChildren();
        }

        return $table;
    }

    private function fetchCompareSegmentReports($table)
    {
        $tables = array();

        $segment = Common::getRequestVar('segment', '', 'string', $this->request);

        if (!$segment) {
            // we need to have a segment to compare it
            return $tables;
        }

        $table->setMetadata('segmentCompare', $segment);

        for ($i = 1; $i <= 4; $i++) {
            $segmentCompare = Common::getRequestVar('segment' . $i, '', 'string', $this->request);
            if ($segmentCompare) {
                $firstLevelReport = $this->findFirstLevelReport();

                if (empty($firstLevelReport)) {
                    // it is not a subtable report
                    $module = $this->apiModule;
                    $action = $this->apiMethod;
                } else {
                    $module = $firstLevelReport->getModule();
                    $action = $firstLevelReport->getAction();
                }

                $request = $this->request;
                unset($request['idSubtable']); // to make sure we work on first level table
                unset($request['segment']);

                $request['segment'] = $segmentCompare;
                $request['flat'] = 1;
                $request['compare'] = 0;


                /** @var \Piwik\Period $period */
                $period = $table->getMetadata('period');

                if (!empty($period)) {
                    // we want a dataTable, not a dataTable\map
                    if (Period::isMultiplePeriod($request['date'], $request['period']) || 'range' == $period->getLabel()) {
                        $request['date']   = $period->getRangeString();
                        $request['period'] = 'range';
                    } else {
                        $request['date']   = $period->getDateStart()->toString();
                        $request['period'] = $period->getLabel();
                    }
                }

                $table = $this->callApiAndReturnDataTable($module, $action, $request);

                $table->setMetadata('segmentCompare', $segmentCompare);
                if ($table instanceof DataTable\Map) {
                    $table = $table->mergeChildren();
                }

                $tables[] = $table;
            }
        }

        return $tables;
    }

    /**
     * Make sure to get all rows of the first level table.
     *
     * @param array $request
     * @return array
     */
    protected function manipulateSubtableRequest($request)
    {
        $request['totals']        = 0;
        $request['expanded']      = 0;
        $request['filter_limit']  = -1;
        $request['filter_offset'] = 0;
        $request['filter_sort_column'] = '';

        $parametersToRemove = array('flat');

        if (!array_key_exists('idSubtable', $this->request)) {
            $parametersToRemove[] = 'idSubtable';
        }

        foreach ($parametersToRemove as $param) {
            if (array_key_exists($param, $request)) {
                unset($request[$param]);
            }
        }
        return $request;
    }

    private function findFirstLevelReport()
    {
        $reports = new ReportsProvider();
        foreach ($reports->getAllReports() as $report) {
            $actionToLoadSubtables = $report->getActionToLoadSubTables();
            if ($actionToLoadSubtables == $this->apiMethod
                && $this->apiModule == $report->getModule()
            ) {
                return $report;
            }
        }
        return null;
    }

}
