var app = angular.module('myApp', []);

app.controller('includes', function($scope, $http) {
	$http.get("http://localhost/realimage/2016/getCountries")
    .then(function(response) {
    	$scope.locations = response.data;
    	console.log(response.data);
    });
    
});