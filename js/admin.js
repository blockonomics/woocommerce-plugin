app = angular.module("blockonomics-admin", []);

//AdminController
app.controller('AdminController', function($scope, $interval, $timeout) {
    $scope.toggle = 0;
    //Toggle Advanced Settings
    $scope.advanced_settings_click = function() {
        $scope.toggle = 1;
        document.getElementById("basic-settings").classList.remove("selected");
        document.getElementById("advanced-settings").classList.add("selected");
    }
    //Toggle Basic Settings
    $scope.basic_settings_click = function() {
        $scope.toggle = 0;
        document.getElementById("basic-settings").classList.add("selected");
        document.getElementById("advanced-settings").classList.remove("selected");
    }
});