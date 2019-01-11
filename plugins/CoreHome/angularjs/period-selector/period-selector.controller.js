/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

(function () {
    angular.module('piwikApp').controller('PeriodSelectorController', PeriodSelectorController);

    PeriodSelectorController.$inject = ['piwik', '$location', 'piwikPeriods'];

    function PeriodSelectorController(piwik, $location, piwikPeriods) {
        var piwikMinDate = new Date(piwik.minDateYear, piwik.minDateMonth - 1, piwik.minDateDay),
            piwikMaxDate = new Date(piwik.maxDateYear, piwik.maxDateMonth - 1, piwik.maxDateDay);

        var vm = this;

        // the period & date currently being viewed
        vm.periodValue = null;
        vm.dateValue = null;

        vm.selectedPeriod = null;

        vm.startRangeDate = null;
        vm.endRangeDate = null;
        vm.isRangeValid = null;
        vm.compareToEnabled = false;
        vm.compareDateFrom = '';
        vm.compareDateTo = '';
        vm.compareToMethod = 'previousDate';
        vm.compareToMethods = [
            {id: 'custom', label: 'Custom'},
            {id: 'previousDate', label: 'Previous Date'},
            {id: 'previousYear', label: 'Previous Year'}
        ];

        vm.isLoadingNewPage = false;

        vm.getCurrentlyViewingText = getCurrentlyViewingText;
        vm.changeViewedPeriod = changeViewedPeriod;
        vm.setPiwikPeriodAndDate = setPiwikPeriodAndDate;
        vm.onApplyClicked = onApplyClicked;
        vm.updateSelectedValuesFromHash = updateSelectedValuesFromHash;
        vm.getPeriodDisplayText = getPeriodDisplayText;
        vm.$onChanges = $onChanges;
        vm.onRangeChange = onRangeChange;
        vm.isApplyEnabled = isApplyEnabled;
        vm.$onInit = init;
        vm.compare = compare;

        function init() {
            vm.updateSelectedValuesFromHash();
            initTopControls(); // must be called when a top control changes width
        }

        function compare() {
            if (vm.compareToEnabled) {
                var dateFrom = vm.compareDateFrom,
                    dateTo = vm.compareDateTo,
                    oDateFrom = piwikPeriods.parseDate(dateFrom),
                    oDateTo = piwikPeriods.parseDate(dateTo);

                if (!isValidDate(oDateFrom)
                    || !isValidDate(oDateTo)
                    || oDateFrom > oDateTo
                ) {
                    // TODO: use a notification instead?
                    $('#alert').find('h2').text(_pk_translate('General_InvalidDateRange'));
                    piwik.helper.modalConfirm('#alert', {});
                    return;
                }

                propagateNewUrlParams(dateFrom + ',' + dateTo, 'range', 'date1', 'period1');
            } else {
                propagateNewUrlParams('', '', 'date1', 'period1');
            }
        }

        function $onChanges(changesObj) {
            if (changesObj.periods) {
                removeUnrecognizedPeriods();
            }
        }

        function onRangeChange(start, end) {
            if (!start || !end) {
                vm.isRangeValid = false;
                return;
            }

            vm.isRangeValid = true;
            vm.startRangeDate = start;
            vm.endRangeDate = end;
        }

        function isApplyEnabled() {
            if (vm.selectedPeriod === 'range'
                && !vm.isRangeValid
            ) {
                return false;
            }

            return true;
        }

        function removeUnrecognizedPeriods() {
            vm.periods = vm.periods.filter(function (periodLabel) {
                return piwikPeriods.isRecognizedPeriod(periodLabel);
            });
        }

        function updateSelectedValuesFromHash() {
            var strDate = getQueryParamValue('date');
            var strPeriod = getQueryParamValue('period');

            vm.periodValue = strPeriod;
            vm.selectedPeriod = strPeriod;

            vm.dateValue = vm.startRangeDate = vm.endRangeDate = null;

            if (strPeriod === 'range') {
                var period = piwikPeriods.get(strPeriod).parse(strDate);
                vm.dateValue = period.startDate;
                vm.startRangeDate = formatDate(period.startDate);
                vm.endRangeDate = formatDate(period.endDate);
            } else {
                vm.dateValue = piwikPeriods.parseDate(strDate);
                setRangeStartEndFromPeriod(strPeriod, strDate);
            }


            var strDate1 = getQueryParamValue('date1');
            var strPeriod1 = getQueryParamValue('period1');
            if (strDate1 && strPeriod1) {
                var period = piwikPeriods.get(strPeriod1).parse(strDate1);
                vm.compareDateFrom = formatDate(period.startDate);
                vm.compareDateTo = formatDate(period.endDate);
                vm.compareToEnabled = true;
            }
        }

        function getQueryParamValue(name) {
            // $location doesn't parse the URL before the hashbang, but it can hold the query param
            // values, if the page doesn't have the hashbang.
            var result = $location.search()[name];
            if (!result) {
                result = broadcast.getValueFromUrl(name);
            }
            return result;
        }

        function getPeriodDisplayText(periodLabel) {
            return piwikPeriods.get(periodLabel).getDisplayText();
        }

        function getCurrentlyViewingText() {
            var date;
            if (vm.periodValue === 'range') {
                date = vm.startRangeDate + ',' + vm.endRangeDate;
            } else {
                date = formatDate(vm.dateValue);
            }

            try {
                return piwikPeriods.parse(vm.periodValue, date).getPrettyString();
            } catch (e) {
                return _pk_translate('General_Error');
            }
        }

        function changeViewedPeriod(period) {
            // only change period if it's different from what's being shown currently
            if (period === vm.periodValue) {
                return;
            }

            // can't just change to a range period, w/o setting two new dates
            if (period === 'range') {
                return;
            }

            setPiwikPeriodAndDate(period, vm.dateValue);
        }

        function onApplyClicked() {
            if (vm.selectedPeriod === 'range') {
                var dateFrom = vm.startRangeDate,
                    dateTo = vm.endRangeDate,
                    oDateFrom = piwikPeriods.parseDate(dateFrom),
                    oDateTo = piwikPeriods.parseDate(dateTo);

                if (!isValidDate(oDateFrom)
                    || !isValidDate(oDateTo)
                    || oDateFrom > oDateTo
                ) {
                    // TODO: use a notification instead?
                    $('#alert').find('h2').text(_pk_translate('General_InvalidDateRange'));
                    piwik.helper.modalConfirm('#alert', {});
                    return;
                }

                vm.periodValue = 'range';

                propagateNewUrlParams(dateFrom + ',' + dateTo, 'range');
                return;
            }

            setPiwikPeriodAndDate(vm.selectedPeriod, vm.dateValue);
        }

        function setPiwikPeriodAndDate(period, date) {
            vm.periodValue = period;
            vm.selectedPeriod = period;
            vm.dateValue = date;

            var currentDateString = formatDate(date);
            setRangeStartEndFromPeriod(period, currentDateString);

            propagateNewUrlParams(currentDateString, vm.selectedPeriod);
            initTopControls();
        }

        function setRangeStartEndFromPeriod(period, dateStr) {
            var dateRange = piwikPeriods.parse(period, dateStr).getDateRange();
            vm.startRangeDate = formatDate(dateRange[0] < piwikMinDate ? piwikMinDate : dateRange[0]);
            vm.endRangeDate = formatDate(dateRange[1] > piwikMaxDate ? piwikMaxDate : dateRange[1]);
        }

        function propagateNewUrlParams(date, period, dateParam, periodParam) {
            if (!dateParam) {
                dateParam = 'date';
            }
            if (!periodParam) {
                periodParam = 'period'
            }
            if (piwik.helper.isAngularRenderingThePage()) {
                vm.closePeriodSelector(); // defined in directive

                var $search = $location.search();
                if (date !== $search.date || period !== $search.period) {
                    // eg when using back button the date might be actually already changed in the URL and we do not
                    // want to change the URL again
                    if (date) {
                        $search[dateParam] = date;
                    } else {
                        delete $search[dateParam];
                    }
                    if (period) {
                        $search[periodParam] = period;
                    } else {
                        delete $search[periodParam];
                    }
                    $location.search($search);
                }

                return;
            }

            vm.isLoadingNewPage = true;

            // not in an angular context (eg, embedded dashboard), so must actually
            // change the URL
            broadcast.propagateNewPage(dateParam + '=' + date + '&' + periodParam + '=' + period);
        }

        function isValidDate(d) {
            if (Object.prototype.toString.call(d) !== "[object Date]") {
                return false;
            }

            return !isNaN(d.getTime());
        }

        function formatDate(date) {
            return $.datepicker.formatDate('yy-mm-dd', date);
        }
    }
})();
