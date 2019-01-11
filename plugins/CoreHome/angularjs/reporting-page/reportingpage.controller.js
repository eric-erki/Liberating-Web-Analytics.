/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
(function () {
    angular.module('piwikApp').controller('ReportingPageController', ReportingPageController);

    ReportingPageController.$inject = ['$scope', 'piwik', '$rootScope', '$location', 'reportingPageModel', 'reportingPagesModel', 'notifications'];

    function ReportingPageController($scope, piwik, $rootScope, $location, pageModel, pagesModel, notifications) {
        pageModel.resetPage();
        $scope.pageModel = pageModel;

        var currentCategory = null;
        var currentSubcategory = null;
        var currentPeriod = null;
        var currentDate = null;
        var currentSegment = null;

        var currentDate1 = null;
        var currentPeriod1 = null;
        var currentSegment1 = null;
        var currentSegment2 = null;
        var currentSegment3 = null;
        var currentSegment4 = null;

        function renderInitialPage()
        {
            var $search = $location.search();
            currentPeriod = $search.period;
            currentDate = $search.date;
            currentSegment = $search.segment;
            $scope.renderPage($search.category, $search.subcategory);
        }

        $scope.renderPage = function (category, subcategory) {
            if (!category || !subcategory) {
                pageModel.resetPage();
                $scope.loading = false;
                return;
            }

            $rootScope.$emit('piwikPageChange', {});

            currentCategory = category;
            currentSubcategory = subcategory;

            notifications.clearTransientNotifications();

            if (category === 'Dashboard_Dashboard' && $.isNumeric(subcategory) && $('[piwik-dashboard]').length) {
                // hack to make loading of dashboards faster since all the information is already there in the
                // piwik-dashboard widget, we can let the piwik-dashboard widget render the page. We need to find
                // a proper solution for this. A workaround for now could be an event or something to let other
                // components render a specific page.
                $scope.loading = true;
                var element = $('[piwik-dashboard]');
                var scope = angular.element(element).scope();
                scope.fetchDashboard(parseInt(subcategory, 10)).then(function () {
                    $scope.loading = false;
                }, function () {
                    $scope.loading = false;
                });
                return;
            }

            pageModel.fetchPage(category, subcategory).then(function () {

                if (!pageModel.page) {
                    var page = pagesModel.findPageInCategory(category);
                    if (page && page.subcategory) {
                        var $search = $location.search();
                        $search.subcategory = page.subcategory.id;
                        $location.search($search);
                        return;
                    }
                }

                $scope.hasNoPage = !pageModel.page;
                $scope.loading = false;
            });
        }

        $scope.loading = true; // we only set loading on initial load
        
        renderInitialPage();

        $rootScope.$on('$locationChangeSuccess', function () {
            var $search = $location.search();

            // should be handled by $route
            var category = $search.category;
            var subcategory = $search.subcategory;
            var period = $search.period;
            var date = $search.date;
            var segment = $search.segment;
            var segment1 = $search.segment1;
            var segment2 = $search.segment2;
            var segment3 = $search.segment3;
            var segment4 = $search.segment4;
            var date1 = $search.date1;
            var period1 = $search.period1;

            if (category === currentCategory
                && subcategory === currentSubcategory
                && period === currentPeriod
                && date === currentDate
                && segment === currentSegment
                && date1 === currentDate1
                && period1 === currentPeriod1
                && segment1 === currentSegment1
                && segment2 === currentSegment2
                && segment3 === currentSegment3
                && segment4 === currentSegment4) {
                // this page is already loaded
                return;
            }

            currentPeriod = period;
            currentDate = date;
            currentSegment = segment;
            currentDate1 = date1;
            currentPeriod1 = period1;
            currentSegment1 = segment1;
            currentSegment2 = segment2;
            currentSegment3 = segment3;
            currentSegment4 = segment4;

            $scope.renderPage(category, subcategory);
        });

        $rootScope.$on('loadPage', function (event, category, subcategory) {
            $scope.renderPage(category, subcategory);
        });
    }
})();
